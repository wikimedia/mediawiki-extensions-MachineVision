<?php

namespace MediaWiki\Extension\MachineVision\Handler;

use Html;
use IContextSource;
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

	/**
	 * Expose label suggestions in page info for transparency and developer convenience.
	 * @param IContextSource $context
	 * @param LocalFile $file
	 * @param array &$pageInfo
	 */
	public function handleInfoAction( IContextSource $context, LocalFile $file, array &$pageInfo ) {
		$labels = $this->repository->getLabels( $file->getSha1() );
		if ( $labels ) {
			// FIXME there's probably a nice way to build human-readable description of Q-items
			$labels = array_map( function ( $label ) {
				return Html::element( 'a', [
					'href' => 'https://www.wikidata.org/wiki/' . htmlentities( $label ),
				], $label );
			}, $labels );
			// TODO there should probably be a structured-data or similar header but this extension
			// is not the right place for that
			$pageInfo['header-properties'][] = [
				$context->msg( 'machinevision-pageinfo-field-suggested-labels' )->escaped(),
				$context->getLanguage()->commaList( $labels ),
			];
		}
	}

}
