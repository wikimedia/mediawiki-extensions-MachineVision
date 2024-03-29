<?php

namespace MediaWiki\Extension\MachineVision;

use IDBAccessObject;
use InvalidArgumentException;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Storage\NameTableStore;
use MediaWiki\User\UserIdentity;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use UnexpectedValueException;
use Wikimedia\Rdbms\IDatabase;

/**
 * Database interaction for label suggestions.
 */
class Repository implements LoggerAwareInterface {

	use LoggerAwareTrait;

	// Constants used for machine_vision_label.mvl_review
	public const REVIEW_UNREVIEWED = 0;
	public const REVIEW_ACCEPTED = 1;
	public const REVIEW_REJECTED = -1;
	public const REVIEW_WITHHELD_POPULAR = -2;
	public const REVIEW_WITHHELD_ALL = -3;
	public const REVIEW_NOT_DISPLAYED = -4;

	private static $reviewStates = [
		self::REVIEW_UNREVIEWED,
		self::REVIEW_ACCEPTED,
		self::REVIEW_REJECTED,
		self::REVIEW_WITHHELD_POPULAR,
		self::REVIEW_WITHHELD_ALL,
		self::REVIEW_NOT_DISPLAYED
	];

	/** @var NameTableStore */
	private $nameTableStore;

	/** @var IDatabase */
	private $dbr;

	/** @var IDatabase */
	private $dbw;

	/**
	 * @param NameTableStore $nameTableStore NameTableStore for provider names
	 * @param IDatabase $dbr Database connection for reading.
	 * @param IDatabase $dbw Database connection for writing.
	 */
	public function __construct(
		NameTableStore $nameTableStore,
		IDatabase $dbr,
		IDatabase $dbw
	) {
		$this->nameTableStore = $nameTableStore;
		$this->dbr = $dbr;
		$this->dbw = $dbw;

		$this->logger = LoggerFactory::getInstance( 'machinevision' );
	}

	/**
	 * Insert a new set of label suggestions, and metadata about them, into the DB tables.
	 * Tables have uniqueness constraints enforced, and duplicate key errors are ignored
	 * since it is expected for the job queue to perform a job more than once in some cases (in
	 * case of service restart, for example).
	 * @param string $sha1 Image SHA1
	 * @param string $providerName Provider name
	 * @param UserIdentity|null $uploader the uploader's identity, if known
	 * @param LabelSuggestion[] $suggestions A list of Wikidata ID such as 'Q123'
	 * @param int $priority priority value between -128 & 127
	 * @param int $initialState initial review state
	 * @return int $labelsCount
	 */
	public function insertLabels(
		$sha1,
		$providerName,
		?UserIdentity $uploader,
		array $suggestions,
		$priority = 0,
		$initialState = self::REVIEW_UNREVIEWED
	) {
		$providerId = $this->nameTableStore->acquireId( $providerName );
		$timestamp = $this->dbw->timestamp();
		$labelsCount = 0;

		$this->dbw->insert(
			'machine_vision_image',
			[
				'mvi_sha1' => $sha1,
				'mvi_priority' => $priority,
				'mvi_rand' => $this->getRandomFloat(),
			],
			__METHOD__,
			[ 'IGNORE' ]
		);

		$mviRowId = $this->dbw->insertId() ?: $this->dbw->selectField(
			'machine_vision_image',
			'mvi_id',
			[ 'mvi_sha1' => $sha1 ],
			__METHOD__
		);

		foreach ( $suggestions as $suggestion ) {
			$wikidataId = $suggestion->getWikidataId();
			$confidence = $suggestion->getConfidence();

			$this->dbw->insert(
				'machine_vision_label',
				[
					'mvl_mvi_id' => $mviRowId,
					'mvl_wikidata_id' => $wikidataId,
					'mvl_review' => $initialState,
					'mvl_uploader_id' => $uploader ? $uploader->getId() : 0,
				],
				__METHOD__,
				[ 'IGNORE' ]
			);

			$mvlId = $this->dbw->insertId();
			if ( $mvlId ) {
				// new label inserted; increment $labelsCount
				$labelsCount++;
			} else {
				// suggested label already exists in DB and insert failed;
				// re-query for row ID
				$mvlId = $this->dbw->selectField(
					'machine_vision_label',
					'mvl_id',
					[
						'mvl_mvi_id' => $mviRowId,
						'mvl_wikidata_id' => $wikidataId,
					],
					__METHOD__
				);
			}

			$this->dbw->insert(
				'machine_vision_suggestion',
				[
					'mvs_mvl_id' => $mvlId,
					'mvs_provider_id' => $providerId,
					'mvs_timestamp' => $timestamp,
					'mvs_confidence' => $confidence,
				],
				__METHOD__,
				[ 'IGNORE' ]
			);
		}

		return $labelsCount;
	}

	/**
	 * Get the suggested labels of an image.
	 * @param string|array $sha1 Image SHA1
	 * @return array List of Wikidata item IDs (including the Q prefix)
	 */
	public function getLabels( $sha1 ) {
		$res = $this->dbr->select(
			[
				'machine_vision_image',
				'machine_vision_label',
				'machine_vision_suggestion',
				'machine_vision_provider'
			],
			[ 'mvi_sha1', 'mvl_wikidata_id', 'mvl_review', 'mvl_reviewer_id', 'mvs_confidence', 'mvp_name' ],
			[ 'mvi_sha1' => $sha1 ],
			__METHOD__,
			[],
			[
				'machine_vision_label' => [ 'INNER JOIN', [ 'mvi_id = mvl_mvi_id' ] ],
				'machine_vision_suggestion' => [ 'INNER JOIN', [ 'mvl_id = mvs_mvl_id' ] ],
				'machine_vision_provider' => [ 'INNER JOIN', [ 'mvs_provider_id = mvp_id' ] ]
			]
		);

		$data = [];
		foreach ( $res as $row ) {
			$sha1 = $row->mvi_sha1;
			$label = $row->mvl_wikidata_id;
			$provider = $row->mvp_name;
			$confidence = (float)$row->mvs_confidence;
			if ( array_key_exists( $label, $data ) ) {
				$data["$sha1-$label"]['confidence'][$provider] = $confidence;
			} else {
				$data["$sha1-$label"] = [
					'sha1' => $sha1,
					'wikidata_id' => $label,
					'review' => (int)$row->mvl_review,
					'reviewer_id' => (int)$row->mvl_reviewer_id,
					'confidence' => [ $provider => $confidence ],
				];
			}
		}
		return array_values( $data );
	}

	/**
	 * Change the state of a label suggestion.
	 * @param string $sha1 Image SHA1
	 * @param string $label Image label (Wikidata item ID, including the Q prefix)
	 * @param int $state New state (one of the REVIEW_* constants).
	 * @param int $reviewerId Local user ID of the user submitting the review.
	 * @param int $ts review timestamp (unix format with microseconds, converted to an int)
	 * @return bool Success
	 */
	public function setLabelState( $sha1, $label, $state, $reviewerId, $ts ) {
		$validStates = array_diff( self::$reviewStates,
			[ self::REVIEW_UNREVIEWED, self::REVIEW_WITHHELD_POPULAR ] );
		if ( !in_array( $state, $validStates, true ) ) {
			$validStates = implode( ', ', $validStates );
			throw new InvalidArgumentException( "Invalid state $state (must be one of $validStates)" );
		}

		$mviId = $this->getMviIdForSha1( $sha1 );
		$this->dbw->update(
			'machine_vision_label',
			[
				'mvl_review' => $state,
				'mvl_reviewer_id' => $reviewerId,
				'mvl_reviewed_time' => $ts
			],
			[ 'mvl_mvi_id' => $mviId, 'mvl_wikidata_id' => $label ],
			__METHOD__
		);
		return (bool)$this->dbw->affectedRows();
	}

	/**
	 * Get the state of a label suggestion.
	 * @param string $sha1 Image SHA1
	 * @param string $label Image label (Wikidata item ID, including the Q prefix)
	 * @param int $flags IDBAccessObject flags
	 * @return int|false Label review state (one of the REVIEW_* constants),
	 *   or false if there was no such suggestion.
	 */
	public function getLabelState( $sha1, $label, $flags = 0 ) {
		if ( ( $flags & IDBAccessObject::READ_LATEST ) == IDBAccessObject::READ_LATEST ) {
			$db = $this->dbw;
		} else {
			$db = $this->dbr;
		}

		$state = $db->newSelectQueryBuilder()
			->select( 'mvl_review' )
			->from( 'machine_vision_image' )
			->join( 'machine_vision_label', null, 'mvi_id = mvl_mvi_id' )
			->where( [ 'mvi_sha1' => $sha1, 'mvl_wikidata_id' => $label ] )
			->recency( $flags )
			->caller( __METHOD__ )->fetchField();

		if ( $state === false ) {
			return false;
		}
		if ( !in_array( (int)$state, self::$reviewStates, true ) ) {
			throw new UnexpectedValueException( "Invalid database value for mvl_review: $state" );
		}
		return (int)$state;
	}

	/**
	 * Set all unreviewed label suggestions for an image to REVIEW_WITHHELD_POPULAR.
	 * @param string $sha1 image SHA1 digest
	 */
	public function withholdImageFromPopular( $sha1 ) {
		$mviId = $this->getMviIdForSha1( $sha1 );
		$this->dbw->update(
			'machine_vision_label',
			[ 'mvl_review' => self::REVIEW_WITHHELD_POPULAR ],
			[
				'mvl_mvi_id' => $mviId,
				'mvl_review' => self::REVIEW_UNREVIEWED,
			],
			__METHOD__
		);
	}

	/**
	 * Get a random-ish list of image titles with unreviewed image labels.
	 * @param int $limit
	 * @param int|null $userId local user ID for filtering
	 * @return string[] Titles of file pages with associated unreviewed labels
	 */
	public function getTitlesWithUnreviewedLabels( $limit, $userId = null ) {
		// Previously this query used the DISTINCT keyword to derive unique image SHA-1 digests
		// from a query over the machine_vision_label table, in which they are duplicated, but
		// that query was slow. Instead, we'll query for a multiple of the required values and
		// deduplicate in PHP.
		$multiplier = 10;

		$rand = $this->getRandomFloat();
		$fname = __METHOD__;

		$tables = [ 'machine_vision_image', 'machine_vision_label' ];
		$joins = [ 'machine_vision_label' => [ 'INNER JOIN', [ 'mvi_id = mvl_mvi_id' ] ] ];

		if ( $userId !== null ) {
			$conds = [
				'mvl_review' => [ self::REVIEW_UNREVIEWED, self::REVIEW_WITHHELD_POPULAR ],
				'mvl_uploader_id' => strval( $userId ),
			];
		} else {
			// Because multiple users may request images to label, we can't simply serve
			// the first available images, or they might end up labeling the exact same
			// images. OTOH, we do want higher priority images to go out first, so...
			// We'll do a quick pass to figure out a reasonable cutoff priority; this ought
			// to ensure that we still prioritize, even though we attempt to (randomly)
			// spread the results
			$priority = $this->dbr->selectField(
				'machine_vision_image',
				'mvi_priority',
				[
					'mvi_id IN ' . $this->dbr->buildSelectSubquery(
						'machine_vision_label',
						'mvl_mvi_id',
						[ 'mvl_review' => self::REVIEW_UNREVIEWED ],
						$fname
					)
				],
				$fname,
				[
					'ORDER BY' => 'mvi_priority DESC',
					'LIMIT' => 1,
					'OFFSET' => 20 * $limit
				]
			);
			$priority = $priority === false ? -128 : (int)$priority;

			$conds = [
				'mvl_review' => self::REVIEW_UNREVIEWED,
				'mvi_priority >= ' . strval( $priority ),
			];
		}

		$select = function ( $ascending, $limit, $conds ) use ( $fname, $tables, $joins, $rand,
			$multiplier ) {
			$whereClause = array_merge( $conds,
				[ 'mvi_rand ' . ( $ascending ? '> ' : '< ' ) . strval( $rand ) ] );

			return $this->dbr->selectFieldValues(
				[ 'image', 'machinevision' => $this->dbr->buildSelectSubquery(
					$tables,
					'mvi_sha1',
					$whereClause,
					$fname,
					[
						'ORDER BY' => 'mvi_rand ' . ( $ascending ? 'ASC' : 'DESC' ),
						'LIMIT' => $limit * $multiplier,
					],
					$joins
				) ],
				'img_name',
				'',
				$fname,
				[],
				[ 'machinevision' => [ 'INNER JOIN', [ 'img_sha1 = machinevision.mvi_sha1' ] ] ]
			);
		};

		$names = $select( true, $limit, $conds );

		$shortfall = $limit - count( array_unique( $names ) );
		if ( $shortfall > 0 ) {
			$names = array_merge( $names, $select( false, $shortfall, $conds ) );
		}

		return array_slice( array_unique( $names ), 0, $limit );
	}

	/**
	 * Get count of images uploaded by the specified user with labels awaiting review.
	 * @param int $userId local user id
	 * @return array
	 */
	public function getUnreviewedImageCountForUser( $userId ) {
		$total = $this->dbr->selectRow(
			'machine_vision_label',
			[ 'COUNT(DISTINCT mvl_mvi_id) AS count' ],
			[ 'mvl_uploader_id' => $userId ],
			__METHOD__
		);

		$unreviewed = $this->dbr->selectRow(
			'machine_vision_label',
			[ 'COUNT(DISTINCT mvl_mvi_id) AS count' ],
			"( mvl_uploader_id = " . $userId . " )
			AND ( mvl_review = " . self::REVIEW_UNREVIEWED . "
			OR mvl_review = " . self::REVIEW_WITHHELD_POPULAR . " )",
			__METHOD__
		);

		return [
			'unreviewed' => (int)$unreviewed->count,
			'total' => (int)$total->count,
		];
	}

	/**
	 * Get the mapped Wikidata ID(s) given a Freebase ID.
	 * @param string $freebaseId
	 * @return string[]|false Array containing all matching Wikidata IDs, or false if none are found
	 */
	public function getMappedWikidataIds( $freebaseId ) {
		return $this->dbr->selectFieldValues(
			'machine_vision_freebase_mapping',
			'mvfm_wikidata_id',
			[ 'mvfm_freebase_id' => $freebaseId ],
			__METHOD__
		);
	}

	/**
	 * Insert SafeSearch annotations. For meanings associated with the integer values, see
	 * Google\Cloud\Vision\V1\Likelihood. Duplicate attempts to add the same data are expected,
	 * and duplicate key errors are ignored.
	 * @param string $sha1 image SHA1 digest
	 * @param int $adult
	 * @param int $spoof
	 * @param int $medical
	 * @param int $violence
	 * @param int $racy
	 */
	public function insertSafeSearchAnnotations( $sha1, $adult, $spoof, $medical, $violence,
		$racy ) {
		$mviId = $this->getMviIdForSha1( $sha1 );
		$this->dbw->insert(
			'machine_vision_safe_search',
			[
				'mvss_mvi_id' => $mviId,
				'mvss_adult' => $adult,
				'mvss_spoof' => $spoof,
				'mvss_medical' => $medical,
				'mvss_violence' => $violence,
				'mvss_racy' => $racy,
			],
			__METHOD__,
			[ 'IGNORE' ]
		);
	}

	/**
	 * Delete Machine Vision related data from deleted File
	 *
	 * @param string $sha1 image SHA1 digest
	 * @return void
	 */
	public function deleteDataOfDeletedFile( $sha1 ) {
		$this->dbw->startAtomic( __METHOD__ );
		$mviId = $this->getMviIdForSha1( $sha1 );
		$this->dbw->delete(
			'machine_vision_image',
			[ 'mvi_sha1' => $sha1 ],
			__METHOD__
		);
		$mvlIds = $this->dbw->selectFieldValues(
			'machine_vision_label',
			'mvl_id',
			[ 'mvl_mvi_id' => $mviId ],
			__METHOD__,
			[ 'FOR UPDATE' ]
		);
		if ( !$mvlIds ) {
			$this->dbw->endAtomic( __METHOD__ );
		} else {
			$this->dbw->delete(
				'machine_vision_suggestion',
				[ 'mvs_mvl_id' => $mvlIds ],
				__METHOD__
			);
			$this->dbw->delete(
				'machine_vision_label',
				[ 'mvl_mvi_id' => $mviId ],
				__METHOD__
			);
			$this->dbw->delete(
				'machine_vision_safe_search',
				[ 'mvss_mvi_id' => $mviId ],
				__METHOD__
			);
			$this->dbw->endAtomic( __METHOD__ );
		}
	}

	private function getMviIdForSha1( $sha1 ) {
		return $this->dbw->selectField(
			'machine_vision_image',
			'mvi_id',
			[ 'mvi_sha1' => $sha1 ],
			__METHOD__
		);
	}

	private function getRandomFloat(): float {
		return mt_rand( 0, mt_getrandmax() - 1 ) / mt_getrandmax();
	}

}
