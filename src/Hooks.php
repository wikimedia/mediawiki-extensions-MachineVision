<?php

namespace MediaWiki\Extension\MachineVision;

use Content;
use DatabaseUpdater;
use DeferredUpdates;
use DomainException;
use File;
use IContextSource;
use LocalFile;
use MediaWiki\MediaWikiServices;
use Revision;
use Status;
use UploadBase;
use User;
use Wikimedia\Rdbms\IMaintainableDatabase;
use WikiPage;

class Hooks {

	/** @var array Tables which need to be set up / torn down for tests */
	public static $testTables = [
		'machine_vision_provider',
		'machine_vision_label',
		'machine_vision_suggestion',
		'machine_vision_safe_search',
	];

	/**
	 * @param UploadBase $uploadBase
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/UploadComplete
	 */
	public static function onUploadComplete( UploadBase $uploadBase ) {
		$extensionServices = new Services( MediaWikiServices::getInstance() );
		$extensionConfig = $extensionServices->getExtensionConfig();
		if ( !$extensionConfig->get( 'MachineVisionRequestLabelsOnUploadComplete' ) ) {
			return;
		}
		$file = $uploadBase->getLocalFile();
		if ( !Util::isMediaTypeAllowed( $file->getMediaType() ) ) {
			return;
		}
		$userId = $file->getUser( 'id' );
		if ( $extensionConfig->get( 'MachineVisionTestersOnly' ) &&
			!self::isMachineVisionTester( User::newFromId( $userId ) ) ) {
			return;
		}
		DeferredUpdates::addCallableUpdate( function () use ( $file, $extensionServices ) {
			$registry = $extensionServices->getHandlerRegistry();
			foreach ( $registry->getHandlers( $file ) as $provider => $handler ) {
				$handler->handleUploadComplete( $provider, $file );
			}
		} );
	}

	/**
	 * Handler for PageContentSaveComplete hook
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/PageContentSaveComplete
	 *
	 * @param WikiPage &$wikiPage modified WikiPage
	 * @param User &$user User who edited
	 * @param Content $content New article text
	 * @param string $summary Edit summary
	 * @param bool $minoredit Minor edit or not
	 * @param bool $watchthis Watch this article?
	 * @param string $sectionanchor Section that was edited
	 * @param int &$flags Edit flags
	 * @param Revision $revision Revision that was created
	 * @param Status &$status
	 * @param int $baseRevId
	 * @param int $undidRevId
	 */
	public static function onPageContentSaveComplete(
		&$wikiPage,
		&$user,
		$content,
		$summary,
		$minoredit,
		$watchthis,
		$sectionanchor,
		&$flags,
		$revision,
		Status &$status,
		$baseRevId,
		$undidRevId = 0
	) {
		$services = MediaWikiServices::getInstance();
		if ( strpos( $summary, 'wbsetclaim-create' ) === false ) {
			return;
		}
		try {
			$depicts = Util::getMediaInfoPropertyId( MediaWikiServices::getInstance(), 'depicts' );
		} catch ( DomainException $e ) {
			// If 'depicts' isn't set in MediaInfo config (for example, if we're running in CI),
			// just bail out.
			return;
		}
		if ( strpos( $summary, $depicts ) === false ) {
			return;
		}
		$title = $wikiPage->getTitle();
		if ( $title->getNamespace() !== NS_FILE ) {
			return;
		}
		DeferredUpdates::addCallableUpdate( function () use ( $services, $title ) {
			$extensionServices = new Services( $services );
			if ( !$extensionServices->getTitleFilter()->isGoodTitle( $title ) ) {
				$file = $services->getRepoGroup()->getLocalRepo()->findFile( $title );
				if ( !$file ) {
					return;
				}
				$repo = $extensionServices->getRepository();
				$repo->withholdUnreviewedLabelsForFile( $file->getSha1() );
			}
		} );
	}

	/**
	 * @param IContextSource $context
	 * @param array &$pageInfo
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/InfoAction
	 */
	public static function onInfoAction( IContextSource $context, array &$pageInfo ) {
		$services = MediaWikiServices::getInstance();
		$extensionServices = new Services( MediaWikiServices::getInstance() );
		if ( $extensionServices->getExtensionConfig()->get( 'MachineVisionTestersOnly' ) &&
			!self::isMachineVisionTester( $context->getUser() ) ) {
			return;
		}

		$title = $context->getTitle();
		if ( $title->inNamespace( NS_FILE ) ) {
			/** @var LocalFile $file */
			$file = $services->getRepoGroup()->getLocalRepo()->findFile( $title );
			'@phan-var LocalFile $file';
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
		$updater->addExtensionTable( 'machine_vision_freebase_mapping',
			"$sqlDir/patches/01-add-freebase_mapping.sql" );
		$updater->addExtensionField( 'machine_vision_label', 'mvl_uploader_id',
			"$sqlDir/patches/02-add-mvl_uploader_id.sql" );
		$updater->addExtensionField( 'machine_vision_suggestion', 'mvs_confidence',
			"$sqlDir/patches/03-add-mvs_confidence.sql" );
		$updater->addExtensionField( 'machine_vision_label', 'mvl_suggested_time',
			"$sqlDir/patches/04-add-mvl_suggested_time.sql" );
		$updater->addExtensionField( 'machine_vision_label', 'mvl_reviewer_id',
			"$sqlDir/patches/05-add-mvl_reviewer_id.sql" );
		$updater->addExtensionField( 'machine_vision_label', 'mvl_reviewed_time',
			"$sqlDir/patches/06-add-mvl_reviewed_time.sql" );
		$updater->addExtensionTable( 'machine_vision_safe_search',
			"$sqlDir/patches/07-add-machine_vision_safe_search.sql" );
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

	/**
	 * Handler for the GetPreferences hook
	 *
	 * @param \User $user The user object
	 * @param array &$preferences Their preferences object
	 */
	public static function onGetPreferences( User $user, array &$preferences ) {
		$preferences['wbmad-onboarding-dialog-dismissed'] = [
			'type' => 'api'
		];
	}

	/**
	 * @param File $file
	 * @param string $oldimage
	 * @param Article $article
	 * @param User $user
	 * @param string $reason
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/FileDeleteComplete
	 * @suppress PhanUndeclaredTypeParameter
	 */
	public static function onFileDeleteComplete( File $file, $oldimage, $article, User $user,
		$reason ) {
		if ( !$oldimage ) {
			$extensionServices = new Services( MediaWikiServices::getInstance() );
			DeferredUpdates::addCallableUpdate( function () use ( $file, $extensionServices ) {
				$repository = $extensionServices->getRepository();
				$repository->deleteDataOfDeletedFile( $file->getSha1() );
			} );
		}
	}

	private static function isMachineVisionTester( User $user ): bool {
		$permissionsManager = MediaWikiServices::getInstance()->getPermissionManager();
		$perms = $permissionsManager->getUserPermissions( $user );
		return in_array( 'imagelabel-test', $perms );
	}

}
