<?php

namespace MediaWiki\Extension\MachineVision\Client;

use EchoEvent;
use ExtensionRegistry;
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

	/**
	 * Client for interacting with the Google auth API.
	 * @var GoogleOAuthClient
	 */
	private $oAuthClient;

	/** @var HttpRequestFactory */
	private $httpRequestFactory;

	/** @var RepoGroup */
	private $repoGroup;

	/**
	 * Label suggestion repository object.
	 * @var Repository
	 */
	private $repository;

	/**
	 * Whether to post the full file contents rather than the file's public URL. Intended for
	 * development and testing only.
	 * @var bool
	 */
	private $sendFileContents;

	/**
	 * Array of SafeSearch limits at which an image should be excluded from the "popular" view
	 * in Special:SuggestedTags.
	 * @var array
	 */
	private $safeSearchLimits;

	/**
	 * Outgoing HTTP proxy.
	 * @var string|bool
	 */
	private $proxy;

	/**
	 * Array of Wikidata IDs indicating that the image should be withheld from appearing in
	 * Special:SuggestedTags.
	 * @var array
	 */
	private $withholdImageList;

	/**
	 * Array of Wikidata IDs that should not be used as label suggestions but do not block the
	 * image from appearing in Special:SuggestedTags.
	 * @var array
	 */
	private $wikidataIdBlocklist;

	/**
	 * GoogleCloudVisionClient constructor.
	 * @param GoogleOAuthClient $oAuthClient
	 * @param HttpRequestFactory $httpRequestFactory
	 * @param RepoGroup $repoGroup
	 * @param Repository $repository
	 * @param bool $sendFileContents
	 * @param array $safeSearchLimits
	 * @param string|bool $proxy
	 * @param array $withholdImageList
	 * @param array $wikidataIdBlocklist
	 */
	public function __construct(
		GoogleOAuthClient $oAuthClient,
		HttpRequestFactory $httpRequestFactory,
		RepoGroup $repoGroup,
		Repository $repository,
		bool $sendFileContents,
		array $safeSearchLimits,
		$proxy,
		array $withholdImageList,
		array $wikidataIdBlocklist
	) {
		$this->oAuthClient = $oAuthClient;
		$this->httpRequestFactory = $httpRequestFactory;
		$this->repoGroup = $repoGroup;
		$this->repository = $repository;
		$this->sendFileContents = $sendFileContents;
		$this->safeSearchLimits = $safeSearchLimits;
		$this->proxy = $proxy;
		$this->withholdImageList = $withholdImageList;
		$this->wikidataIdBlocklist = $wikidataIdBlocklist;

		$this->setLogger( LoggerFactory::getInstance( 'machinevision' ) );
	}

	/**
	 * Fetch annotations from Google Cloud Vision.
	 * @param string $provider
	 * @param LocalFile $file
	 * @param int $priority priority value between -128 & 127
	 * @param bool $notify true if a notification should be sent to the uploader on success
	 * @return void
	 * @throws \MWException
	 */
	public function fetchAnnotations( string $provider, LocalFile $file, int $priority = 0, bool $notify = false ) {
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
			return;
		}

		$responseBody = json_decode( $annotationRequest->getContent(), true );
		// @phan-suppress-next-line PhanTypeArraySuspiciousNullable
		$response = $responseBody['responses'][0];

		if ( !array_key_exists( 'labelAnnotations', $response )
			|| !array_key_exists( 'safeSearchAnnotation', $response ) ) {
			$this->logger->warning(
				'labelAnnotations or safeSearchAnnotation key not found in response',
				[
					'caller' => __METHOD__,
					'content' => $annotationRequest->getContent()
				]
			);
			return;
		}

		$labelAnnotations = $response['labelAnnotations'];
		$safeSearchAnnotation = $response['safeSearchAnnotation'];

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

		$initialState = self::getInitialLabelState( $this->withholdImageList, $suggestions,
			$this->safeSearchLimits, $adult, $spoof, $medical, $violence, $racy );

		$filteredSuggestions = $this->filterIdBlocklist( $suggestions );
		if ( count( $filteredSuggestions ) < 1 ) {
			$this->logger->info(
				'No labels remain after blocklist filtering',
				[
					'caller' => __METHOD__,
					'content' => $annotationRequest->getContent()
				]
			);
			return;
		}

		// If previous versions of this file exist, grab the oldest one so we
		// can add this image to the original uploader's personal uploads.
		$history = $file->getHistory();
		$fileForUser = !$history ? $file : $history[ count( $history ) - 1 ];

		$labelsCount = $this->repository->insertLabels(
			$file->getSha1(),
			$provider,
			$fileForUser->getUser( 'id' ),
			$filteredSuggestions,
			$priority,
			$initialState
		);

		$this->repository->insertSafeSearchAnnotations(
			$file->getSha1(),
			$adult,
			$spoof,
			$medical,
			$violence,
			$racy
		);

		if ( $notify && $labelsCount > 0 ) {
			$this->createEchoNotification( $file );
		}
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
			$options,
			__METHOD__
		);
		// @phan-suppress-next-line PhanTypeArraySuspiciousNullable
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

	private function createEchoNotification( LocalFile $file ) {
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'Echo' ) ) {
			return;
		}

		EchoEvent::create( [
			'type' => 'machinevision-suggestions-ready',
			'title' => \SpecialPage::getTitleFor( 'SuggestedTags' ),
			'agent' => $file->getUser( 'object' )
		] );
	}

	/**
	 * @param array $withholdImageList list of Wikidata IDs indicating the image should be withheld
	 * @param array $labelSuggestions array of LabelSuggestion objects
	 * @param array $safeSearchLimits
	 * @param int $adult
	 * @param int $spoof
	 * @param int $medical
	 * @param int $violence
	 * @param int $racy
	 * @return int
	 */
	private static function getInitialLabelState(
		array $withholdImageList,
		array $labelSuggestions,
		array $safeSearchLimits,
		int $adult,
		int $spoof,
		int $medical,
		int $violence,
		int $racy
	): int {
		if ( self::hasWithholdAllTag( $withholdImageList, $labelSuggestions ) ) {
			return Repository::REVIEW_WITHHELD_ALL;
		} elseif ( self::imageFailsSafeSearch( $safeSearchLimits, $adult, $spoof, $medical,
			$violence, $racy ) ) {
			return Repository::REVIEW_WITHHELD_POPULAR;
		} else {
			return Repository::REVIEW_UNREVIEWED;
		}
	}

	/**
	 * @param array $withholdImageList list of Wikidata IDs indicating the image should be withheld
	 * @param array $labelSuggestions array of LabelSuggestion objects
	 * @return bool
	 */
	private static function hasWithholdAllTag( array $withholdImageList, array $labelSuggestions ):
		bool {
		$wikidataIds = array_map( function ( LabelSuggestion $suggestion ) {
			return $suggestion->getWikidataId();
		}, $labelSuggestions );
		return (bool)array_intersect( $withholdImageList, $wikidataIds );
	}

	/**
	 * @param array $safeSearchLimits
	 * @param int $adult
	 * @param int $spoof
	 * @param int $medical
	 * @param int $violence
	 * @param int $racy
	 * @return bool
	 */
	private static function imageFailsSafeSearch( array $safeSearchLimits, int $adult, int $spoof,
		int $medical, int $violence, int $racy ): bool {
		return ( array_key_exists( 'adult', $safeSearchLimits ) &&
			$adult >= $safeSearchLimits['adult'] ) ||
		( array_key_exists( 'spoof', $safeSearchLimits ) &&
			$spoof >= $safeSearchLimits['spoof'] ) ||
		( array_key_exists( 'medical', $safeSearchLimits ) &&
			$medical >= $safeSearchLimits['medical'] ) ||
		( array_key_exists( 'violence', $safeSearchLimits ) &&
			$violence >= $safeSearchLimits['violence'] ) ||
		( array_key_exists( 'racy', $safeSearchLimits ) && $racy >= $safeSearchLimits['racy'] );
	}

	/**
	 * Return filtered array removing blocklisted Q ids
	 * @param array $suggestions array of LabelSuggestions filter
	 * @return array Filtered array removing blocklisted Q ids
	 */
	protected function filterIdBlocklist( array $suggestions ) {
		return array_filter( $suggestions, function ( $suggestion ) {
			return !in_array( $suggestion->getWikidataId(), $this->wikidataIdBlocklist, true );
		} );
	}

}
