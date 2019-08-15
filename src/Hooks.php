<?php

namespace MediaWiki\Extension\MachineVision;

use DatabaseUpdater;
use DeferredUpdates;
use IContextSource;
use LocalFile;
use MediaWiki\Extension\MachineVision\Special\SpecialImageLabeling;
use MediaWiki\MediaWikiServices;
use UploadBase;
use Wikimedia\Rdbms\IMaintainableDatabase;

class Hooks {

	/** @var array Tables which need to be set up / torn down for tests */
	public static $testTables = [
		'machine_vision_provider',
		'machine_vision_label',
	];

	/**
	 * @param UploadBase $uploadBase
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/UploadComplete
	 */
	public static function onUploadComplete( UploadBase $uploadBase ) {
		$extensionServices = new Services( MediaWikiServices::getInstance() );
		if ( !$extensionServices->getExtensionConfig()
			->get( 'MachineVisionRequestLabelsOnUploadComplete' ) ) {
			return;
		}
		$file = $uploadBase->getLocalFile();
		if ( $file->getMediaType() !== MEDIATYPE_BITMAP ) {
			return;
		}
		DeferredUpdates::addCallableUpdate( function () use ( $file, $extensionServices ) {
			$registry = $extensionServices->getHandlerRegistry();
			foreach ( $registry->getHandlers( $file ) as $handler ) {
				$handler->handleUploadComplete( $file );
			}
		} );
	}

	/**
	 * @param array &$wgQueryPages
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/wgQueryPages
	 */
	public static function onwgQueryPages( array &$wgQueryPages ) {
		$wgQueryPages[] = [ SpecialImageLabeling::class, 'ImageLabeling' ];
	}

	/**
	 * @param IContextSource $context
	 * @param array &$pageInfo
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/InfoAction
	 */
	public static function onInfoAction( IContextSource $context, array &$pageInfo ) {
		$services = MediaWikiServices::getInstance();
		$extensionServices = new Services( MediaWikiServices::getInstance() );

		$title = $context->getTitle();
		if ( $title->inNamespace( NS_FILE ) ) {
			/** @var LocalFile $file */
			$file = $services->getRepoGroup()->getLocalRepo()->findFile( $title );
			if ( $file ) {
				$registry = $extensionServices->getHandlerRegistry();
				foreach ( $registry->getHandlers( $file ) as $handler ) {
					$handler->handleInfoAction( $context, $file, $pageInfo );
				}
			}
		}
	}

	/**
	 * @param DatabaseUpdater $updater
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/LoadExtensionSchemaUpdates
	 */
	public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ) {
		$sqlDir = __DIR__ . '/../sql';
		$updater->addExtensionTable( 'machine_vision_provider', "$sqlDir/machine_vision.sql" );
	}

	/**
	 * Setup the tables in the test DB, even if the configuration points elsewhere;
	 * there is less chance of an accident this way. The first time the hook is called
	 * we have to set the DB prefix ourselves, and reset it back to the original
	 * so that CloneDatabase will work. On subsequent runs, the prefix is already
	 * set up for us.
	 *
	 * @param IMaintainableDatabase $db
	 * @param string $prefix
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/UnitTestsAfterDatabaseSetup
	 */
	public static function onUnitTestsAfterDatabaseSetup( $db, $prefix ) {
		global $wgMachineVisionCluster, $wgMachineVisionDatabase;
		$wgMachineVisionCluster = false;
		$wgMachineVisionDatabase = false;
		$originalPrefix = $db->tablePrefix();
		$db->tablePrefix( $prefix );
		if ( !$db->tableExists( 'machine_vision_provider' ) ) {
			$sqlDir = __DIR__ . '/../sql';
			$db->sourceFile( "$sqlDir/machine_vision.sql" );
		}
		$db->tablePrefix( $originalPrefix );
	}

	/**
	 * Cleans up tables created by onUnitTestsAfterDatabaseSetup() above
	 */
	public static function onUnitTestsBeforeDatabaseTeardown() {
		$db = wfGetDB( DB_MASTER );
		foreach ( self::$testTables as $table ) {
			$db->dropTable( $table );
		}
	}

}
