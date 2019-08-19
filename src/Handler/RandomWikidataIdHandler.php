<?php

namespace MediaWiki\Extension\MachineVision\Handler;

use LocalFile;
use MediaWiki\Extension\MachineVision\Client;
use MediaWiki\Extension\MachineVision\Repository;

class RandomWikidataIdHandler extends WikidataIdHandler {

	/** @var Client */
	private $client;

	/** @var string API URL. $1 will be replaced with the URL-encoded file title. */
	private $apiUrlTemplate;

	/**
	 * @param Client $client
	 * @param Repository $repository
	 * @param string $apiUrlTemplate
	 */
	public function __construct(
		Client $client,
		Repository $repository,
		$apiUrlTemplate
	) {
		parent::__construct( $repository );
		$this->client = $client;
		$this->apiUrlTemplate = $apiUrlTemplate;
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
			$this->getRepository()->insertLabels( $file->getSha1(), $provider, $wikidataIds );
		}
	}

}
