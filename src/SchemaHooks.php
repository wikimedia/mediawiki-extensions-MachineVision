<?php

namespace MediaWiki\Extension\MachineVision;

use DatabaseUpdater;
use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;

class SchemaHooks implements LoadExtensionSchemaUpdatesHook {
	/**
	 * @param DatabaseUpdater $updater
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/LoadExtensionSchemaUpdates
	 */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$sqlDir = __DIR__ . '/../sql';
		$dbType = $updater->getDB()->getType();
		$updater->addExtensionTable( 'machine_vision_provider', "$sqlDir/$dbType/tables-generated.sql" );

		if ( $dbType === 'mysql' ) {
			// 1.35
			$updater->addExtensionField(
				'machine_vision_image',
				'mvi_priority',
				"$sqlDir/$dbType/patch-machine_vision_image-mvi_priority.sql"
			);
		}
	}
}
