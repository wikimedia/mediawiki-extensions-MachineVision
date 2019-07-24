<?php

namespace MediaWiki\Extension\MachineVision;

use File;
use MediaWiki\Http\HttpRequestFactory;
use PHPUnit\Framework\TestCase;
use Title;

class ClientTest extends TestCase {

	/**
	 * @covers \MediaWiki\Extension\MachineVision\Client::getFileMetadata
	 * @dataProvider provideGetFileMetadata
	 */
	public function testGetFileMetadata( $httpRequestFactory, $expectedData ) {
		$file = $this->getMockFile( 'X.png' );
		$client = new Client( $httpRequestFactory, 'https://example.com/?title=$1', 'UA' );
		$data = $client->getFileMetadata( $file );
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
				], [
					'wikidata_id' => 'Q876461',
					'label' => 'spulzied',
				], [
					'wikidata_id' => 'Q57538',
					'label' => 'unnecessariness',
				], [
					'wikidata_id' => 'Q790587',
					'label' => 'lapilliform',
				], [
					'wikidata_id' => 'Q963064',
					'label' => 'gentilize',
				], [
					'wikidata_id' => 'Q390032',
					'label' => 'hyalomelanes',
				], [
					'wikidata_id' => 'Q882995',
					'label' => 'hucks',
				], [
					'wikidata_id' => 'Q522949',
					'label' => 'unordinary',
				], [
					'wikidata_id' => 'Q833708',
					'label' => 'latten',
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

	private function getMockFile( $name ): File {
		$title = Title::newFromText( $name, NS_FILE );
		$file = $this->getMockBuilder( File::class )
			->disableOriginalConstructor()
			->setMethods( [ 'getTitle' ] )
			->getMock();
		$file->expects( $this->any() )
			->method( 'getTitle' )
			->willReturn( $title );
		/** @var $file File */
		return $file;
	}

}
