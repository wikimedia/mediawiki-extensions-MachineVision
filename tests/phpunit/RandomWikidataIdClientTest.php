<?php

namespace MediaWiki\Extension\MachineVision;

use MediaWiki\Extension\MachineVision\Client\RandomWikidataIdClient;
use MediaWiki\Http\HttpRequestFactory;
use PHPUnit\Framework\TestCase;

class RandomWikidataIdClientTest extends TestCase {

	/**
	 * @covers \MediaWiki\Extension\MachineVision\RandomWikidataIdClient::getFileMetadata
	 * @dataProvider provideGetFileMetadata
	 */
	public function testGetFileMetadata( $httpRequestFactory, $expectedData ) {
		$file = MockHelper::getMockFile( $this, 'X.png' );
		$client = new RandomWikidataIdClient( $httpRequestFactory, 'UA' );
		$data = $client->getFileMetadata( $file, 'https://example.com/?title=$1' );
		$this->assertSame( $expectedData, $data );
	}

	public function provideGetFileMetadata() {
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

		$httpRequestFactory = $this->getMockHttpRequestFactory(
			'https://example.com/?title=File%3AX.png', null );
		yield 'HTTP error' => [ $httpRequestFactory, null ];

		$httpRequestFactory = $this->getMockHttpRequestFactory(
			'https://example.com/?title=File%3AX.png', '{{' );
		yield 'invalid JSON' => [ $httpRequestFactory, null ];

		$httpRequestFactory = $this->getMockHttpRequestFactory(
			'https://example.com/?title=File%3AX.png', json_encode( $response ) );
		yield 'valid' => [ $httpRequestFactory, $response ];
	}

	private function getMockHttpRequestFactory( $url, $response ): HttpRequestFactory {
		$httpRequestFactory = $this->getMockBuilder( HttpRequestFactory::class )
			->disableOriginalConstructor()
			->setMethods( [ 'get' ] )
			->getMock();
		$httpRequestFactory->expects( $this->once() )
			->method( 'get' )
			->with( $url )
			->willReturn( $response );
		/** @var $httpRequestFactory HttpRequestFactory */
		return $httpRequestFactory;
	}

}
