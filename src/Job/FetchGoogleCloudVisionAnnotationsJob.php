<?php

namespace MediaWiki\Extension\MachineVision\Job;

use Job;
use MediaWiki\Extension\MachineVision\Services;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

class FetchGoogleCloudVisionAnnotationsJob extends Job implements LoggerAwareInterface {

	use LoggerAwareTrait;

	/**
	 * @param string $command
	 * @param array|Title|null $params
	 */
	public function __construct( string $command, $params = null ) {
		parent::__construct( $command, $params );
		$this->setLogger( LoggerFactory::getInstance( 'machinevision' ) );
	}

	/**
	 * @return bool success
	 * @suppress PhanTypeMismatchArgument
	 */
	public function run(): bool {
		$title = $this->params['title'];
		$namespace = $this->params['namespace'];
		$provider = $this->params['provider'];
		$priority = $this->params['priority'] ?? 0;

		$services = MediaWikiServices::getInstance();
		$repoGroup = $services->getRepoGroup();
		$file = $repoGroup->getLocalRepo()->findFile( Title::newFromText( $title, $namespace ) );

		if ( !$file ) {
			// File not found. Note that this is an expected scenario. The extension
			// configuration provides for delaying this job due to the high likelihood of newly
			// uploaded files being deleted.
			$this->logger->info( 'Local file not found', [ 'title' => $title ] );
			return true;
		}

		$extensionServices = new Services( $services );
		$client = $extensionServices->getGoogleCloudVisionClient();
		$client->fetchAnnotations( $provider, $file, $priority, false );

		return true;
	}

}
