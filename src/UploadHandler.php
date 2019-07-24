<?php

namespace MediaWiki\Extension\MachineVision;

use DeferredUpdates;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use UploadBase;

class UploadHandler implements LoggerAwareInterface {

	use LoggerAwareTrait;

	/** @var Client */
	private $client;

	/** @var Repository */
	private $repository;

	/**
	 * @param Client $client
	 * @param Repository $repository
	 */
	public function __construct(
		Client $client,
		Repository $repository
	) {
		$this->client = $client;
		$this->repository = $repository;

		$this->setLogger( new NullLogger() );
	}

	/**
	 * Handle a new upload, after the core handling has been completed.
	 * Retrieves machine vision metadata about the image and stores it.
	 * @param UploadBase $uploadBase
	 */
	public function handle( UploadBase $uploadBase ) {
		$file = $uploadBase->getLocalFile();
		DeferredUpdates::addCallableUpdate( function () use ( $file ) {
			$metadata = $this->client->getFileMetadata( $file );
			$this->logger->debug( ( $metadata ? 'Retrieved' : 'No' ) . ' machine vision info for file', [
				'title' => $file->getTitle()->getPrefixedDBkey(),
				'data' => $metadata,
			] );
			if ( $metadata ) {
				$providerName = $metadata['provider'];
				$wikidataIds = array_column( $metadata['labels'], 'wikidata_id' );
				$this->repository->insertLabels( $file->getSha1(), $providerName, $wikidataIds );
			}
		} );
	}

}
