<?php

namespace MediaWiki\Extension\MachineVision;

use PHPUnit\Framework\TestCase;

/**
 * @covers \MediaWiki\Extension\MachineVision\Util
 */
class UtilTest extends TestCase {

	public function setUp(): void {
		global $wgMediaInfoProperties;
		$wgMediaInfoProperties = [
			'depicts' => 'P1',
			'banana' => 'P4'
		];
	}

	/**
	 * @dataProvider mediaTypeProvider
	 */
	public function testIsMediaTypeAllowed( $mediaType, $expected ) {
		$this->assertEquals( Util::isMediaTypeAllowed( $mediaType ), $expected );
	}

	public function mediaTypeProvider() {
		return [
			[ 'BITMAP', true ],
			[ 'VIDEO', false ]
		];
	}

	/**
	 * @dataProvider mediaInfoPropertyIdProvider
	 */
	public function testGetMediaInfoPropertyId( $property, $id, $expected ) {
		$this->assertEquals( Util::getMediaInfoPropertyId( $property ) === $id, $expected );
	}

	public function mediaInfoPropertyIdProvider() {
		return [
			[ 'banana', 'P4', true ],
			[ 'depicts', 'P1', true ],
			[ 'depicts', 'P3', false ],
		];
	}

	public function testGetMediaInfoPropertyIdException() {
		$this->expectException( \DomainException::class );
		Util::getMediaInfoPropertyId( 'void' );
	}

}
