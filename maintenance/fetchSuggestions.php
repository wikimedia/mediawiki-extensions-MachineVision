<?php

namespace MediaWiki\Extension\MachineVision\Maintenance;

use LocalFile;
use Maintenance;
use MediaWiki\Extension\MachineVision\Client\GoogleCloudVisionClient;
use MediaWiki\Extension\MachineVision\Handler\Registry;
use MediaWiki\Extension\MachineVision\Services;
use MediaWiki\MediaWikiServices;
use MWException;
use RepoGroup;
use Throwable;
use Title;

// Security: Disable all stream wrappers and reenable individually as needed
foreach ( stream_get_wrappers() as $wrapper ) {
	stream_wrapper_unregister( $wrapper );
}

stream_wrapper_restore( 'file' );
$basePath = getenv( 'MW_INSTALL_PATH' );
if ( $basePath ) {
	if ( !is_dir( $basePath )
		|| strpos( $basePath, '..' ) !== false
		|| strpos( $basePath, '~' ) !== false
	) {
		throw new MWException( "Bad MediaWiki install path: $basePath" );
	}
} else {
	$basePath = __DIR__ . '/../../..';
}
require_once "$basePath/maintenance/Maintenance.php";

/**
 * Maintenance script for fetching suggestions for specific files.
 */
class FetchSuggestions extends Maintenance {

	/** @var GoogleCloudVisionClient */
	private $client;

	/** @var RepoGroup */
	private $repoGroup;

	/** @var Registry */
	private $handlerRegistry;

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
		$this->addOption( 'priority', 'Priority of the images (between -128 & 127)', false, true );
		$this->setBatchSize( 100 );
	}

	/**
	 * Initialization code that should be in the constructor but can't due to the
	 * idiosyncratic loading order in Maintenance.
	 */
	public function init() {
		$services = MediaWikiServices::getInstance();
		$extensionServices = new Services( $services );
		$extensionConfig = $extensionServices->getExtensionConfig();
		$this->client = $extensionServices->getGoogleCloudVisionClient();
		$this->repoGroup = $services->getRepoGroup();
		$this->handlerRegistry = $extensionServices->getHandlerRegistry();
		$this->backoffSeconds = $extensionConfig->get( 'MachineVisionLabelRequestBackoffSeconds' );
		$this->numRetries = $extensionConfig->get( 'MachineVisionLabelRequestNumRetries' );
	}

	/** @inheritDoc */
	public function execute() {
		$this->init();
		$localRepo = $this->repoGroup->getLocalRepo();
		$processed = 0;
		stream_wrapper_restore( 'php' );
		$priority = (int)$this->getOption( 'priority' );
		foreach ( $this->getFilenameBatches() as $filenameBatch ) {
			$this->output( 'processing ' . $filenameBatch[0] . ' ... ' . end( $filenameBatch ) . "\n" );
			$titles = array_map( function ( $filename ) {
				return Title::newFromText( $filename, NS_FILE );
			}, $filenameBatch );
			$files = $localRepo->findFiles( $titles );
			$this->beginTransaction( $this->getDB( DB_MASTER ), __METHOD__ );
			foreach ( $files as $file ) {
				$this->fetchForFile( $file, $priority );
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
	 * @param int $priority priority value between -128 & 127
	 */
	private function fetchForFile( LocalFile $file, $priority ) {
		foreach ( $this->handlerRegistry->getHandlers( $file ) as $provider => $handler ) {
			try {
				$this->client->fetchAnnotations( $provider, $file, $priority );
			} catch ( Throwable $t ) {
				$retries = $this->numRetries;
				while ( $retries ) {
					if ( $handler->isTooManyRequestsError( $t ) ) {
						sleep( $this->backoffSeconds );
						try {
							$this->client->fetchAnnotations( $provider, $file, $priority );
							return;
						} catch ( Throwable $t ) {
							if ( $handler->isTooManyRequestsError( $t ) ) {
								$retries--;
								continue;
							}
							throw $t;
						}
					}
					throw $t;
				}
				throw $t;
			}
		}
	}

}

$maintClass = FetchSuggestions::class;

$doMaintenancePath = RUN_MAINTENANCE_IF_MAIN;
if ( !( file_exists( $doMaintenancePath ) &&
	$doMaintenancePath === "$basePath/maintenance/doMaintenance.php" ) ) {
	throw new MWException( "Bad maintenance script location: $basePath" );
}

require_once RUN_MAINTENANCE_IF_MAIN;
