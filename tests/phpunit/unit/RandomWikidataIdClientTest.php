<?php

namespace MediaWiki\Extension\MachineVision;

use MediaWiki\Extension\MachineVision\Client\RandomWikidataIdClient;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Tests\Rest\Handler\MediaTestTrait;
use MediaWikiUnitTestCase;

class RandomWikidataIdClientTest extends MediaWikiUnitTestCase {
	use MediaTestTrait;

	/**
	 * @covers \MediaWiki\Extension\MachineVision\Client\RandomWikidataIdClient::getFileMetadata
	 * @dataProvider provideGetFileMetadata
	 */
	public function testGetFileMetadata( $mockUrl, $mockResponse, $expectedData ) {
		$httpRequestFactory = $this->getMockHttpRequestFactory( $mockUrl, $mockResponse );
		$file = $this->makeMockFile( 'X.png' );
		$client = new RandomWikidataIdClient( $httpRequestFactory, 'UA' );
		$data = $client->getFileMetadata( $file, 'https://example.com/?title=$1' );
		$this->assertSame( $expectedData, $data );
	}

	public static function provideGetFileMetadata() {
		$response = [
			'title' => 'File:Seal_mechanical_compression.png',
			'data' => [
				'title' => 'File:Seal_mechanical_compression.png',
				'timestamp' => 1563972620213,
				'provider' => 'random',
				'labels' => [
					'wikidata_id' => 'Q773044',
					'label' => 'siffleurs',
				], [
					'wikidata_id' => 'Q29106',
					'label' => 'allotrope',
				],
			],
		];

		yield 'HTTP error' => [ 'https://example.com/?title=File%3AX.png', null, null ];
		yield 'invalid JSON' => [ 'https://example.com/?title=File%3AX.png', '{{', null ];
		yield 'valid' => [ 'https://example.com/?title=File%3AX.png', json_encode( $response ), $response ];
	}

	private function getMockHttpRequestFactory( $url, $response ): HttpRequestFactory {
		$httpRequestFactory = $this->getMockBuilder( HttpRequestFactory::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'get' ] )
			->getMock();
		$httpRequestFactory->expects( $this->once() )
			->method( 'get' )
			->with( $url )
			->willReturn( $response );
		/** @var $httpRequestFactory HttpRequestFactory */
		return $httpRequestFactory;
	}

}
