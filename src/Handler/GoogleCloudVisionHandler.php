<?php

namespace MediaWiki\Extension\MachineVision\Handler;

use Google\Cloud\Vision\V1\Feature\Type;
use Google\Cloud\Vision\V1\ImageAnnotatorClient;
use LocalFile;
use MediaWiki\Extension\MachineVision\Repository;
use MediaWiki\Extension\MachineVision\LabelSuggestion;
use RepoGroup;
use Throwable;

class GoogleCloudVisionHandler extends WikidataIdHandler {

	/** @var ImageAnnotatorClient */
	// @phan-suppress-next-line PhanUndeclaredTypeProperty
	private $client;

	/** @var RepoGroup */
	private $repoGroup;

	/**
	 * Send file data if true, otherwise send the public URL.
	 * @var bool
	 */
	private $sendFileContents;

	/**
	 * Omit images with SafeSearch likelihood scores greater than or equal to the defined values.
	 * Available categories are 'adult,' 'spoof,' 'medical,' 'violent,' and 'racy.'
	 * Ex: [ 'adult' => 3, 'medical' => 3, 'violent' => 4, 'racy' => 4 ]
	 * @var array
	 */
	private $safeSearchLimits;

	/**
	 * Maximum requests per minute to send to the Google Cloud Vision API when running the label
	 * fetcher script.
	 * @var int
	 */
	private $maxRequestsPerMinute;

	/**
	 * @param ImageAnnotatorClient $client
	 * @param Repository $repository
	 * @param RepoGroup $repoGroup
	 * @param WikidataDepictsSetter $depictsSetter
	 * @param LabelResolver $labelResolver
	 * @param bool $sendFileContents
	 * @param array $safeSearchLimits
	 * @param int $maxRequestsPerMinute
	 * @suppress PhanUndeclaredTypeParameter
	 */
	public function __construct(
		ImageAnnotatorClient $client,
		Repository $repository,
		RepoGroup $repoGroup,
		WikidataDepictsSetter $depictsSetter,
		LabelResolver $labelResolver,
		$sendFileContents,
		$safeSearchLimits,
		$maxRequestsPerMinute = 0
	) {
		parent::__construct( $repository, $depictsSetter, $labelResolver );
		$this->client = $client;
		$this->repoGroup = $repoGroup;
		$this->sendFileContents = $sendFileContents;
		$this->safeSearchLimits = $safeSearchLimits;
		$this->maxRequestsPerMinute = $maxRequestsPerMinute;
	}

	/** @inheritDoc */
	public function getMaxRequestsPerMinute(): int {
		return $this->maxRequestsPerMinute;
	}

	/**
	 * See https://cloud.google.com/apis/design/errors for the API error format.
	 * @inheritDoc
	 */
	public function isTooManyRequestsError( Throwable $t ): bool {
		return $t->getCode() === 429;
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
		$initialState = Repository::REVIEW_UNREVIEWED;
		$safeSearchAnnotations = $annotations->getSafeSearchAnnotation();

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

		if (
			( array_key_exists( 'adult', $this->safeSearchLimits ) &&
			  $safeSearchAnnotations->getAdult() >= $this->safeSearchLimits['adult'] ) ||
			( array_key_exists( 'spoof', $this->safeSearchLimits ) &&
			  $safeSearchAnnotations->getSpoof() >= $this->safeSearchLimits['spoof'] ) ||
			( array_key_exists( 'medical', $this->safeSearchLimits ) &&
			  $safeSearchAnnotations->getMedical() >= $this->safeSearchLimits['medical'] ) ||
			( array_key_exists( 'violence', $this->safeSearchLimits ) &&
			  $safeSearchAnnotations->getViolence() >= $this->safeSearchLimits['violence'] ) ||
			( array_key_exists( 'racy', $this->safeSearchLimits ) &&
			  $safeSearchAnnotations->getRacy() >= $this->safeSearchLimits['racy'] )
		) {
			$initialState = Repository::REVIEW_WITHHELD;
		}

		$this->getRepository()->insertLabels( $file->getSha1(), $provider,
			$file->getUser( 'id' ), $suggestions, $initialState );

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
