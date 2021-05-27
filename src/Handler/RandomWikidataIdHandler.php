<?php

namespace MediaWiki\Extension\MachineVision\Handler;

use File;
use LocalFile;
use MediaWiki\Extension\MachineVision\Client\RandomWikidataIdClient;
use MediaWiki\Extension\MachineVision\LabelSuggestion;
use MediaWiki\Extension\MachineVision\Repository;
use Throwable;

// Legacy handler class for early-stage development and testing.
// TODO: REMOVE
class RandomWikidataIdHandler extends WikidataIdHandler {

	/** @var RandomWikidataIdClient */
	private $client;

	/** @var string API URL. $1 will be replaced with the URL-encoded file title. */
	private $apiUrlTemplate;

	/**
	 * @param RandomWikidataIdClient $client
	 * @param Repository $repository
	 * @param LabelResolver $labelResolver
	 * @param string $apiUrlTemplate
	 */
	public function __construct(
		RandomWikidataIdClient $client,
		Repository $repository,
		LabelResolver $labelResolver,
		$apiUrlTemplate
	) {
		parent::__construct( $repository, $labelResolver );
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
	 * Retrieves machine vision metadata about the image and stores it.
	 * @param string $provider provider name
	 * @param LocalFile $file
	 * @param int $priority priority value between -128 & 127
	 */
	public function requestAnnotations( string $provider, LocalFile $file, int $priority = 0 ): void {
		$metadata = $this->client->getFileMetadata( $file, $this->apiUrlTemplate );
		$this->logger->debug( ( $metadata ? 'Retrieved' : 'No' ) . ' machine vision info for file', [
			'title' => $file->getTitle()->getPrefixedDBkey(),
			'data' => $metadata,
		] );
		if ( $metadata ) {
			$wikidataIds = array_column( $metadata['labels'], 'wikidata_id' );
			$suggestions = array_map( static function ( $wikidataId ) {
				return new LabelSuggestion( $wikidataId );
			}, $wikidataIds );
			$this->getRepository()->insertLabels( $file->getSha1(), $provider,
				$file->getUploader( File::RAW ), $suggestions, $priority );
		}
	}

}
