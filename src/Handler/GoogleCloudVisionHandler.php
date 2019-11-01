<?php

namespace MediaWiki\Extension\MachineVision\Handler;

use Google\Auth\Credentials\ServiceAccountCredentials;
use LocalFile;
use MediaWiki\Extension\MachineVision\Repository;
use MediaWiki\Extension\MachineVision\LabelSuggestion;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Logger\LoggerFactory;
use MWHttpRequest;
use RepoGroup;
use Status;
use Throwable;

class GoogleCloudVisionHandler extends WikidataIdHandler {

	/** @var ServiceAccountCredentials */
	// @phan-suppress-next-line PhanUndeclaredTypeProperty
	private $credentials;

	/** @var HttpRequestFactory */
	private $httpRequestFactory;

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
	 * HTTP proxy
	 * @var string
	 */
	private $proxy;

	/**
	 * Maximum requests per minute to send to the Google Cloud Vision API when running the label
	 * fetcher script.
	 * @var int
	 */
	private $maxRequestsPerMinute;

	const SAFE_SEARCH_LIKELIHOODS = [
		'UNKNOWN' => 0,
		'VERY_UNLIKELY' => 1,
		'UNLIKELY' => 2,
		'POSSIBLE' => 3,
		'LIKELY' => 4,
		'VERY_LIKELY' => 5,
	];

	/**
	 * @param ServiceAccountCredentials $credentials
	 * @param HttpRequestFactory $httpRequestFactory
	 * @param Repository $repository
	 * @param RepoGroup $repoGroup
	 * @param WikidataDepictsSetter $depictsSetter
	 * @param LabelResolver $labelResolver
	 * @param bool $sendFileContents
	 * @param array $safeSearchLimits
	 * @param string|bool $proxy
	 * @param int $maxRequestsPerMinute
	 * @suppress PhanUndeclaredTypeParameter
	 */
	public function __construct(
		ServiceAccountCredentials $credentials,
		HttpRequestFactory $httpRequestFactory,
		Repository $repository,
		RepoGroup $repoGroup,
		WikidataDepictsSetter $depictsSetter,
		LabelResolver $labelResolver,
		$sendFileContents,
		$safeSearchLimits,
		$proxy = false,
		$maxRequestsPerMinute = 0
	) {
		parent::__construct( $repository, $depictsSetter, $labelResolver );
		$this->credentials = $credentials;
		$this->httpRequestFactory = $httpRequestFactory;
		$this->repoGroup = $repoGroup;
		$this->sendFileContents = $sendFileContents;
		$this->safeSearchLimits = $safeSearchLimits;
		$this->proxy = $proxy;
		$this->maxRequestsPerMinute = $maxRequestsPerMinute;

		$this->setLogger( LoggerFactory::getInstance( 'machinevision' ) );
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
	 */
	public function handleUploadComplete( $provider, LocalFile $file ) {
		// FIXME: move this to a Job (via JobQueueGroup) to allow for retries and to avoid
		// tying up apache threads if the external service is slow. Jobs can afford higher
		// timeout tolerance as well. This seems a bit heavy-weight for a DeferrableUpdate.

		$annotationRequest = $this->getAnnotationRequest( $file );
		$status = $annotationRequest->execute();

		if ( !$status->isOK() ) {
			$errors = $status->getErrorsByType( 'error' );
			$this->logger->warning( Status::wrap( $status )->getWikiText( false, false, 'en' ),
				[
					'error' => $errors,
					'caller' => __METHOD__,
					'content' => $annotationRequest->getContent()
				]
			);
			return;
		}

		$responseBody = json_decode( $annotationRequest->getContent(), true );
		$responses = $responseBody['responses'][0];
		$labelAnnotations = $responses['labelAnnotations'];
		$safeSearchAnnotation = $responses['safeSearchAnnotation'];

		$suggestions = [];
		foreach ( $labelAnnotations as $label ) {
			$freebaseId = $label['mid'];
			$score = $label['score'];
			$mappedWikidataIds = $this->getRepository()->getMappedWikidataIds( $freebaseId );
			$newSuggestions = array_map( function ( $mappedId ) use ( $score ) {
				return new LabelSuggestion( $mappedId, $score );
			}, $mappedWikidataIds );
			$suggestions = array_merge( $suggestions, $newSuggestions );
		}

		$adult = self::SAFE_SEARCH_LIKELIHOODS[$safeSearchAnnotation['adult']];
		$spoof = self::SAFE_SEARCH_LIKELIHOODS[$safeSearchAnnotation['spoof']];
		$medical = self::SAFE_SEARCH_LIKELIHOODS[$safeSearchAnnotation['medical']];
		$violence = self::SAFE_SEARCH_LIKELIHOODS[$safeSearchAnnotation['violence']];
		$racy = self::SAFE_SEARCH_LIKELIHOODS[$safeSearchAnnotation['racy']];

		$initialState = Repository::REVIEW_UNREVIEWED;

		if (
			( array_key_exists( 'adult', $this->safeSearchLimits ) &&
				$adult >= $this->safeSearchLimits['adult'] ) ||
			( array_key_exists( 'spoof', $this->safeSearchLimits ) &&
				$spoof >= $this->safeSearchLimits['spoof'] ) ||
			( array_key_exists( 'medical', $this->safeSearchLimits ) &&
				$medical >= $this->safeSearchLimits['medical'] ) ||
			( array_key_exists( 'violence', $this->safeSearchLimits ) &&
				$violence >= $this->safeSearchLimits['violence'] ) ||
			( array_key_exists( 'racy', $this->safeSearchLimits ) &&
				$racy >= $this->safeSearchLimits['racy'] )
		) {
			$initialState = Repository::REVIEW_WITHHELD;
		}

		$this->getRepository()->insertLabels( $file->getSha1(), $provider,
			$file->getUser( 'id' ), $suggestions, $initialState );

		$this->getRepository()->insertSafeSearchAnnotations( $file->getSha1(), $adult, $spoof,
			$medical, $violence, $racy
		);
	}

	/**
	 * @param LocalFile $file
	 * @return MWHttpRequest
	 * @suppress PhanUndeclaredClassMethod
	 */
	private function getAnnotationRequest( LocalFile $file ): MWHttpRequest {
		// Avoid getFileContents() since files can be large (and there can be more than one
		// handler, possible doing something like this one). Using resource handles would
		// require some FileBackend changes and would still hit similar code in
		// https://github.com/googleapis/google-cloud-php/blob/83ae284c025f6e93b9ce835b987932c425b5a9de/Vision/src/VisionHelpersTrait.php#L111
		// so this should only stick to URLs unless there is some unusual reason not to.
		// It also protects agains mistakes with private wiki config since the usual CDN
		// layers and auth code would be hit by Google accessing the public file URL.
		$requestBody = [
			'requests' => [
				'image' => $this->sendFileContents ?
					[ 'content' => base64_encode( $this->getContents( $file ) ) ] :
					// TODO: check the file size and fall back to a thumb URL if the original
					//  image size is too large (>10485760 bytes)
					[ 'source' => [ 'image_uri' => $this->getUrl( $file ) ] ],
				'features' => [
					[ 'type' => 'LABEL_DETECTION' ],
					[ 'type' => 'SAFE_SEARCH_DETECTION' ],
				],
			],
		];
		$options = [
			'method' => 'POST',
			'postData' => json_encode( $requestBody )
		];
		if ( $this->proxy ) {
			$options['proxy'] = $this->proxy;
		}
		$annotationRequest = $this->httpRequestFactory->create(
			'https://vision.googleapis.com/v1/images:annotate',
			$options
		);
		$token = $this->credentials->fetchAuthToken()['access_token'];
		$annotationRequest->setHeader( 'Content-Type', 'application/json; charset=utf-8' );
		$annotationRequest->setHeader( 'Authorization', "Bearer $token" );
		return $annotationRequest;
	}

	private function getUrl( LocalFile $file ) {
		return wfExpandUrl( $file->getFullUrl(), PROTO_HTTPS );
	}

	private function getContents( LocalFile $file ) {
		$fileBackend = $this->repoGroup->getLocalRepo()->getBackend();
		return $fileBackend->getFileContents( [ 'src' => $file->getPath() ] );
	}

}
