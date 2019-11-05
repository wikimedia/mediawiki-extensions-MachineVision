<?php

namespace MediaWiki\Extension\MachineVision\Client;

use File;
use MediaWiki\Http\HttpRequestFactory;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

class RandomWikidataIdClient implements LoggerAwareInterface {

	use LoggerAwareTrait;

	/** @var HttpRequestFactory */
	private $httpRequestFactory;

	/** @var string */
	private $userAgent;

	/**
	 * @param HttpRequestFactory $httpRequestFactory
	 * @param string $userAgent Request UA.
	 */
	public function __construct(
		HttpRequestFactory $httpRequestFactory,
		$userAgent
	) {
		$this->httpRequestFactory = $httpRequestFactory;
		$this->userAgent = $userAgent;

		$this->setLogger( new NullLogger() );
	}

	/**
	 * Fetch file metadata from the given URL.
	 * @param File $file
	 * @param string $apiUrlTemplate API URL. $1 will be replaced with the URL-encoded file title.
	 * @return array|null Arbitrary metadata returned by the service
	 */
	public function getFileMetadata( File $file, $apiUrlTemplate ) {
		$titleText = $file->getTitle()->getPrefixedDBkey();
		$url = str_replace( '$1', urlencode( $titleText ), $apiUrlTemplate );
		$response = $this->httpRequestFactory->get( $url, [
			'userAgent' => $this->userAgent,
		], __METHOD__ );
		if ( $response !== null ) {
			$json = json_decode( $response, true );
			if ( $json === null ) {
				$this->logger->error( 'Failed to decode JSON: ' . json_last_error_msg(), [
					'json' => $response,
					'url' => $url,
					'file' => $file->getTitle()->getPrefixedDBkey(),
				] );
			}
			return $json;
		}
		// HTTP error, the request factory already logged it
		return null;
	}

}
