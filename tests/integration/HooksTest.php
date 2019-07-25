<?php

namespace MediaWiki\Extension\MachineVision;

use LocalFile;
use MediaWiki\Extension\MachineVision\Handler\WikidataIdHandler;
use MediaWikiIntegrationTestCase;
use UploadBase;
use function Wikimedia\base_convert;

class HooksTest extends MediaWikiIntegrationTestCase {

	public function setUp() {
		parent::setUp();
		$this->setMwGlobals( 'wgMachineVisionHandlers', [
			'random' => [
				'class' => WikidataIdHandler::class,
				'services' => [ 'MachineVisionClient', 'MachineVisionRepository' ],
				'args' => [ 'https://example.org/?title=$1' ],
			],
		] );
	}

	/**
	 * @covers \MediaWiki\Extension\MachineVision\Hooks::onUploadComplete
	 */
	public function testOnUploadComplete() {
		$sha1 = base_convert( sha1( 'baz' ), 16, 36, 31 );
		$file = MockHelper::getMockFile( $this, 'Foo.png', $sha1 );
		$upload = $this->getMockUpload( $file );
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

		// sanity check
		$row = $this->db->selectRow( 'machine_vision_label', '*',
			[ 'mvl_image_sha1' => $sha1 ], __METHOD__ );
		$this->assertSame( false, $row );

		$this->setService( 'MachineVisionClient', $this->getMockClient( null ) );
		Hooks::onUploadComplete( $upload );

		$row = $this->db->selectRow( 'machine_vision_label', '*',
			[ 'mvl_image_sha1' => $sha1 ], __METHOD__ );
		$this->assertSame( false, $row );

		$this->setService( 'MachineVisionClient', $this->getMockClient( $response ) );
		Hooks::onUploadComplete( $upload );

		$values = $this->db->selectFieldValues( 'machine_vision_label', 'mvl_wikidata_id',
			[ 'mvl_image_sha1' => $sha1 ], __METHOD__ );
		$this->assertArrayEquals( [ 'Q773044', 'Q29106' ], $values );
	}

	private function getMockUpload( LocalFile $file ): UploadBase {
		$upload = $this->getMockBuilder( UploadBase::class )
			->setMethods( [ 'getLocalFile' ] )
			->getMockForAbstractClass();
		$upload->expects( $this->any() )
			->method( 'getLocalFile' )
			->willReturn( $file );
		/** @var UploadBase $upload */
		return $upload;
	}

	private function getMockClient( $response ) {
		$client = $this->getMockBuilder( Client::class )
			->disableOriginalConstructor()
			->setMethods( [ 'getFileMetadata' ] )
			->getMock();
		$client->expects( $this->once() )
			->method( 'getFileMetadata' )
			->willReturn( $response );
		/** @var Client $client */
		return $client;
	}

}
