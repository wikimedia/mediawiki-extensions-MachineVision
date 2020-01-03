<?php

namespace MediaWiki\Extension\MachineVision;

use DerivativeContext;
use LocalFile;
use LocalRepo;
use MediaWiki\Extension\MachineVision\Handler\WikidataIdHandler;
use MediaWiki\MediaWikiServices;
use MediaWikiIntegrationTestCase;
use Prophecy\Argument;
use RepoGroup;
use RequestContext;
use Title;
use UploadBase;
use function Wikimedia\base_convert;

/**
 * @group Database
 */
class HooksTest extends MediaWikiIntegrationTestCase {

	public function setUp() : void {
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
		$this->markTestSkipped( 'Broken: '
			. 'Error: 1054 Unknown column \'mvl_image_sha1\' in \'where clause\' and '
			. 'No such service: MachineVisionClient'
		);

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

	/**
	 * @covers \MediaWiki\Extension\MachineVision\Hooks::onInfoAction
	 */
	public function testOnInfoAction() {
		$this->markTestSkipped( 'Broken: No such service: MachineVisionClient' );

		$sha1 = base_convert( sha1( 'Foom.png' ), 16, 36, 31 );

		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setTitle( Title::newFromText( 'File:Foom.png' ) );

		// file doesn't exist
		$pageInfo = [];
		Hooks::onInfoAction( $context, $pageInfo );
		$this->assertEmpty( $pageInfo );

		$this->setSetMockFile( 'File:Foom.png', $sha1 );

		// file has no labels
		$pageInfo = [];
		Hooks::onInfoAction( $context, $pageInfo );
		$this->assertEmpty( $pageInfo );

		$extensionServices = new Services( MediaWikiServices::getInstance() );
		$repository = $extensionServices->getRepository();
		$repository->insertLabels( $sha1, 'fake', [ 'Q123', 'Q234' ] );

		$pageInfo = [];
		Hooks::onInfoAction( $context, $pageInfo );
		$entry = $pageInfo['header-properties'][0] ?? null;
		$this->assertNotEmpty( $entry[0] ?? null );
		$this->assertSame( 'Q123, Q234', strip_tags( $entry[1] ?? '' ) );
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
		$client = $this->getMockBuilder( RandomWikidataIdClient::class )
			->disableOriginalConstructor()
			->setMethods( [ 'getFileMetadata' ] )
			->getMock();
		$client->expects( $this->once() )
			->method( 'getFileMetadata' )
			->willReturn( $response );
		/** @var RandomWikidataIdClient $client */
		return $client;
	}

	private function setSetMockFile( $name, $sha1 ) {
		$title = Title::newFromText( $name, NS_FILE );
		$file = MockHelper::getMockFile( $this, $name, $sha1 );
		$repo = $this->prophesize( LocalRepo::class );
		$repo->findFile( Argument::that( function ( $actualTitle ) use ( $title ) {
			return $title->equals( $actualTitle );
		} ) )->willReturn( $file );
		$repoGroup = $this->prophesize( RepoGroup::class );
		$repoGroup->getLocalRepo()->willReturn( $repo->reveal() );
		$this->setService( 'RepoGroup', $repoGroup->reveal() );
	}

}
