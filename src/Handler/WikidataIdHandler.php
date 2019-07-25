<?php

namespace MediaWiki\Extension\MachineVision\Handler;

use LocalFile;
use MediaWiki\Extension\MachineVision\Client;
use MediaWiki\Extension\MachineVision\Repository;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

class WikidataIdHandler implements Handler {

	use LoggerAwareTrait;

	/** @var Client */
	private $client;

	/** @var Repository */
	private $repository;

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
		$this->client = $client;
		$this->repository = $repository;
		$this->apiUrlTemplate = $apiUrlTemplate;

		$this->setLogger( new NullLogger() );
	}

	/**
	 * Handle a new upload, after the core handling has been completed.
	 * Retrieves machine vision metadata about the image and stores it.
	 * @param LocalFile $file
	 */
	public function handleUploadComplete( LocalFile $file ) {
		$metadata = $this->client->getFileMetadata( $file, $this->apiUrlTemplate );
		$this->logger->debug( ( $metadata ? 'Retrieved' : 'No' ) . ' machine vision info for file', [
			'title' => $file->getTitle()->getPrefixedDBkey(),
			'data' => $metadata,
		] );
		if ( $metadata ) {
			$providerName = $metadata['provider'];
			$wikidataIds = array_column( $metadata['labels'], 'wikidata_id' );
			$this->repository->insertLabels( $file->getSha1(), $providerName, $wikidataIds );
		}
	}

}
