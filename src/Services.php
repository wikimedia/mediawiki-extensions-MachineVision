<?php
// phpcs:disable MediaWiki.Commenting.FunctionComment.MissingDocumentationPublic

namespace MediaWiki\Extension\MachineVision;

use MediaWiki\MediaWikiServices;

class Services {

	/** @var MediaWikiServices */
	private $services;

	public function __construct( MediaWikiServices $services ) {
		$this->services = $services;
	}

	public function getClient(): Client {
		return $this->services->getService( 'MachineVisionClient' );
	}

	public function getRepository(): Repository {
		return $this->services->getService( 'MachineVisionRepository' );
	}

	public function getUploadHandler(): UploadHandler {
		return $this->services->getService( 'MachineVisionUploadHandler' );
	}

}
