<?php

namespace MediaWiki\Extension\MachineVision\Exception;

use Exception;
use Status;

/**
 * Exception thrown when attempting to save a revision to a Wikibase entity.
 * Class MachineVisionEntitySaveException
 * @package MediaWiki\Extension\MachineVision
 */
class MachineVisionEntitySaveException extends Exception {

	/**
	 * Log the entire contents of the bad Status resulting from an entity save attempt, in order to
	 * navigate the thicket of possibly-failing validation checks.
	 * @param Status $status not-OK Status result
	 */
	public function __construct( Status $status ) {
		parent::__construct( $status->__toString() );
	}

}
