<?php

namespace MediaWiki\Extension\MachineVision;

use Exception;
use Message;

/**
 * Exception thrown when attempting to save a revision to a Wikibase entity.
 * Class MachineVisionEntitySaveException
 * @package MediaWiki\Extension\MachineVision
 */
class MachineVisionEntitySaveException extends Exception {

	/**
	 * @param Message $message Message from the not-OK Status result when attempting to save
	 */
	public function __construct( Message $message ) {
		parent::__construct( $message->parse() );
	}

}
