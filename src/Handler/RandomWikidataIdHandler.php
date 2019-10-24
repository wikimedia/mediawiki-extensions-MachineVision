<?php

namespace MediaWiki\Extension\MachineVision\Handler;

use LocalFile;
use MediaWiki\Extension\MachineVision\Client;
use MediaWiki\Extension\MachineVision\Repository;
use MediaWiki\Extension\MachineVision\LabelSuggestion;
use Throwable;

// Legacy handler class for early-stage development and testing.
// TODO: REMOVE
class RandomWikidataIdHandler extends WikidataIdHandler {

	/** @var Client */
	private $client;

	/** @var string API URL. $1 will be replaced with the URL-encoded file title. */
	private $apiUrlTemplate;

	/**
	 * @param Client $client
	 * @param Repository $repository
	 * @param WikidataDepictsSetter $depictsSetter
	 * @param LabelResolver $labelResolver
	 * @param string $apiUrlTemplate
	 */
	public function __construct(
		Client $client,
		Repository $repository,
		WikidataDepictsSetter $depictsSetter,
		LabelResolver $labelResolver,
		$apiUrlTemplate
	) {
		parent::__construct( $repository, $depictsSetter, $labelResolver );
		$this->client = $client;
		$this->apiUrlTemplate = $apiUrlTemplate;
	}

	/** @inheritDoc */
	public function getMaxRequestsPerMinute(): int {
		return 0;
	}

	/** @inheritDoc */
	public function isTooManyRequestsError( Throwable $t ): bool {
		return false;
	}

	/**
	 * Handle a new upload, after the core handling has been completed.
	 * Retrieves machine vision metadata about the image and stores it.
	 * @param string $provider provider name
	 * @param LocalFile $file
	 */
	public function handleUploadComplete( $provider, LocalFile $file ) {
		$metadata = $this->client->getFileMetadata( $file, $this->apiUrlTemplate );
		$this->logger->debug( ( $metadata ? 'Retrieved' : 'No' ) . ' machine vision info for file', [
			'title' => $file->getTitle()->getPrefixedDBkey(),
			'data' => $metadata,
		] );
		if ( $metadata ) {
			$wikidataIds = array_column( $metadata['labels'], 'wikidata_id' );
			$suggestions = array_map( function ( $wikidataId ) {
				return new LabelSuggestion( $wikidataId );
			}, $wikidataIds );
			$this->getRepository()->insertLabels( $file->getSha1(), $provider,
				$file->getUser( 'id' ), $suggestions );
		}
	}

}
