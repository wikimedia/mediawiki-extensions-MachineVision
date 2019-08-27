<?php

namespace MediaWiki\Extension\MachineVision;

use DBAccessObjectUtils;
use InvalidArgumentException;
use MediaWiki\Storage\NameTableStore;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use UnexpectedValueException;
use Wikimedia\Rdbms\IDatabase;

/**
 * Database interaction for label suggestions.
 * TODO: This class refers to unresolved Wikidata IDs as "labels" but this is confusing since
 * elsewhere we use "label" to refer to the resolved text labels. We should find better terminology
 * here.
 */
class Repository implements LoggerAwareInterface {

	use LoggerAwareTrait;

	// Constants used for machine_vision_label.mvl_review
	const REVIEW_UNREVIEWED = 0;
	const REVIEW_ACCEPTED = 1;
	const REVIEW_REJECTED = -1;
	const REVIEW_SKIPPED = -2;

	private static $reviewStates = [ self::REVIEW_UNREVIEWED, self::REVIEW_ACCEPTED,
		self::REVIEW_REJECTED, self::REVIEW_SKIPPED ];

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
	 * @param array $wikidataIds A list of Wikidata ID such as 'Q123'
	 */
	public function insertLabels( $sha1, $providerName, array $wikidataIds ) {
		$providerId = $this->nameTableStore->acquireId( $providerName );
		$timestamp = $this->dbw->timestamp();
		foreach ( $wikidataIds as $wikidataId ) {
			$this->dbw->insert(
				'machine_vision_label',
				[
					'mvl_image_sha1' => $sha1,
					'mvl_wikidata_id' => $wikidataId,
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
			'mvl_wikidata_id',
			[ 'mvl_image_sha1' => $sha1 ],
			__METHOD__
		);
		$data = [];
		foreach ( $res as $row ) {
			$data[] = $row->mvl_wikidata_id;
		}
		return array_unique( $data );
	}

	/**
	 * Change the state of a label suggestion.
	 * @param string $sha1 Image SHA1
	 * @param string $label Image label (Wikidata item ID, including the Q prefix)
	 * @param int $state New state (one of the REVIEW_* constants).
	 * @return bool Success
	 */
	public function setLabelState( $sha1, $label, $state ) {
		$validStates = array_diff( self::$reviewStates, [ self::REVIEW_UNREVIEWED ] );
		if ( !in_array( $state, $validStates, true ) ) {
			$validStates = implode( ', ', $validStates );
			throw new InvalidArgumentException( "Invalid state $state (must be one of $validStates)" );
		}

		$this->dbw->update(
			'machine_vision_label',
			[ 'mvl_review' => $state ],
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

}
