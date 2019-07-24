<?php

namespace MediaWiki\Extension\MachineVision;

use File;
use MediaWiki\Http\HttpRequestFactory;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

class Client implements LoggerAwareInterface {

	use LoggerAwareTrait;

	/** @var HttpRequestFactory */
	private $httpRequestFactory;

	/** @var string */
	private $apiUrl;

	/** @var string */
	private $userAgent;

	/**
	 * @param HttpRequestFactory $httpRequestFactory
	 * @param string $apiUrl Labeling API URL. $1 will be replaced with the URL-encoded file title.
	 * @param string $userAgent Request UA.
	 */
	public function __construct(
		HttpRequestFactory $httpRequestFactory,
		$apiUrl,
		$userAgent
	) {
		$this->httpRequestFactory = $httpRequestFactory;
		$this->apiUrl = $apiUrl;
		$this->userAgent = $userAgent;

		$this->setLogger( new NullLogger() );
	}

	/**
	 * Fetch file
	 * @param File $file
	 * @return array|null Arbitrary metadata returned by the service
	 */
	public function getFileMetadata( File $file ) {
		$titleText = $file->getTitle()->getPrefixedDBkey();
		$url = str_replace( '$1', urlencode( $titleText ), $this->apiUrl );
		$response = $this->httpRequestFactory->get( $url, [
			'userAgent' => $this->userAgent,
		], __METHOD__ );
		if ( $response !== null ) {
			$json = json_decode( $response, true );
			if ( $json === null ) {
				$this->logger->error( 'Failed to decode JSON: ' . json_last_error_msg(), [
					'json' => $response,
					'url' => $url,
				] );
			}
			return $json;
		}
		// HTTP error, the request factory already logged it
		return null;
	}

}
