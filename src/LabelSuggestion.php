<?php

namespace MediaWiki\Extension\MachineVision;

class LabelSuggestion {

	/** @var string */
	private $wikidataId;

	/** @var float */
	private $confidence;

	/**
	 * LabelSuggestion constructor.
	 * @param string $wikidataId
	 * @param float $confidence
	 */
	public function __construct( $wikidataId, float $confidence = 0.0 ) {
		$this->wikidataId = $wikidataId;
		$this->confidence = $confidence;
	}

	/**
	 * @return string
	 */
	public function getWikidataId() {
		return $this->wikidataId;
	}

	/**
	 * @return float
	 */
	public function getConfidence() {
		return $this->confidence;
	}

}
