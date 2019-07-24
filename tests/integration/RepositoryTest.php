<?php

namespace MediaWiki\Extension\MachineVision;

use MediaWiki\MediaWikiServices;
use MediaWikiIntegrationTestCase;
use function Wikimedia\base_convert;

/**
 * @group Database
 */
class RepositoryTest extends MediaWikiIntegrationTestCase {

	public function setUp() {
		parent::setUp();
		$this->tablesUsed[] = 'machine_vision_provider';
		$this->tablesUsed[] = 'machine_vision_label';
	}

	/**
	 * @covers \MediaWiki\Extension\MachineVision\Repository::getLabels
	 * @covers \MediaWiki\Extension\MachineVision\Repository::insertLabels
	 */
	public function testLabels() {
		$services = MediaWikiServices::getInstance();
		$extensionServices = new Services( $services );
		$repository = $extensionServices->getRepository();

		$sha1Foo = base_convert( sha1( 'foo' ), 16, 36, 31 );
		$sha1Bar = base_convert( sha1( 'bar' ), 16, 36, 31 );

		$labels = $repository->getLabels( $sha1Foo );
		$this->assertSame( [], $labels );

		$repository->insertLabels( $sha1Foo, 'some-provider', [ 'Q123', 'Q456' ] );
		$labels = $repository->getLabels( $sha1Foo );
		$this->assertArrayEquals( [ 'Q123', 'Q456' ], $labels );

		$repository->insertLabels( $sha1Foo, 'some-provider', [ 'Q789' ] );
		$labels = $repository->getLabels( $sha1Foo );
		$this->assertArrayEquals( [ 'Q123', 'Q456', 'Q789' ], $labels );

		$repository->insertLabels( $sha1Foo, 'other-provider', [ 'Q123', 'Q321' ] );
		$labels = $repository->getLabels( $sha1Foo );
		$this->assertArrayEquals( [ 'Q123', 'Q456', 'Q789', 'Q321' ], $labels );

		$labels = $repository->getLabels( $sha1Bar );
		$this->assertSame( [], $labels );

		$repository->insertLabels( $sha1Bar, 'some-provider', [ 'Q123', 'Q234' ] );
		$labels = $repository->getLabels( $sha1Foo );
		$this->assertArrayEquals( [ 'Q123', 'Q456', 'Q789', 'Q321' ], $labels );
		$labels = $repository->getLabels( $sha1Bar );
		$this->assertArrayEquals( [ 'Q123', 'Q234' ], $labels );
	}

}
