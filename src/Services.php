<?php
// phpcs:disable MediaWiki.Commenting.FunctionComment.MissingDocumentationPublic

namespace MediaWiki\Extension\MachineVision;

use Config;
use MediaWiki\Extension\MachineVision\Client\GoogleCloudVisionClient;
use MediaWiki\Extension\MachineVision\Client\RandomWikidataIdClient;
use MediaWiki\Extension\MachineVision\Handler\LabelResolver;
use MediaWiki\Extension\MachineVision\Handler\Registry;
use MediaWiki\MediaWikiServices;
use MediaWiki\Storage\NameTableStore;
use RepoGroup;

class Services {

	/** @var MediaWikiServices */
	private $services;

	public function __construct( MediaWikiServices $services ) {
		$this->services = $services;
	}

	public function getGoogleCloudVisionClient(): GoogleCloudVisionClient {
		return $this->services->getService( 'MachineVisionGoogleCloudVisionClient' );
	}

	public function getRandomWikidataIdClient(): RandomWikidataIdClient {
		return $this->services->getService( 'MachineVisionRandomWikidataIdClient' );
	}

	public function getNameTableStore(): NameTableStore {
		return $this->services->getService( 'MachineVisionNameTableStore' );
	}

	public function getRepository(): Repository {
		return $this->services->getService( 'MachineVisionRepository' );
	}

	public function getHandlerRegistry(): Registry {
		return $this->services->getService( 'MachineVisionHandlerRegistry' );
	}

	public function getExtensionConfig(): Config {
		return $this->services->getService( 'MachineVisionConfig' );
	}

	public function getRepoGroup(): RepoGroup {
		return $this->services->getService( 'MachineVisionRepoGroup' );
	}

	public function getLabelResolver(): LabelResolver {
		return $this->services->getService( 'MachineVisionLabelResolver' );
	}

	public function getTitleFilter(): TitleFilter {
		return $this->services->getService( 'MachineVisionTitleFilter' );
	}

}
