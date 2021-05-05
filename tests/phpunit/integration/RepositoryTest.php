<?php

namespace MediaWiki\Extension\MachineVision;

use Exception;
use InvalidArgumentException;
use MediaWiki\MediaWikiServices;
use MediaWikiIntegrationTestCase;
use Throwable;
use function Wikimedia\base_convert;

/**
 * @group Database
 */
class RepositoryTest extends MediaWikiIntegrationTestCase {

	public function setUp() : void {
		parent::setUp();
		$this->tablesUsed[] = 'machine_vision_provider';
		$this->tablesUsed[] = 'machine_vision_label';
		$this->tablesUsed[] = 'machine_vision_suggestion';
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

		$labels = array_column( $repository->getLabels( $sha1Foo ), 'wikidata_id' );
		$this->assertSame( [], $labels );

		$repository->insertLabels( $sha1Foo, 'some-provider', 0, [
			new LabelSuggestion( 'Q123', 1 ),
			new LabelSuggestion( 'Q456', 1 )
		] );
		$labels = array_column( $repository->getLabels( $sha1Foo ), 'wikidata_id' );
		$this->assertArrayEquals( [ 'Q123', 'Q456' ], $labels );

		$repository->insertLabels( $sha1Foo, 'some-provider', 0, [
			new LabelSuggestion( 'Q789', 1 )
		] );
		$labels = array_column( $repository->getLabels( $sha1Foo ), 'wikidata_id' );
		$this->assertArrayEquals( [ 'Q123', 'Q456', 'Q789' ], $labels );

		$repository->insertLabels( $sha1Foo, 'other-provider', 0, [
			new LabelSuggestion( 'Q123', 1 ),
			new LabelSuggestion( 'Q321', 1 )
		] );
		$labels = array_column( $repository->getLabels( $sha1Foo ), 'wikidata_id' );
		$this->assertArrayEquals( [ 'Q123', 'Q456', 'Q789', 'Q321' ], $labels );

		$labels = array_column( $repository->getLabels( $sha1Bar ), 'wikidata_id' );
		$this->assertSame( [], $labels );

		$repository->insertLabels( $sha1Bar, 'some-provider', 0, [
			new LabelSuggestion( 'Q123', 1 ),
			new LabelSuggestion( 'Q234', 1 )
		] );
		$labels = array_column( $repository->getLabels( $sha1Foo ), 'wikidata_id' );
		$this->assertArrayEquals( [ 'Q123', 'Q456', 'Q789', 'Q321' ], $labels );
		$labels = array_column( $repository->getLabels( $sha1Bar ), 'wikidata_id' );
		$this->assertArrayEquals( [ 'Q123', 'Q234' ], $labels );
	}

	/**
	 * @covers \MediaWiki\Extension\MachineVision\Repository::getLabelState
	 * @covers \MediaWiki\Extension\MachineVision\Repository::setLabelState
	 */
	public function testStates() {
		$services = MediaWikiServices::getInstance();
		$extensionServices = new Services( $services );
		$repository = $extensionServices->getRepository();

		$sha1Foo = base_convert( sha1( 'foo' ), 16, 36, 31 );
		$sha1Bar = base_convert( sha1( 'bar' ), 16, 36, 31 );

		$repository->insertLabels( $sha1Foo, 'some-provider', 0, [
			new LabelSuggestion( 'Q123', 1 ),
			new LabelSuggestion( 'Q456', 1 )
		] );

		$this->assertSame( false, $repository->getLabelState( $sha1Bar, 'Q123' ) );
		$this->assertSame( false, $repository->getLabelState( $sha1Foo, 'Q789' ) );
		$this->assertSame( Repository::REVIEW_UNREVIEWED,
			$repository->getLabelState( $sha1Foo, 'Q123' ) );

		$success = $repository->setLabelState( $sha1Foo, 'Q123', Repository::REVIEW_ACCEPTED, 0, 0 );
		$this->assertTrue( $success );
		$this->assertSame( Repository::REVIEW_ACCEPTED,
			$repository->getLabelState( $sha1Foo, 'Q123' ) );
		$this->assertSame( Repository::REVIEW_UNREVIEWED,
			$repository->getLabelState( $sha1Foo, 'Q456' ) );

		// setting to current state
		$success = $repository->setLabelState( $sha1Foo, 'Q123', Repository::REVIEW_ACCEPTED, 0, 0 );
		$this->assertTrue( $success );

		// no such label
		$success = $repository->setLabelState( $sha1Foo, 'Q789', Repository::REVIEW_ACCEPTED, 0, 0 );
		$this->assertFalse( $success );

		// no such file
		$success = $repository->setLabelState( $sha1Bar, 'Q123', Repository::REVIEW_ACCEPTED, 0, 0 );
		$this->assertFalse( $success );

		// no such state
		$this->assertThrows( static function () use ( $sha1Foo, $repository ) {
			$repository->setLabelState( $sha1Foo, 'Q123', 10, 0, 0 );
		}, InvalidArgumentException::class );
	}

	private function assertThrows( callable $callback, $exceptionClass ) {
		try {
			$callback();
		} catch ( Exception $e ) {
			$this->assertInstanceOf( $exceptionClass, $e );
			return;
		} catch ( Throwable $e ) {
			$this->assertInstanceOf( $exceptionClass, $e );
			return;
		}
		$this->fail( "Expected exception $exceptionClass not thrown" );
	}

}
