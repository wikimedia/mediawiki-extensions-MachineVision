<?php

namespace MediaWiki\Extension\MachineVision\Handler;

use Google\Cloud\Vision\V1\Feature\Type;
use Google\Cloud\Vision\V1\ImageAnnotatorClient;
use LocalFile;
use MediaWiki\Extension\MachineVision\Repository;
use MediaWiki\Extension\MachineVision\LabelSuggestion;
use RepoGroup;

class GoogleCloudVisionHandler extends WikidataIdHandler {

	/** @var ImageAnnotatorClient */
	// @phan-suppress-next-line PhanUndeclaredTypeProperty
	private $client;

	/** @var RepoGroup */
	private $repoGroup;

	/** @var bool */
	private $sendFileContents;

	/**
	 * @param ImageAnnotatorClient $client
	 * @param Repository $repository
	 * @param RepoGroup $repoGroup
	 * @param WikidataDepictsSetter $depictsSetter
	 * @param LabelResolver $labelResolver
	 * @param bool $sendFileContents
	 * @suppress PhanUndeclaredTypeParameter
	 */
	public function __construct(
		ImageAnnotatorClient $client,
		Repository $repository,
		RepoGroup $repoGroup,
		WikidataDepictsSetter $depictsSetter,
		LabelResolver $labelResolver,
		$sendFileContents
	) {
		parent::__construct( $repository, $depictsSetter, $labelResolver );
		$this->client = $client;
		$this->repoGroup = $repoGroup;
		$this->sendFileContents = $sendFileContents;
	}

	/**
	 * Handle a new upload, after the core handling has been completed.
	 * Retrieves machine vision metadata about the image and stores it.
	 * @param string $provider provider name
	 * @param LocalFile $file
	 * @throws \Google\ApiCore\ApiException
	 * @suppress PhanUndeclaredTypeThrowsType,PhanUndeclaredClassMethod,PhanUndeclaredClassConstant
	 */
	public function handleUploadComplete( $provider, LocalFile $file ) {
		$payload = $this->sendFileContents ? $this->getContents( $file ) : $this->getUrl( $file );
		$features = [ Type::LABEL_DETECTION, Type::SAFE_SEARCH_DETECTION ];
		$annotations = $this->client->annotateImage( $payload, $features );
		$suggestions = [];
		foreach ( $annotations->getLabelAnnotations() as $label ) {
			$freebaseId = $label->getMid();
			$score = $label->getScore();
			$mappedWikidataIds = $this->getRepository()->getMappedWikidataIds( $freebaseId );
			$newSuggestions = array_map( function ( $mappedId ) use ( $score ) {
				return new LabelSuggestion( $mappedId, $score );
			}, $mappedWikidataIds );
			$suggestions = array_merge( $suggestions, $newSuggestions );
		}
		$this->getRepository()->insertLabels( $file->getSha1(), $provider,
			$file->getUser( 'id' ), $suggestions );

		$safeSearchAnnotations = $annotations->getSafeSearchAnnotation();
		$this->getRepository()->insertSafeSearchAnnotations(
			$file->getSha1(),
			$safeSearchAnnotations->getAdult(),
			$safeSearchAnnotations->getSpoof(),
			$safeSearchAnnotations->getMedical(),
			$safeSearchAnnotations->getViolence(),
			$safeSearchAnnotations->getRacy()
		);
	}

	private function getUrl( LocalFile $file ) {
		return wfExpandUrl( $file->getFullUrl(), PROTO_HTTPS );
	}

	private function getContents( LocalFile $file ) {
		$fileBackend = $this->repoGroup->getLocalRepo()->getBackend();
		return $fileBackend->getFileContents( [ 'src' => $file->getPath() ] );
	}

}
