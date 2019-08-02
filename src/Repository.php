<?php

namespace MediaWiki\Extension\MachineVision;

use MediaWiki\Storage\NameTableStore;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Wikimedia\Rdbms\IDatabase;

class Repository implements LoggerAwareInterface {

	// Constants used for machine_vision_label.mvl_review
	const REVIEW_UNREVIEWED = 0;
	const REVIEW_ACCEPTED = 1;
	const REVIEW_REJECTED = -1;
	const REVIEW_SKIPPED = -2;

	use LoggerAwareTrait;

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
		$data = array_map( function ( $wikidataId ) use ( $sha1, $providerId, $timestamp ) {
			return [
				'mvl_image_sha1' => $sha1,
				'mvl_provider_id' => $providerId,
				'mvl_wikidata_id' => $wikidataId,
				'mvl_timestamp' => $timestamp,
			];
		}, $wikidataIds );
		$this->dbw->insert( 'machine_vision_label', $data, __METHOD__ );
	}

	/**
	 * Get the suggested labels of an image.
	 * @param string $sha1 Image SHA1
	 * @return string[] List of Wikidata item IDs (including the Q prefix)
	 */
	public function getLabels( $sha1 ) {
		$res = $this->dbr->select(
			'machine_vision_label',
			[ 'mvl_provider_id', 'mvl_wikidata_id' ],
			[ 'mvl_image_sha1' => $sha1 ],
			__METHOD__
		);
		$data = [];
		foreach ( $res as $row ) {
			// We assume callers won't care about the providers.
			$data[] = $row->mvl_wikidata_id;
		}
		return array_unique( $data );
	}

}
