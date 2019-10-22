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

		// TODO: Display a fallback message for Grade C via client-nojs.

		$this->getOutput()->addModules( 'ext.MachineVision' );
	}

	/** @inheritDoc */
	public function getDescription() {
		return $this->msg( 'machinevision-machineaidedtagging' )->text();
	}
}
