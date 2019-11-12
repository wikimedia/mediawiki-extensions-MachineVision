<?php

namespace MediaWiki\Extension\MachineVision\Job;

use Job;
use MediaWiki\Extension\MachineVision\Services;
use MediaWiki\MediaWikiServices;
use Title;

class FetchGoogleCloudVisionAnnotationsJob extends Job {

	/**
	 * @return bool success
	 * @suppress PhanTypeMismatchArgument
	 */
	public function run(): bool {
		$title = $this->params['title'];
		$namespace = $this->params['namespace'];
		$provider = $this->params['provider'];

		$services = MediaWikiServices::getInstance();
		$repoGroup = $services->getRepoGroup();
		$file = $repoGroup->getLocalRepo()->findFile( Title::newFromText( $title, $namespace ) );

		$extensionServices = new Services( $services );
		$client = $extensionServices->getGoogleCloudVisionClient();
		$client->fetchAnnotations( $provider, $file, true );

		return true;
	}

}
