<?php

namespace MediaWiki\Extension\MachineVision;

use DatabaseUpdater;
use MediaWiki\MediaWikiServices;
use UploadBase;

class Hooks {

	/**
	 * @param UploadBase $uploadBase
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/UploadComplete
	 */
	public static function onUploadComplete( UploadBase $uploadBase ) {
		$services = new Services( MediaWikiServices::getInstance() );
		$handler = $services->getUploadHandler();
		$handler->handle( $uploadBase );
	}

	/**
	 * @param DatabaseUpdater $updater
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/LoadExtensionSchemaUpdates
	 */
	public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ) {
		$sqlDir = __DIR__ . '/../sql';
		$updater->addExtensionTable( 'machine_vision_provider', "$sqlDir/machine_vision.sql" );
	}

}
