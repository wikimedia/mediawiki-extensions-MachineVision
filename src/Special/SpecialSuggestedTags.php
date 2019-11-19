<?php

namespace MediaWiki\Extension\MachineVision\Special;

use MediaWiki\Extension\MachineVision\Services;
use MediaWiki\MediaWikiServices;
use SpecialPage;

class SpecialSuggestedTags extends SpecialPage {

	/** @inheritDoc */
	public function __construct( $name = 'SuggestedTags' ) {
		parent::__construct( $name, $this->testersOnly() ? 'imagelabel-test' : '' );
	}

	/** @inheritDoc */
	public function execute( $par ) {
		$this->checkPermissions();

		$request = $this->getRequest();
		$output = $this->getOutput();
		$this->setHeaders();

		// TODO: Display a fallback message for Grade C via client-nojs.

		$this->getOutput()->addModules( 'ext.MachineVision' );
	}

	/** @inheritDoc */
	public function getDescription() {
		return $this->msg( 'machinevision-machineaidedtagging' )->parse();
	}

	private function testersOnly() {
		$extensionServices = new Services( MediaWikiServices::getInstance() );
		return $extensionServices->getExtensionConfig()->get( 'MachineVisionTestersOnly' );
	}
}
