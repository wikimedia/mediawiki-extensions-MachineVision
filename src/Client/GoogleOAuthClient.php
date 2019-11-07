<?php

namespace MediaWiki\Extension\MachineVision\Client;

use DomainException;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Logger\LoggerFactory;
use MWHttpRequest;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Status;

class GoogleOAuthClient implements LoggerAwareInterface {

	use LoggerAwareTrait;

	const TOKEN_CREDENTIAL_URI = 'https://oauth2.googleapis.com/token';
	const JWT_URN = 'urn:ietf:params:oauth:grant-type:jwt-bearer';
	const DEFAULT_EXPIRY_SECONDS = 3600;
	const DEFAULT_SKEW_SECONDS = 60;
	const SCOPE = 'https://www.googleapis.com/auth/cloud-vision';
	const SIGNING_ALGORITHM = 'RS256';
	const HASHING_ALGORITHM = 'SHA256';

	/** @var HttpRequestFactory */
	private $httpRequestFactory;

	/** @var array */
	private $credentialsData;

	/** @var string */
	private $proxy;

	/**
	 * GoogleOAuthClient constructor.
	 * @param HttpRequestFactory $httpRequestFactory
	 * @param array $credentialsData
	 * @param string|null $proxy
	 */
	public function __construct(
		HttpRequestFactory $httpRequestFactory,
		array $credentialsData,
		string $proxy = null
	) {
		$this->httpRequestFactory = $httpRequestFactory;
		$this->credentialsData = $credentialsData;
		$this->proxy = $proxy;

		$this->setLogger( LoggerFactory::getInstance( 'machinevision' ) );
	}

	public function fetchAuthToken() {
		$request = $this->getTokenRequest();
		$status = $request->execute();

		if ( !$status->isOK() ) {
			$errors = $status->getErrorsByType( 'error' );
			$this->logger->warning(
				Status::wrap( $status )->getMessage( false, false, 'en' )->serialize(),
				[
					'error' => $errors,
					'caller' => __METHOD__,
					'content' => $request->getContent()
				]
			);
			return false;
		}

		return json_decode( $request->getContent(), true );
	}

	private function getTokenRequest(): MWHttpRequest {
		$httpRequestFactory = $this->httpRequestFactory;
		$now = time();

		$assertion = $this->encode( [
			'iss' => $this->credentialsData['client_email'],
			'aud' => self::TOKEN_CREDENTIAL_URI,
			'exp' => self::DEFAULT_EXPIRY_SECONDS + $now,
			'iat' => self::DEFAULT_SKEW_SECONDS + $now,
			'scope' => self::SCOPE,
		], $this->credentialsData['private_key'] );

		$options = [
			'method' => 'POST',
			'postData' => [
				'grant_type' => self::JWT_URN,
				'assertion' => $assertion,
			],
		];
		if ( $this->proxy ) {
			$options['proxy'] = $this->proxy;
		}

		$request = $httpRequestFactory->create(
			self::TOKEN_CREDENTIAL_URI,
			$options
		);

		$request->setHeader( 'Content-Type', 'application/x-www-form-urlencoded' );
		$request->setHeader( 'Cache-Control', 'no-store' );

		return $request;
	}

	private function encode( $payload, $key ) {
		$header = [ 'typ' => 'JWT', 'alg' => self::SIGNING_ALGORITHM ];
		$result = [
			$this->urlsafeB64Encode( json_encode( $header ) ),
			$this->urlsafeB64Encode( json_encode( $payload ) ),
		];
		$signingInput = implode( '.', $result );
		$result[] = $this->urlsafeB64Encode( $this->sign( $signingInput, $key ) );
		return implode( '.', $result );
	}

	private function sign( $input, $key ) {
		$signature = '';
		$success = openssl_sign( $input, $signature, $key, self::HASHING_ALGORITHM );
		if ( !$success ) {
			throw new DomainException( 'OpenSSL unable to sign data' );
		} else {
			return $signature;
		}
	}

	private function urlsafeB64Encode( $input ) {
		return str_replace( '=', '', strtr( base64_encode( $input ), '+/', '-_' ) );
	}

}
