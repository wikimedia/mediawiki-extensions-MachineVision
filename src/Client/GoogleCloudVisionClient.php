<?php

namespace MediaWiki\Extension\MachineVision\Client;

use LocalFile;
use MediaWiki\Extension\MachineVision\LabelSuggestion;
use MediaWiki\Extension\MachineVision\Repository;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Logger\LoggerFactory;
use MWHttpRequest;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use RepoGroup;
use Status;

class GoogleCloudVisionClient implements LoggerAwareInterface {

	use LoggerAwareTrait;

	const MAX_IMAGE_SIZE = 10485760;

	const SAFE_SEARCH_LIKELIHOODS = [
		'UNKNOWN' => 0,
		'VERY_UNLIKELY' => 1,
		'UNLIKELY' => 2,
		'POSSIBLE' => 3,
		'LIKELY' => 4,
		'VERY_LIKELY' => 5,
	];

	/** @var GoogleOAuthClient */
	private $oAuthClient;

	/** @var HttpRequestFactory */
	private $httpRequestFactory;

	/** @var RepoGroup */
	private $repoGroup;

	/** @var Repository */
	private $repository;

	/** @var bool */
	private $sendFileContents;

	/** @var array */
	private $safeSearchLimits;

	/** @var string|bool */
	private $proxy;

	/**
	 * GoogleCloudVisionClient constructor.
	 * @param GoogleOAuthClient $oAuthClient
	 * @param HttpRequestFactory $httpRequestFactory
	 * @param RepoGroup $repoGroup
	 * @param Repository $repository
	 * @param bool $sendFileContents
	 * @param array $safeSearchLimits
	 * @param string|bool $proxy
	 */
	public function __construct(
		GoogleOAuthClient $oAuthClient,
		HttpRequestFactory $httpRequestFactory,
		RepoGroup $repoGroup,
		Repository $repository,
		bool $sendFileContents,
		array $safeSearchLimits,
		$proxy
	) {
		$this->oAuthClient = $oAuthClient;
		$this->httpRequestFactory = $httpRequestFactory;
		$this->repoGroup = $repoGroup;
		$this->repository = $repository;
		$this->sendFileContents = $sendFileContents;
		$this->safeSearchLimits = $safeSearchLimits;
		$this->proxy = $proxy;

		$this->setLogger( LoggerFactory::getInstance( 'machinevision' ) );
	}

	/**
	 * Fetch annotations from Google Cloud Vision.
	 * @param string $provider
	 * @param LocalFile $file
	 * @return bool
	 * @throws \MWException
	 */
	public function fetchAnnotations( string $provider, LocalFile $file ) {
		$annotationRequest = $this->getAnnotationRequest( $file );
		$status = $annotationRequest->execute();

		if ( !$status->isOK() ) {
			$errors = $status->getErrorsByType( 'error' );
			$this->logger->warning(
				Status::wrap( $status )->getMessage( false, false, 'en' )->serialize(),
				[
					'error' => $errors,
					'caller' => __METHOD__,
					'content' => $annotationRequest->getContent()
				]
			);
			return false;
		}

		$responseBody = json_decode( $annotationRequest->getContent(), true );
		$responses = $responseBody['responses'][0];
		$labelAnnotations = $responses['labelAnnotations'];
		$safeSearchAnnotation = $responses['safeSearchAnnotation'];

		$suggestions = [];
		foreach ( $labelAnnotations as $label ) {
			$freebaseId = $label['mid'];
			$score = $label['score'];
			$mappedWikidataIds = $this->repository->getMappedWikidataIds( $freebaseId );
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

		$this->repository->insertLabels( $file->getSha1(), $provider,
			$file->getUser( 'id' ), $suggestions, $initialState );

		$this->repository->insertSafeSearchAnnotations( $file->getSha1(), $adult, $spoof, $medical,
			$violence, $racy );
	}

	/**
	 * @param LocalFile $file
	 * @return MWHttpRequest
	 */
	private function getAnnotationRequest( LocalFile $file ): MWHttpRequest {
		$requestBody = [
			'requests' => [
				'image' => $this->sendFileContents ?
					[ 'content' => base64_encode( $this->getContents( $this->repoGroup, $file ) ) ]
					: [ 'source' => [ 'image_uri' => $this->getUrl( $file ) ] ],
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
		$token = $this->oAuthClient->fetchAuthToken()['access_token'];
		$annotationRequest->setHeader( 'Content-Type', 'application/json; charset=utf-8' );
		$annotationRequest->setHeader( 'Authorization', "Bearer $token" );
		return $annotationRequest;
	}

	private function getUrl( LocalFile $file ) {
		if ( $file->getSize() > self::MAX_IMAGE_SIZE ) {
			$thumb = $file->transform( [ 'width' => 1280, 'height' => 1024 ] );
			return wfExpandUrl( $thumb->getUrl(), PROTO_HTTPS );
		}
		return wfExpandUrl( $file->getFullUrl(), PROTO_HTTPS );
	}

	private function getContents( RepoGroup $repoGroup, LocalFile $file ) {
		$fileBackend = $repoGroup->getLocalRepo()->getBackend();
		return $fileBackend->getFileContents( [ 'src' => $file->getPath() ] );
	}

}
