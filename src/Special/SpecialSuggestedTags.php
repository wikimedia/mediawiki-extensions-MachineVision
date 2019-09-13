<?php

namespace MediaWiki\Extension\MachineVision\Special;

use SpecialPage;

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
		// $out->addModuleStyles( $moduleStyles );
		$moduleID = 'ext.MachineVision';
		$this->getOutput()->addJsConfigVars( [ 'moduleID' => $moduleID ] );
		$this->getOutput()->addModules( [ $moduleID ] );
	}

	/** @inheritDoc */
	public function getDescription() {
		return $this->msg( 'machinevision-machineaidedtagging' )->text();
	}
}
