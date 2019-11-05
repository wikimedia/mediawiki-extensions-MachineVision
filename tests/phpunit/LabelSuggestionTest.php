<?php

namespace Mediawiki\Extension\MachineVision;

use PHPUnit\Framework\TestCase;

/**
 * @covers Mediawiki\Extension\MachineVision\LabelSuggestion
 */
class LabelSuggestionTest extends TestCase {

	public function testLabelSuggestion() {
		$ls = new LabelSuggestion( 'Q123', 0.9 );
		$this->assertEquals( $ls->getWikidataId(), 'Q123' );
		$this->assertEquals( $ls->getConfidence(), 0.9 );
	}

	public function testConfidenceTypeError() {
		$this->expectException( \TypeError::class );
		$ls = new LabelSuggestion( 'Q456', 'a' );
	}

	public function testWithoutConfidence() {
		$ls = new LabelSuggestion( 'Q321' );
		$this->assertEquals( $ls->getWikidataId(), 'Q321' );
		$this->assertEquals( $ls->getConfidence(), 0.0 );
	}

	public function testArgumentCountError() {
		$this->expectException( \ArgumentCountError::class );
		$ls = new LabelSuggestion();
	}

}
