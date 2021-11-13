<?php

namespace MediaWiki\Extension\MachineVision;

use PHPUnit\Framework\TestCase;

/**
 * @covers MediaWiki\Extension\MachineVision\LabelSuggestion
 */
class LabelSuggestionTest extends TestCase {

	public function testLabelSuggestion() {
		$ls = new LabelSuggestion( 'Q123', 0.9 );
		$this->assertEquals( 'Q123', $ls->getWikidataId() );
		$this->assertEquals( 0.9, $ls->getConfidence() );
	}

	public function testConfidenceTypeError() {
		$this->expectException( \TypeError::class );
		$ls = new LabelSuggestion( 'Q456', 'a' );
	}

	public function testWithoutConfidence() {
		$ls = new LabelSuggestion( 'Q321' );
		$this->assertEquals( 'Q321', $ls->getWikidataId() );
		$this->assertSame( 0.0, $ls->getConfidence() );
	}

	public function testArgumentCountError() {
		$this->expectException( \ArgumentCountError::class );
		$ls = new LabelSuggestion();
	}

}
