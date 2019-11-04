<?php

namespace MediaWiki\Extension\MachineVision\Job;

use Google\Auth\Credentials\ServiceAccountCredentials;
use Job;
use LocalFile;
use MediaWiki\Extension\MachineVision\LabelSuggestion;
use MediaWiki\Extension\MachineVision\Repository;
use MediaWiki\Extension\MachineVision\Services;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MWHttpRequest;
use RepoGroup;
use Status;
use Title;

class FetchGoogleCloudVisionAnnotationsJob extends Job {

	const SAFE_SEARCH_LIKELIHOODS = [
		'UNKNOWN' => 0,
		'VERY_UNLIKELY' => 1,
		'UNLIKELY' => 2,
		'POSSIBLE' => 3,
		'LIKELY' => 4,
		'VERY_LIKELY' => 5,
	];

	/** @return bool */
	public function run(): bool {
		$title = $this->params['title'];
		$namespace = $this->params['namespace'];
		$provider = $this->params['provider'];
		$sendFileContents = $this->params['sendFileContents'];
		$safeSearchLimits = $this->params['safeSearchLimits'];
		$proxy = $this->params['proxy'];

		$services = MediaWikiServices::getInstance();
		$httpRequestFactory = $services->getHttpRequestFactory();
		$repoGroup = $services->getRepoGroup();
		$file = $repoGroup->getLocalRepo()->findFile( Title::newFromText( $title, $namespace ) );

		$extensionServices = new Services( $services );
		$repository = $extensionServices->getRepository();
		$credentials = $extensionServices->getGoogleServiceAccountCredentials();

		$logger = LoggerFactory::getInstance( 'machinevision' );

		// @phan-suppress-next-line PhanTypeMismatchArgument
		$annotationRequest = $this->getAnnotationRequest( $file, $credentials,
			$httpRequestFactory, $repoGroup, $sendFileContents, $proxy );
		$status = $annotationRequest->execute();

		if ( !$status->isOK() ) {
			$errors = $status->getErrorsByType( 'error' );
			$logger->warning(
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
			$mappedWikidataIds = $repository->getMappedWikidataIds( $freebaseId );
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
			( array_key_exists( 'adult', $safeSearchLimits ) &&
				$adult >= $safeSearchLimits['adult'] ) ||
			( array_key_exists( 'spoof', $safeSearchLimits ) &&
				$spoof >= $safeSearchLimits['spoof'] ) ||
			( array_key_exists( 'medical', $safeSearchLimits ) &&
				$medical >= $safeSearchLimits['medical'] ) ||
			( array_key_exists( 'violence', $safeSearchLimits ) &&
				$violence >= $safeSearchLimits['violence'] ) ||
			( array_key_exists( 'racy', $safeSearchLimits ) &&
				$racy >= $safeSearchLimits['racy'] )
		) {
			$initialState = Repository::REVIEW_WITHHELD;
		}

		$repository->insertLabels( $file->getSha1(), $provider,
			$file->getUser( 'id' ), $suggestions, $initialState );

		$repository->insertSafeSearchAnnotations( $file->getSha1(), $adult, $spoof, $medical,
			$violence, $racy
		);

		return true;
	}

	/**
	 * @param LocalFile $file
	 * @param ServiceAccountCredentials $credentials
	 * @param HttpRequestFactory $httpRequestFactory
	 * @param RepoGroup $repoGroup
	 * @param bool $sendFileContents
	 * @param string|bool $proxy
	 * @return MWHttpRequest
	 * @suppress PhanUndeclaredClassMethod,PhanUndeclaredTypeParameter
	 */
	private function getAnnotationRequest(
		LocalFile $file,
		ServiceAccountCredentials $credentials,
		HttpRequestFactory $httpRequestFactory,
		RepoGroup $repoGroup,
		bool $sendFileContents,
		$proxy
	): MWHttpRequest {
		$requestBody = [
			'requests' => [
				'image' => $sendFileContents ?
					[ 'content' => base64_encode( $this->getContents( $repoGroup, $file ) ) ] :
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
		if ( $proxy ) {
			$options['proxy'] = $proxy;
		}
		$annotationRequest = $httpRequestFactory->create(
			'https://vision.googleapis.com/v1/images:annotate',
			$options
		);
		$token = $credentials->fetchAuthToken()['access_token'];
		$annotationRequest->setHeader( 'Content-Type', 'application/json; charset=utf-8' );
		$annotationRequest->setHeader( 'Authorization', "Bearer $token" );
		return $annotationRequest;
	}

	private function getUrl( LocalFile $file ) {
		return wfExpandUrl( $file->getFullUrl(), PROTO_HTTPS );
	}

	private function getContents( RepoGroup $repoGroup, LocalFile $file ) {
		$fileBackend = $repoGroup->getLocalRepo()->getBackend();
		return $fileBackend->getFileContents( [ 'src' => $file->getPath() ] );
	}

}
