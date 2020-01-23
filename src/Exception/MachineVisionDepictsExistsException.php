<?php

namespace MediaWiki\Extension\MachineVision\Exception;

use Exception;

/**
 * Exception to be thrown when a user approves a label suggestion but a corresponding Depicts
 * statement already exists.
 */
class MachineVisionDepictsExistsException extends Exception {

	/**
	 * MachineVisionDepictsExistsException constructor.
	 * @param string $itemId Wikidata ID
	 * @param string $mediaInfoId MediaInfo ID
	 */
	public function __construct( string $itemId, string $mediaInfoId ) {
		parent::__construct( "Statement 'depicts $itemId' already exists for MediaInfo ID
			$mediaInfoId" );
	}

}
