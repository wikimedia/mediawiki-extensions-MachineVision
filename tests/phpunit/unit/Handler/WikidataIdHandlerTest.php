<?php

namespace MediaWiki\Extension\MachineVision\Handler;

use MediaWiki\Extension\MachineVision\Client\RandomWikidataIdClient;
use MediaWiki\Extension\MachineVision\LabelSuggestion;
use MediaWiki\Extension\MachineVision\Repository;
use MediaWiki\Tests\Rest\Handler\MediaTestTrait;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\MachineVision\Handler\WikidataIdHandler
 */
class WikidataIdHandlerTest extends MediaWikiUnitTestCase {
	use MediaTestTrait;

	public function testHandleUploadComplete() {
		$apiUrlTemplate = 'https://example.com/?title=$1';
		$file = $this->makeMockFile( 'Foo.png' );
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
			->onlyMethods( [ 'getFileMetadata' ] )
			->getMock();
		$client->expects( $this->once() )
			->method( 'getFileMetadata' )
			->with( $file, $apiUrlTemplate )
			->willReturn( $response );
		/** @var RandomWikidataIdClient $client */

		$repository = $this->getMockBuilder( Repository::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'insertLabels' ] )
			->getMock();
		$repository->expects( $this->once() )
			->method( 'insertLabels' )
			->with( $file->getSha1(), 'random', $file->getUploader(), [
				new LabelSuggestion( 'Q773044' ),
				new LabelSuggestion( 'Q29106' ),
			] );
		/** @var Repository $repository */

		$labelResolver = $this->getMockBuilder( LabelResolver::class )
			->disableOriginalConstructor()
			->getMock();
		/** @var LabelResolver $labelResolver */

		$wikidataIdHandler = new RandomWikidataIdHandler(
			$client,
			$repository,
			$labelResolver,
			$apiUrlTemplate
		);
		$wikidataIdHandler->requestAnnotations( 'random',  $file, 0 );
	}

}
