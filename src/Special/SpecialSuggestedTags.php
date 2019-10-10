<?php

namespace MediaWiki\Extension\MachineVision\Special;

use SpecialPage;
use ContentSecurityPolicy;

class SpecialSuggestedTags extends SpecialPage {

	/** @inheritDoc */
	public function __construct( $name = 'SuggestedTags' ) {
		parent::__construct( $name );
	}

	/** @inheritDoc */
	public function execute( $par ) {
		$request = $this->getRequest();
		$output = $this->getOutput();
		$this->setHeaders();

		// Set CSP headers
		ContentSecurityPolicy::sendHeaders( $this->getContext() );

		$moduleID = 'ext.MachineVision';
		$this->getOutput()->addJsConfigVars( [ 'moduleID' => $moduleID ] );
		$this->getOutput()->addModules( [ $moduleID ] );
	}

	/** @inheritDoc */
	public function getDescription() {
		return $this->msg( 'machinevision-machineaidedtagging' )->text();
	}
}
