<?php

namespace MediaWiki\Extension\MachineVision\Handler;

use MediaWiki\Extension\MachineVision\Client\RandomWikidataIdClient;
use MediaWiki\Extension\MachineVision\MockHelper;
use MediaWiki\Extension\MachineVision\Repository;
use MediaWiki\Extension\MachineVision\LabelSuggestion;
use PHPUnit\Framework\TestCase;
use User;

class WikidataIdHandlerTest extends TestCase {

	/**
	 * @covers \MediaWiki\Extension\MachineVision\Handler\WikidataIdHandler::handleUploadComplete
	 */
	public function testHandleUploadComplete() {
		$apiUrlTemplate = 'https://example.com/?title=$1';
		$sha1 = '0beec7b5ea3f0fdbc95d0dd47f3c5bc275da8a33';
		$user = User::newFromId( 1 );
		$file = MockHelper::getMockFile( $this, 'Foo.png', $sha1, $user );
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

		$client = $this->getMockBuilder( RandomWikidataIdClient::class )
			->disableOriginalConstructor()
			->setMethods( [ 'getFileMetadata' ] )
			->getMock();
		$client->expects( $this->once() )
			->method( 'getFileMetadata' )
			->with( $file, $apiUrlTemplate )
			->willReturn( $response );
		/** @var RandomWikidataIdClient $client */

		$repository = $this->getMockBuilder( Repository::class )
			->disableOriginalConstructor()
			->setMethods( [ 'insertLabels' ] )
			->getMock();
		$repository->expects( $this->once() )
			->method( 'insertLabels' )
			->with( $sha1, 'random', $user, [
				new LabelSuggestion( 'Q773044' ),
				new LabelSuggestion( 'Q29106' ),
			] );
		/** @var Repository $repository */

		$depictsSetter = $this->getMockBuilder( WikidataDepictsSetter::class )
			->disableOriginalConstructor()
			->getMock();
		/** @var WikidataDepictsSetter $depictsSetter */

		$labelResolver = $this->getMockBuilder( LabelResolver::class )
			->disableOriginalConstructor()
			->getMock();
		/** @var LabelResolver $labelResolver */

		$wikidataIdHandler = new RandomWikidataIdHandler( $client, $repository, $depictsSetter,
			$labelResolver, $apiUrlTemplate );
		$wikidataIdHandler->requestAnnotations( 'random',  $file );
	}

}
