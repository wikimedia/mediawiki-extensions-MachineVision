<?php

namespace MediaWiki\Extension\MachineVision\Test;

use MediaWiki\Extension\MachineVision\Client\GoogleCloudVisionClient;
use MediaWiki\Extension\MachineVision\LabelSuggestion;
use MediaWiki\Extension\MachineVision\Repository;
use MediaWikiUnitTestCase;
use Wikimedia\TestingAccessWrapper;

/** @covers \MediaWiki\Extension\MachineVision\Client\GoogleCloudVisionClient */
class GoogleCloudVisionClientTest extends MediaWikiUnitTestCase {

	private const WITHHOLD_IMAGE_LIST = [ 'Q1' ];

	private const SAFE_SEARCH_LIMITS = [
		'adult' => 3,
		'spoof' => 3,
		'medical' => 3,
		'violence' => 3,
		'racy' => 3,
	];

	/** @var GoogleCloudVisionClient */
	private $client;

	public function setUp(): void {
		$this->client = TestingAccessWrapper::newFromClass( GoogleCloudVisionClient::class );
	}

	public function testNoWithholding(): void {
		$suggestions = [ new LabelSuggestion( 'Q3', 1.0 ) ];
		$initialState = $this->client->getInitialLabelState( self::WITHHOLD_IMAGE_LIST,
			$suggestions, self::SAFE_SEARCH_LIMITS, 1, 1, 1, 1, 1 );
		$this->assertEquals( Repository::REVIEW_UNREVIEWED, $initialState );
	}

	public function testWithheldFromPopular(): void {
		$suggestions = [ new LabelSuggestion( 'Q3', 1.0 ) ];
		$initialState = $this->client->getInitialLabelState( self::WITHHOLD_IMAGE_LIST,
			$suggestions, self::SAFE_SEARCH_LIMITS, 1, 1, 5, 1, 1 );
		$this->assertEquals( Repository::REVIEW_WITHHELD_POPULAR, $initialState );
	}

	public function testWithheldFromAll(): void {
		$suggestions = [ new LabelSuggestion( 'Q1', 1.0 ) ];
		$initialState = $this->client->getInitialLabelState( self::WITHHOLD_IMAGE_LIST,
			$suggestions, self::SAFE_SEARCH_LIMITS, 1, 1, 1, 1, 1 );
		$this->assertEquals( Repository::REVIEW_WITHHELD_ALL, $initialState );
	}

	public function testWithheldFromAllOverridesWithholdPopular(): void {
		$suggestions = [ new LabelSuggestion( 'Q1', 1.0 ) ];
		$initialState = $this->client->getInitialLabelState( self::WITHHOLD_IMAGE_LIST,
			$suggestions, self::SAFE_SEARCH_LIMITS, 1, 1, 1, 5, 1 );
		$this->assertEquals( Repository::REVIEW_WITHHELD_ALL, $initialState );
	}

	public function testNoSuggestions(): void {
		$suggestions = [];
		$initialState = $this->client->getInitialLabelState( self::WITHHOLD_IMAGE_LIST,
			$suggestions, self::SAFE_SEARCH_LIMITS, 1, 1, 1, 1, 1 );
		$this->assertEquals( Repository::REVIEW_UNREVIEWED, $initialState );
	}

}
