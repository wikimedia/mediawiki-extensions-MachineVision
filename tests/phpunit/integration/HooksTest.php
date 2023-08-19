<?php

namespace MediaWiki\Extension\MachineVision;

use DerivativeContext;
use File;
use LocalFile;
use LocalRepo;
use MediaWiki\Extension\MachineVision\Handler\WikidataIdHandler;
use MediaWiki\MediaWikiServices;
use MediaWiki\Tests\Rest\Handler\MediaTestTrait;
use MediaWiki\Title\Title;
use MediaWiki\User\UserIdentityValue;
use MediaWikiIntegrationTestCase;
use RepoGroup;
use RequestContext;
use UploadBase;

/**
 * @group Database
 */
class HooksTest extends MediaWikiIntegrationTestCase {
	use MediaTestTrait;

	public function setUp(): void {
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

		$file = $this->makeMockFile( 'Foo.png' );
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
			[ 'mvl_image_sha1' => $file->getSha1() ], __METHOD__ );
		$this->assertSame( false, $row );

		$this->setService( 'MachineVisionClient', $this->getMockClient( null ) );
		Hooks::onUploadComplete( $upload );

		$row = $this->db->selectRow( 'machine_vision_label', '*',
			[ 'mvl_image_sha1' => $file->getSha1() ], __METHOD__ );
		$this->assertSame( false, $row );

		$this->setService( 'MachineVisionClient', $this->getMockClient( $response ) );
		Hooks::onUploadComplete( $upload );

		$values = $this->db->selectFieldValues( 'machine_vision_label', 'mvl_wikidata_id',
			[ 'mvl_image_sha1' => $file->getSha1() ], __METHOD__ );
		$this->assertArrayEquals( [ 'Q773044', 'Q29106' ], $values );
	}

	/**
	 * @covers \MediaWiki\Extension\MachineVision\Hooks::onInfoAction
	 */
	public function testOnInfoAction() {
		$this->markTestSkipped( 'Broken: No such service: MachineVisionClient' );

		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setTitle( Title::newFromText( 'File:Foom.png' ) );

		// file doesn't exist
		$pageInfo = [];
		Hooks::onInfoAction( $context, $pageInfo );
		$this->assertSame( [], $pageInfo );

		$file = $this->setSetMockFile( 'File:Foom.png' );

		// file has no labels
		$pageInfo = [];
		Hooks::onInfoAction( $context, $pageInfo );
		$this->assertSame( [], $pageInfo );

		$extensionServices = new Services( MediaWikiServices::getInstance() );
		$repository = $extensionServices->getRepository();
		$repository->insertLabels(
			$file->getSha1(),
			'fake',
			UserIdentityValue::newAnonymous( '123.123.123.123' ),
			[ new LabelSuggestion( 'Q123' ), new LabelSuggestion( 'Q234' ) ]
		);

		$pageInfo = [];
		Hooks::onInfoAction( $context, $pageInfo );
		$entry = $pageInfo['header-properties'][0] ?? null;
		$this->assertNotEmpty( $entry[0] ?? null );
		$this->assertSame( 'Q123, Q234', strip_tags( $entry[1] ?? '' ) );
	}

	private function getMockUpload( LocalFile $file ): UploadBase {
		$upload = $this->getMockBuilder( UploadBase::class )
			->onlyMethods( [ 'getLocalFile' ] )
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
			->onlyMethods( [ 'getFileMetadata' ] )
			->getMock();
		$client->expects( $this->once() )
			->method( 'getFileMetadata' )
			->willReturn( $response );
		/** @var RandomWikidataIdClient $client */
		return $client;
	}

	private function setSetMockFile( $name ): File {
		$title = Title::newFromText( $name, NS_FILE );
		$file = $this->makeMockFile( $title );
		$repo = $this->createMock( LocalRepo::class );
		$repo->method( 'findFile' )
			->with( $this->callback( static function ( $actualTitle ) use ( $title ) {
				return $title->equals( $actualTitle );
			} ) )
			->willReturn( $file );
		$repoGroup = $this->createMock( RepoGroup::class );
		$repoGroup->method( 'getLocalRepo' )
			->willReturn( $repo );
		$this->setService( 'RepoGroup', $repoGroup );
		return $file;
	}

}
