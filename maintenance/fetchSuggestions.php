<?php

namespace MediaWiki\Extension\MachineVision\Maintenance;

use LocalFile;
use Maintenance;
use MediaWiki\Extension\MachineVision\Handler\Registry;
use MediaWiki\Extension\MachineVision\Services;
use MediaWiki\MediaWikiServices;
use RepoGroup;
use Stiphle\Throttle\LeakyBucket;
use Throwable;
use Title;

$basePath = getenv( 'MW_INSTALL_PATH' ) !== false
	? getenv( 'MW_INSTALL_PATH' )
	: __DIR__ . '/../../..';
require_once "$basePath/maintenance/Maintenance.php";

/**
 * Maintenance script for fetching suggestions for specific files.
 */
class FetchSuggestions extends Maintenance {

	/** @var RepoGroup */
	private $repoGroup;

	/** @var Registry */
	private $handlerRegistry;

	/**
	 * Simple request throttle.
	 * @var LeakyBucket
	 */
	// @phan-suppress-next-line PhanUndeclaredTypeProperty
	private $throttle;

	/** @var int */
	private $backoffSeconds;

	/** @var int */
	private $numRetries;

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'MachineVision' );

		$this->addDescription( 'Fetch machine vision suggestions for image labels '
			. 'for a given set of files' );
		$this->addOption( 'filelist', 'File with a list of files to process, '
			. 'one per line, with or without namespace prefix', true, true );
		$this->setBatchSize( 100 );
	}

	/**
	 * Initialization code that should be in the constructor but can't due to the
	 * idiosyncratic loading order in Maintenance.
	 * @suppress PhanUndeclaredClassMethod
	 */
	public function init() {
		$services = MediaWikiServices::getInstance();
		$extensionServices = new Services( $services );
		$extensionConfig = $extensionServices->getExtensionConfig();
		$this->repoGroup = $services->getRepoGroup();
		$this->handlerRegistry = $extensionServices->getHandlerRegistry();
		$this->throttle = new LeakyBucket();
		$this->backoffSeconds = $extensionConfig->get( 'MachineVisionLabelRequestBackoffSeconds' );
		$this->numRetries = $extensionConfig->get( 'MachineVisionLabelRequestNumRetries' );
	}

	/** @inheritDoc */
	public function execute() {
		$this->init();
		$localRepo = $this->repoGroup->getLocalRepo();
		$processed = 0;
		foreach ( $this->getFilenameBatches() as $filenameBatch ) {
			$this->output( 'processing ' . $filenameBatch[0] . ' ... ' . end( $filenameBatch ) . "\n" );
			$titles = array_map( function ( $filename ) {
				return Title::newFromText( $filename, NS_FILE );
			}, $filenameBatch );
			$files = $localRepo->findFiles( $titles );
			foreach ( $files as $file ) {
				$this->fetchForFile( $file );
				$processed++;
			}
			$this->commitTransaction( $this->getDB( DB_MASTER ), __METHOD__ );
		}
		$this->output( "Done, processed $processed files\n" );
	}

	private function getFilenameBatches() {
		$filename = $this->getOption( 'filelist' );
		$f = ( $filename === '-' ) ? STDIN : fopen( $filename, 'rt' );
		if ( !$f ) {
			$this->fatalError( 'Could not open file list' );
		}

		$i = 0;
		$filenames = [];
		while ( !feof( $f ) ) {
			$filename = trim( fgets( $f ) );
			if ( $filename === '' ) {
				// ignore empty last line
				continue;
			}
			$filenames[] = $filename;
			$i++;
			if ( $i >= $this->getBatchSize() ) {
				$i = 0;
				yield $filenames;
				$filenames = [];
			}
		}

		if ( $filenames ) {
			yield $filenames;
		}
		if ( $f !== STDIN ) {
			fclose( $f );
		}
	}

	/**
	 * @param LocalFile $file
	 * @suppress PhanUndeclaredClassMethod
	 * TODO: Directly make requests in this maintenance script, don't use the JobQueue
	 */
	private function fetchForFile( LocalFile $file ) {
		foreach ( $this->handlerRegistry->getHandlers( $file ) as $provider => $handler ) {
			if ( $handler->getMaxRequestsPerMinute() ) {
				$this->throttle->throttle(
					"$provider.label.request",
					$handler->getMaxRequestsPerMinute(),
					60000
				);
			}
			try {
				$handler->requestAnnotations( $provider, $file );
			} catch ( Throwable $t ) {
				$retries = $this->numRetries;
				while ( $retries ) {
					if ( $handler->isTooManyRequestsError( $t ) ) {
						sleep( $this->backoffSeconds );
						try {
							$handler->requestAnnotations( $provider, $file );
							return;
						} catch ( Throwable $t ) {
							if ( $handler->isTooManyRequestsError( $t ) ) {
								$retries--;
								continue;
							}
							throw $t;
						}
					}
				}
				throw $t;
			}
		}
	}

}

$maintClass = FetchSuggestions::class;
require_once RUN_MAINTENANCE_IF_MAIN;
