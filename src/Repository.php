<?php

namespace MediaWiki\Extension\MachineVision;

use DBAccessObjectUtils;
use InvalidArgumentException;
use MediaWiki\Storage\NameTableStore;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use UnexpectedValueException;
use Wikimedia\AtEase\AtEase;
use Wikimedia\Rdbms\IDatabase;

/**
 * Database interaction for label suggestions.
 */
class Repository implements LoggerAwareInterface {

	use LoggerAwareTrait;

	// Constants used for machine_vision_label.mvl_review
	const REVIEW_UNREVIEWED = 0;
	const REVIEW_ACCEPTED = 1;
	const REVIEW_REJECTED = -1;
	const REVIEW_WITHHELD = -2;

	private static $reviewStates = [
		self::REVIEW_UNREVIEWED,
		self::REVIEW_ACCEPTED,
		self::REVIEW_REJECTED,
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

		$this->logger = new NullLogger();
	}

	/**
	 * @param string $sha1 Image SHA1
	 * @param string $providerName Provider name
	 * @param int $uploaderId the uploader's local user ID
	 * @param LabelSuggestion[] $suggestions A list of Wikidata ID such as 'Q123'
	 * @param int $initialState initial review state
	 */
	public function insertLabels(
		$sha1,
		$providerName,
		$uploaderId,
		array $suggestions,
		$initialState = self::REVIEW_UNREVIEWED
	) {
		$providerId = $this->nameTableStore->acquireId( $providerName );
		$timestamp = $this->dbw->timestamp();
		foreach ( $suggestions as $suggestion ) {
			$wikidataId = $suggestion->getWikidataId();
			$confidence = $suggestion->getConfidence();

			$this->dbw->insert(
				'machine_vision_label',
				[
					'mvl_image_sha1' => $sha1,
					'mvl_wikidata_id' => $wikidataId,
					'mvl_uploader_id' => $uploaderId,
					'mvl_review' => $initialState,
					'mvl_suggested_time' => (int)( microtime( true ) * 10000 ),
				],
				__METHOD__,
				[ 'IGNORE' ]
			);
			// Need to re-select in case the row was already added from another provider's results
			// and the insert fails, in which case we wouldn't have gotten a row ID from insertId().
			$mvlId = $this->dbw->selectField(
				'machine_vision_label',
				'mvl_id',
				[
					'mvl_image_sha1' => $sha1,
					'mvl_wikidata_id' => $wikidataId,
				],
				__METHOD__
			);
			if ( $mvlId !== false ) {
				$this->dbw->insert(
					'machine_vision_suggestion',
					[
						'mvs_mvl_id' => $mvlId,
						'mvs_provider_id' => $providerId,
						'mvs_timestamp' => $timestamp,
						'mvs_confidence' => $confidence,
					],
					__METHOD__
				);
			} else {
				$this->logger->info(
					'Could not find row ID for recently retrieved label suggestion ' .
						$wikidataId . ' for image sha1 ' . $sha1
				);
			}
		}
	}

	/**
	 * Get the suggested labels of an image.
	 * @param string $sha1 Image SHA1
	 * @return string[] List of Wikidata item IDs (including the Q prefix)
	 */
	public function getLabels( $sha1 ) {
		$res = $this->dbr->select(
			'machine_vision_label',
			[ 'mvl_wikidata_id', 'mvl_review', 'mvl_reviewer_id' ],
			[ 'mvl_image_sha1' => $sha1 ],
			__METHOD__
		);
		$data = [];
		foreach ( $res as $row ) {
			$data[] = [
				'wikidata_id' => $row->mvl_wikidata_id,
				'review' => (int)$row->mvl_review,
				'reviewer_id' => (int)$row->mvl_reviewer_id,
			];
		}
		return $data;
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
		$validStates = array_diff( self::$reviewStates, [ self::REVIEW_UNREVIEWED ] );
		if ( !in_array( $state, $validStates, true ) ) {
			$validStates = implode( ', ', $validStates );
			throw new InvalidArgumentException( "Invalid state $state (must be one of $validStates)" );
		}

		$this->dbw->update(
			'machine_vision_label',
			[
				'mvl_review' => $state,
				'mvl_reviewer_id' => $reviewerId,
				'mvl_reviewed_time' => $ts
			],
			[ 'mvl_image_sha1' => $sha1, 'mvl_wikidata_id' => $label ],
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
		list( $index, $options ) = DBAccessObjectUtils::getDBOptions( $flags );
		$db = ( $index === DB_MASTER ) ? $this->dbw : $this->dbr;

		$state = $db->selectField(
			'machine_vision_label',
			'mvl_review',
			[ 'mvl_image_sha1' => $sha1, 'mvl_wikidata_id' => $label ],
			__METHOD__,
			$options
		);
		if ( $state === false ) {
			return false;
		}
		if ( !in_array( (int)$state, self::$reviewStates, true ) ) {
			throw new UnexpectedValueException( "Invalid database value for mvl_review: $state" );
		}
		return (int)$state;
	}

	/**
	 * Get list of image titles with unreviewed image labels. This effectuates queue-like behavior
	 * by selecting results sorted by mvl_suggested_time and updating selected rows'
	 * mvl_suggested_time with the current microtime as part of the same transaction.
	 * Label suggestions fall out of the "queue" when their review state changes to a state other
	 * than REVIEW_UNREVIEWED.
	 * @param int $limit
	 * @param int|null $userId local user ID for filtering
	 * @return string[] Titles of file pages with associated unreviewed labels
	 */
	public function getTitlesWithUnreviewedLabels( $limit, $userId = null ) {
		$conds = [ 'mvl_review' => self::REVIEW_UNREVIEWED ];
		if ( $userId ) {
			$conds['mvl_uploader_id'] = strval( $userId );
		}
		$this->dbw->startAtomic( __METHOD__ );
		// Suppress a warning about using aggregation (DISTINCT) with a locking select. This is for
		// practical purposes a WMF-specific extension, and the warning is about compatibility with
		// alternative DB backends like Postgres, which isn't a concern in WMF production.
		AtEase::suppressWarnings();
		$sha1s = $this->dbw->selectFieldValues(
			'machine_vision_label',
			'mvl_image_sha1',
			$conds,
			__METHOD__,
			[
				'DISTINCT',
				'FOR UPDATE',
				'ORDER BY' => 'mvl_suggested_time',
				'LIMIT' => $limit,
			]
		);
		AtEase::restoreWarnings();
		if ( !$sha1s ) {
			$this->dbw->endAtomic( __METHOD__ );
			return [];
		}
		$this->dbw->update(
			'machine_vision_label',
			[ 'mvl_suggested_time' => (int)( microtime( true ) * 10000 ) ],
			[ 'mvl_image_sha1' => $sha1s ],
			__METHOD__
		);
		$this->dbw->endAtomic( __METHOD__ );
		return $this->dbw->selectFieldValues(
			'image',
			'img_name',
			[ 'img_sha1' => $sha1s ],
			__METHOD__
		);
	}

	/**
	 * Get count of images uploaded by the specified user with labels awaiting review.
	 * @param int $userId local user id
	 * @return int
	 */
	public function getUnreviewedImageCountForUser( $userId ) {
		return (int)$this->dbr->selectField(
			[ 'derived' => $this->dbr->buildSelectSubquery(
				'machine_vision_label',
				'*',
				[
					'mvl_review' => self::REVIEW_UNREVIEWED,
					'mvl_uploader_id' => $userId,
				],
				__METHOD__,
				[ 'GROUP BY' => 'mvl_image_sha1' ]
			) ],
			'COUNT(*)',
			[],
			__METHOD__
		);
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
	 *  Google\Cloud\Vision\V1\Likelihood.
	 * @param string $sha1 image SHA1 digest
	 * @param int $adult
	 * @param int $spoof
	 * @param int $medical
	 * @param int $violence
	 * @param int $racy
	 */
	public function insertSafeSearchAnnotations( $sha1, $adult, $spoof, $medical, $violence,
		$racy ) {
		$this->dbw->insert(
			'machine_vision_safe_search',
			[
				'mvss_image_sha1' => $sha1,
				'mvss_adult' => $adult,
				'mvss_spoof' => $spoof,
				'mvss_medical' => $medical,
				'mvss_violence' => $violence,
				'mvss_racy' => $racy,
			],
			__METHOD__
		);
	}

}
