<?php

namespace MediaWiki\Extension\MachineVision\Handler;

use Google\Cloud\Vision\V1\ImageAnnotatorClient;
use LocalFile;
use MediaWiki\Extension\MachineVision\Repository;
use MediaWiki\Extension\MachineVision\LabelSuggestion;
use RepoGroup;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Services\Lookup\EntityLookup;

class GoogleCloudVisionHandler extends WikidataIdHandler {

	/** @var ImageAnnotatorClient */
	// @phan-suppress-next-line PhanUndeclaredTypeProperty
	private $client;

	/** @var RepoGroup */
	private $repoGroup;

	/** @var EntityLookup */
	private $entityLookup;

	/** @var bool */
	private $sendFileContents;

	/**
	 * @param ImageAnnotatorClient $client
	 * @param Repository $repository
	 * @param RepoGroup $repoGroup
	 * @param WikidataDepictsSetter $depictsSetter
	 * @param LabelResolver $labelResolver
	 * @param EntityLookup $entityLookup
	 * @param bool $sendFileContents
	 * @suppress PhanUndeclaredTypeParameter
	 */
	public function __construct(
		ImageAnnotatorClient $client,
		Repository $repository,
		RepoGroup $repoGroup,
		WikidataDepictsSetter $depictsSetter,
		LabelResolver $labelResolver,
		EntityLookup $entityLookup,
		$sendFileContents
	) {
		parent::__construct( $repository, $depictsSetter, $labelResolver );
		$this->client = $client;
		$this->repoGroup = $repoGroup;
		$this->entityLookup = $entityLookup;
		$this->sendFileContents = $sendFileContents;
	}

	/**
	 * Handle a new upload, after the core handling has been completed.
	 * Retrieves machine vision metadata about the image and stores it.
	 * @param string $provider provider name
	 * @param LocalFile $file
	 * @throws \Google\ApiCore\ApiException
	 * @suppress PhanUndeclaredTypeThrowsType,PhanUndeclaredClassMethod
	 */
	public function handleUploadComplete( $provider, LocalFile $file ) {
		$payload = $this->sendFileContents ? $this->getContents( $file ) : $this->getUrl( $file );
		$labels = $this->client->labelDetection( $payload )->getLabelAnnotations();
		$suggestions = [];
		foreach ( $labels as $label ) {
			$freebaseId = $label->getMid();
			$score = $label->getScore();
			$mappedWikidataIds = $this->getRepository()->getMappedWikidataIds( $freebaseId );
			$items = [];
			$newSuggestions = [];
			// Look up the entity associated with the provided ID.
			// Redirects will be resolved during lookup, and values returning null will be ignored.
			// TODO: Update the mappings table when redirects are resolved.
			// This can be detected when item->getId() has a different value than the ID provided.
			foreach ( $mappedWikidataIds as $mappedId ) {
				$item = $this->entityLookup->getEntity( new ItemId( $mappedId ) );
				if ( $item ) {
					$items[] = $item;
				}
			}
			foreach ( $items as $item ) {
				$itemId = $item->getId();
				if ( $itemId ) {
					$newSuggestions[] = new LabelSuggestion( $itemId, $score );
				}
			}
			$suggestions = array_merge( $suggestions, $newSuggestions );
		}
		$this->getRepository()->insertLabels( $file->getSha1(), $provider,
			$file->getUser( 'id' ), $suggestions );
	}

	private function getUrl( LocalFile $file ) {
		return wfExpandUrl( $file->getFullUrl(), PROTO_HTTPS );
	}

	private function getContents( LocalFile $file ) {
		$fileBackend = $this->repoGroup->getLocalRepo()->getBackend();
		return $fileBackend->getFileContents( [ 'src' => $file->getPath() ] );
	}

}
