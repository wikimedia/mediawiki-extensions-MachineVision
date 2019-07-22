<?php

namespace MediaWiki\Extension\MachineVision;

use DatabaseUpdater;

class Hooks {

	/**
	 * Hook: LoadExtensionSchemaUpdates
	 * @param DatabaseUpdater $updater
	 * @return bool
	 */
	public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ) {
		/* Add DB tables */
		return true;
	}
}
