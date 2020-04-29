<?php

namespace MediaWiki\Extension\MachineVision;

use Article;
use BaseTemplate;
use ChangeTags;
use Content;
use DatabaseUpdater;
use DeferredUpdates;
use DomainException;
use EchoEvent;
use EchoNotificationMapper;
use Exception;
use File;
use IContextSource;
use LocalFile;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use MWException;
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
	 * @throws MWException
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/UploadComplete
	 */
	public static function onUploadComplete( UploadBase $uploadBase ) {
		$file = $uploadBase->getLocalFile();
		if ( !Util::isMediaTypeAllowed( $file->getMediaType() ) ) {
			return;
		}
		$extensionServices = new Services( MediaWikiServices::getInstance() );
		$extensionConfig = $extensionServices->getExtensionConfig();
		if ( !$extensionConfig->get( 'MachineVisionRequestLabelsOnUploadComplete' ) ) {
			return;
		}
		$userId = $file->getUser( 'id' );
		if ( $extensionConfig->get( 'MachineVisionTestersOnly' ) &&
			!self::isMachineVisionTester( User::newFromId( $userId ) ) ) {
			return;
		}
		$registry = $extensionServices->getHandlerRegistry();
		foreach ( $registry->getHandlers( $file ) as $provider => $handler ) {
			$handler->requestAnnotations( $provider, $file );
		}
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
		if ( $undidRevId ) {
			self::tagComputerAidedTaggingRevert( $undidRevId );
			return;
		}
		$services = MediaWikiServices::getInstance();
		if ( strpos( $summary, 'wbsetclaim-create' ) === false ) {
			return;
		}
		$title = $wikiPage->getTitle();
		if ( $title->getNamespace() !== NS_FILE ) {
			return;
		}
		$services = MediaWikiServices::getInstance();
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
		DeferredUpdates::addCallableUpdate( function () use ( $services, $title ) {
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
	 * @param User $agent The user who did the rollback
	 * @param RevisionRecord $newRev The revision the page was reverted back to
	 * @param RevisionRecord $oldRev The revision of the top edit that was reverted
	 */
	public static function onRollbackComplete( WikiPage $wikiPage,
							  User $agent,
							  RevisionRecord $newRev,
							  RevisionRecord $oldRev ) {
		self::tagComputerAidedTaggingRevert( $oldRev );
	}

	/**
	 * @param IContextSource $context
	 * @param array &$pageInfo
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/InfoAction
	 */
	public static function onInfoAction( IContextSource $context, array &$pageInfo ) {
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
	 * @param BaseTemplate $baseTemplate
	 * @param array &$toolbox
	 */
	public static function onBaseTemplateToolbox( BaseTemplate $baseTemplate, array &$toolbox ) {
		$extensionServices = new Services( MediaWikiServices::getInstance() );
		$extensionConfig = $extensionServices->getExtensionConfig();
		if ( $extensionConfig->get( 'MachineVisionAddToolboxLink' ) ) {
			$skin = $baseTemplate->getSkin();
			$toolbox['computer-aided-tagging'] = [
				'text' => $skin->msg( 'machinevision-machineaidedtagging' ),
				'href' => $skin::makeSpecialUrl( 'SuggestedTags' ),
				'id' => 't-computer-aided-tagging',
			];
		}
	}

	/**
	 * @param array &$tags
	 * @return bool true
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ListDefinedTags
	 */
	public static function onRegisterTags( array &$tags ) {
		$tags[] = Util::getDepictsTag();
		$tags[] = Util::getDepictsRevertTag();
		$tags[] = Util::getDepictsCustomTag();
		$tags[] = Util::getDepictsCustomRevertTag();
		return true;
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
	 * @throws Exception
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
	 * @param array &$notifications
	 * @param array &$notificationCategories
	 * @param array &$icons
	 */
	public static function onBeforeCreateEchoEvent(
		&$notifications,
		&$notificationCategories,
		&$icons
	) {
		// 1. Define notification categories: $notificationCategories[ '...' ]
		$notificationCategories[ 'machinevision' ] = [
			'priority' => 3,
			'tooltip' => 'echo-pref-tooltip-machinevision-suggestions-ready',
		];

		// 2. Define the event: $notifications[ '...' ]
		$notifications[ 'machinevision-suggestions-ready'] = [
			'category' => 'machinevision',
			'group' => 'positive',
			'section' => 'alert',
			'presentation-model' => Notifications\SuggestionsReadyPresentationModel::class,
			'user-locators' => [ function ( EchoEvent $event ) {
				// we don't want to spam users with notifications that essentially
				// do the same thing: direct them to the Special:SuggestedTags page
				// (while events could be bundled, they'd still unbundle once read,
				// and pollute their read notifications)
				// let's minimize the amount of notifications by only sending one
				// of this kind until it has been read

				$agent = $event->getAgent();
				if ( !$agent || $agent->isAnon() ) {
					// not a valid user
					return [];
				}

				$notificationMapper = new EchoNotificationMapper();
				$notifications = $notificationMapper->fetchUnreadByUser(
					$agent,
					1,
					null,
					[ $event->getType() ]
				);
				if ( count( $notifications ) > 0 ) {
					// already has an unread notification of this kind
					return [];
				}

				// has not yet been informed about these changes: send notification!
				return [ $agent->getId() => $agent ];
			} ],
			'canNotifyAgent' => true
		];

		$icons['suggestions-ready']['path'] = 'MachineVision/resources/icons/suggestions-ready-icon.svg';
	}

	/**
	 * @param EchoEvent $event
	 * @param string &$bundleString
	 * @return bool
	 */
	public static function onEchoGetBundleRules( EchoEvent $event, &$bundleString ) {
		if ( $event->getType() === 'machinevision-suggestions-ready' ) {
			$bundleString = 'machinevision';
		}

		return true;
	}

	/**
	 * @param array &$allowedTags
	 * @param array $tags
	 * @param User|null $user
	 * @return bool
	 */
	public static function onChangeTagsAllowedAdd( array &$allowedTags, array $tags, $user ) {
		$allowedTags[] = Util::getDepictsTag();
		$allowedTags[] = Util::getDepictsCustomTag();

		return true;
	}
}
