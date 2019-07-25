<?php

namespace MediaWiki\Extension\MachineVision;

use DatabaseUpdater;
use DeferredUpdates;
use MediaWiki\MediaWikiServices;
use UploadBase;

class Hooks {

	/**
	 * @param UploadBase $uploadBase
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/UploadComplete
	 */
	public static function onUploadComplete( UploadBase $uploadBase ) {
		$file = $uploadBase->getLocalFile();
		DeferredUpdates::addCallableUpdate( function () use ( $file ) {
			$services = new Services( MediaWikiServices::getInstance() );
			$registry = $services->getHandlerRegistry();
			foreach ( $registry->getHandlers( $file ) as $handler ) {
				$handler->handleUploadComplete( $file );
			}
		} );
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
