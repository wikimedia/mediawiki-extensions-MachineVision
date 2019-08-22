<?php

namespace MediaWiki\Extension\MachineVision\Handler;

use MediaWiki\Extension\MachineVision\Client;
use MediaWiki\Extension\MachineVision\MockHelper;
use MediaWiki\Extension\MachineVision\Repository;
use PHPUnit\Framework\TestCase;

class WikidataIdHandlerTest extends TestCase {

	/**
	 * @covers \MediaWiki\Extension\MachineVision\Handler\WikidataIdHandler::handleUploadComplete
	 */
	public function testHandleUploadComplete() {
		$apiUrlTemplate = 'https://example.com/?title=$1';
		$sha1 = '0beec7b5ea3f0fdbc95d0dd47f3c5bc275da8a33';
		$file = MockHelper::getMockFile( $this, 'Foo.png', $sha1 );
		$response = [
			'title' => 'File:Seal_mechanical_compression.png',
			'timestamp' => 1563972620213,
			'provider' => 'random',
			'labels' => [
				[
					'wikidata_id' => 'Q773044',
					'label' => 'siffleurs',
				], [
					'wikidata_id' => 'Q29106',
					'label' => 'allotrope',
				],
			],
		];

		$client = $this->getMockBuilder( Client::class )
			->disableOriginalConstructor()
			->setMethods( [ 'getFileMetadata' ] )
			->getMock();
		$client->expects( $this->once() )
			->method( 'getFileMetadata' )
			->with( $file, $apiUrlTemplate )
			->willReturn( $response );
		/** @var Client $client */

		$repository = $this->getMockBuilder( Repository::class )
			->disableOriginalConstructor()
			->setMethods( [ 'insertLabels' ] )
			->getMock();
		$repository->expects( $this->once() )
			->method( 'insertLabels' )
			->with( $sha1, 'random', [ 'Q773044', 'Q29106' ] );
		/** @var Repository $repository */

		$labelResolver = $this->getMockBuilder( LabelResolver::class )
			->disableOriginalConstructor()
			->getMock();
		/** @var LabelResolver $labelResolver */

		$wikidataIdHandler = new RandomWikidataIdHandler( $client, $repository, $labelResolver,
			$apiUrlTemplate );
		$wikidataIdHandler->handleUploadComplete( 'random',  $file );
	}

}
