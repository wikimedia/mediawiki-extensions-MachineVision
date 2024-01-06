<?php

namespace MediaWiki\Extension\MachineVision;

use ChangeTags;
use DomainException;
use Exception;
use File;
use IContextSource;
use LocalFile;
use MediaWiki\ChangeTags\Hook\ChangeTagsAllowedAddHook;
use MediaWiki\ChangeTags\Hook\ChangeTagsListActiveHook;
use MediaWiki\ChangeTags\Hook\ListDefinedTagsHook;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Hook\FileDeleteCompleteHook;
use MediaWiki\Hook\InfoActionHook;
use MediaWiki\Hook\SidebarBeforeOutputHook;
use MediaWiki\Hook\UnitTestsAfterDatabaseSetupHook;
use MediaWiki\Hook\UnitTestsBeforeDatabaseTeardownHook;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\Hook\RollbackCompleteHook;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Storage\EditResult;
use MediaWiki\Storage\Hook\PageSaveCompleteHook;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentity;
use Skin;
use UploadBase;
use WikiFilePage;
use Wikimedia\Rdbms\IMaintainableDatabase;
use WikiPage;

class Hooks implements
	InfoActionHook,
	UnitTestsAfterDatabaseSetupHook,
	UnitTestsBeforeDatabaseTeardownHook,
	FileDeleteCompleteHook,
	PageSaveCompleteHook,
	RollbackCompleteHook,
	ListDefinedTagsHook,
	ChangeTagsListActiveHook,
	SidebarBeforeOutputHook,
	ChangeTagsAllowedAddHook
{

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
		$file = $uploadBase->getLocalFile();
		if ( !Util::isMediaTypeAllowed( $file->getMediaType() ) ) {
			return;
		}

		// Ignore new versions of existing files.
		if ( $file->getHistory( 1 ) ) {
			return;
		}

		$extensionServices = new Services( MediaWikiServices::getInstance() );
		$extensionConfig = $extensionServices->getExtensionConfig();
		if ( !$extensionConfig->get( 'MachineVisionRequestLabelsOnUploadComplete' ) ) {
			return;
		}
		$uploader = $file->getUploader( File::RAW );
		if ( $extensionConfig->get( 'MachineVisionTestersOnly' ) &&
			!self::isMachineVisionTester( $uploader ) ) {
			return;
		}
		$registry = $extensionServices->getHandlerRegistry();
		foreach ( $registry->getHandlers( $file ) as $provider => $handler ) {
			$handler->requestAnnotations( $provider, $file, 0 );
		}
	}

	/**
	 * Handler for PageSaveComplete hook
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/PageSaveComplete
	 *
	 * @param WikiPage $wikiPage modified WikiPage
	 * @param UserIdentity $userIdentity User who edited
	 * @param string $summary Edit summary
	 * @param int $flags Edit flags
	 * @param RevisionRecord $revisionRecord Revision that was created
	 * @param EditResult $editResult
	 */
	public function onPageSaveComplete(
		$wikiPage,
		$userIdentity,
		$summary,
		$flags,
		$revisionRecord,
		$editResult
	) {
		$undidRevId = $editResult->getUndidRevId();
		if ( $undidRevId ) {
			self::tagComputerAidedTaggingRevert( $undidRevId );
			return;
		}
		if ( strpos( $summary, 'wbsetclaim-create' ) === false ) {
			return;
		}
		$title = $wikiPage->getTitle();
		if ( $title->getNamespace() !== NS_FILE ) {
			return;
		}
		try {
			$depicts = Util::getMediaInfoPropertyId( 'depicts' );
		} catch ( DomainException $e ) {
			// If 'depicts' isn't set in MediaInfo config (for example, if we're running in CI),
			// just bail out.
			return;
		}
		if ( strpos( $summary, $depicts ) === false ) {
			return;
		}
		$services = MediaWikiServices::getInstance();
		DeferredUpdates::addCallableUpdate( static function () use ( $services, $title ) {
			$extensionServices = new Services( $services );
			if ( !$extensionServices->getTitleFilter()->isGoodTitle( $title ) ) {
				$file = $services->getRepoGroup()->getLocalRepo()->findFile( $title );
				if ( !$file ) {
					return;
				}
				$repo = $extensionServices->getRepository();
				$repo->withholdImageFromPopular( $file->getSha1() );
			}
		} );
	}

	/**
	 * Handler for RollbackComplete hook.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/RollbackComplete
	 *
	 * @param WikiPage $wikiPage The article that was edited
	 * @param UserIdentity $agent The user who did the rollback
	 * @param RevisionRecord $newRev The revision the page was reverted back to
	 * @param RevisionRecord $oldRev The revision of the top edit that was reverted
	 */
	public function onRollbackComplete( $wikiPage,
							  $agent,
							  $newRev,
							  $oldRev ) {
		self::tagComputerAidedTaggingRevert( $oldRev );
	}

	/**
	 * @param IContextSource $context
	 * @param array &$pageInfo
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/InfoAction
	 */
	public function onInfoAction( $context, &$pageInfo ) {
		$title = $context->getTitle();
		if ( !$title->inNamespace( NS_FILE ) ) {
			return;
		}
		$services = MediaWikiServices::getInstance();
		$extensionServices = new Services( MediaWikiServices::getInstance() );

		if ( $extensionServices->getExtensionConfig()->get( 'MachineVisionTestersOnly' ) &&
			!self::isMachineVisionTester( $context->getUser() ) ) {
			return;
		}
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

	/**
	 * @return array
	 */
	public static function getJSConfig() {
		global $wgMachineVisionTestersOnly,
			$wgMachineVisionShowUploadWizardCallToAction,
			$wgMediaInfoProperties;

		return [
			'testersOnly' => $wgMachineVisionTestersOnly,
			'showComputerAidedTaggingCallToAction' => $wgMachineVisionShowUploadWizardCallToAction,
			'depictsPropertyId' => $wgMediaInfoProperties['depicts'] ?? '',
		];
	}

	/**
	 * @param Skin $skin
	 * @param string[] &$sidebar
	 */
	public function onSidebarBeforeOutput( $skin, &$sidebar ): void {
		$extensionServices = new Services( MediaWikiServices::getInstance() );
		$extensionConfig = $extensionServices->getExtensionConfig();
		if ( $extensionConfig->get( 'MachineVisionAddToolboxLink' ) ) {
			$sidebar['TOOLBOX']['computer-aided-tagging'] = [
				'text' => $skin->msg( 'machinevision-machineaidedtagging' ),
				'href' => $skin::makeSpecialUrl( 'SuggestedTags' ),
				'id' => 't-computer-aided-tagging',
			];
		}
	}

	/**
	 * @param array &$tags
	 */
	public function addRegisterTags( array &$tags ) {
		$tags[] = Util::getDepictsTag();
		$tags[] = Util::getDepictsRevertTag();
		$tags[] = Util::getDepictsCustomTag();
		$tags[] = Util::getDepictsCustomRevertTag();
	}

	/**
	 * @param array &$tags
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ListDefinedTags
	 */
	public function onListDefinedTags( &$tags ) {
		$this->addRegisterTags( $tags );
	}

	/**
	 * @param array &$tags
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ChangeTagsListActive
	 */
	public function onChangeTagsListActive( &$tags ) {
		$this->addRegisterTags( $tags );
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
	 * @throws Exception
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/UnitTestsAfterDatabaseSetup
	 */
	public function onUnitTestsAfterDatabaseSetup( $db, $prefix ) {
		global $wgMachineVisionCluster, $wgMachineVisionDatabase;
		$wgMachineVisionCluster = false;
		$wgMachineVisionDatabase = false;
		$originalPrefix = $db->tablePrefix();
		$db->tablePrefix( $prefix );
		if ( !$db->tableExists( 'machine_vision_provider', __METHOD__ ) ) {
			$sqlDir = __DIR__ . '/../sql';
			$dbType = $db->getType();
			$db->sourceFile( "$sqlDir/$dbType/tables-generated.sql" );
		}
		$db->tablePrefix( $originalPrefix );
	}

	/**
	 * Cleans up tables created by onUnitTestsAfterDatabaseSetup() above
	 */
	public function onUnitTestsBeforeDatabaseTeardown() {
		$db = wfGetDB( DB_PRIMARY );
		foreach ( self::$testTables as $table ) {
			$db->dropTable( $table );
		}
	}

	/**
	 * Handler for the GetPreferences hook
	 *
	 * @param User $user The user object
	 * @param array &$preferences Their preferences object
	 */
	public static function onGetPreferences( User $user, array &$preferences ) {
		$preferences['wbmad-onboarding-dialog-dismissed'] = [
			'type' => 'api'
		];

		$preferences['wbmad-image-exclusion-notice-dismissed'] = [
			'type' => 'api'
		];
	}

	/**
	 * @param LocalFile $file
	 * @param string $oldimage
	 * @param WikiFilePage|null $article
	 * @param User $user
	 * @param string $reason
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/FileDeleteComplete
	 */
	public function onFileDeleteComplete( $file, $oldimage, $article, $user,
		$reason ) {
		if ( !$oldimage ) {
			$extensionServices = new Services( MediaWikiServices::getInstance() );
			DeferredUpdates::addCallableUpdate( static function () use ( $file, $extensionServices ) {
				$repository = $extensionServices->getRepository();
				$repository->deleteDataOfDeletedFile( $file->getSha1() );
			} );
		}
	}

	private static function isMachineVisionTester( ?UserIdentity $user ): bool {
		if ( !$user ) {
			return false;
		}
		$permissionsManager = MediaWikiServices::getInstance()->getPermissionManager();
		$perms = $permissionsManager->getUserPermissions( $user );
		return in_array( 'imagelabel-test', $perms );
	}

	/**
	 * @param int|RevisionRecord $rev
	 */
	private static function tagComputerAidedTaggingRevert( $rev ) {
		if ( gettype( $rev ) === 'integer' ) {
			$rev = MediaWikiServices::getInstance()->getRevisionStore()->getRevisionById( $rev );
		}
		$oldRevTags = ChangeTags::getTags( wfGetDB( DB_REPLICA ), null, $rev->getId() );
		if ( in_array( Util::getDepictsTag(), $oldRevTags, true ) ) {
			ChangeTags::addTags( Util::getDepictsRevertTag(), null, $rev->getId() );
		}
	}

	/**
	 * @param array &$allowedTags
	 * @param array $tags
	 * @param User|null $user
	 */
	public function onChangeTagsAllowedAdd( &$allowedTags, $tags, $user ) {
		$allowedTags[] = Util::getDepictsTag();
		$allowedTags[] = Util::getDepictsCustomTag();
	}
}
