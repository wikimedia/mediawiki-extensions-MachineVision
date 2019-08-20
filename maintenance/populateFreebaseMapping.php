<?php

namespace MediaWiki\Extension\MachineVision\Maintenance;

use Maintenance;
use MediaWiki\Extension\MachineVision\Services;
use MediaWiki\MediaWikiServices;
use Wikimedia\Rdbms\IDatabase;

$basePath = getenv( 'MW_INSTALL_PATH' ) !== false
	? getenv( 'MW_INSTALL_PATH' )
	: __DIR__ . '/../../..';
require_once "$basePath/maintenance/Maintenance.php";

// Maintenance script for populating a table with Freebase to Wikidata ID mappings.
// Download the mapping file at https://developers.google.com/freebase/#freebase-wikidata-mappings,
// unzip, and provide the location to this script with the --mappingFile option.
class PopulateFreebaseMapping extends Maintenance {

	/** @var IDatabase */
	private $dbw;

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'MachineVision' );
		$this->addDescription( 'Populate a table of Freebase to Wikidata ID mappings' );
		$this->addOption( 'mappingFile', 'Location of the mapping file', true, true );
		$this->setBatchSize( 10000 );
	}

	public function init() {
		$services = MediaWikiServices::getInstance();
		$extensionServices = new Services( $services );

		$extensionConfig = $extensionServices->getExtensionConfig();
		$loadBalancerFactory = $services->getDBLoadBalancerFactory();

		$cluster = $extensionConfig->get( 'MachineVisionCluster' );
		$database = $extensionConfig->get( 'MachineVisionDatabase' );

		$loadBalancer = $cluster
			? $loadBalancerFactory->getExternalLB( $cluster )
			: $loadBalancerFactory->getMainLB( $database );

		$this->dbw = $loadBalancer->getLazyConnectionRef( DB_MASTER, [], $database );
	}

	/** @inheritDoc */
	public function execute() {
		$this->init();
		foreach ( $this->getMappingBatches() as $batch ) {
			$this->dbw->insert(
				'machine_vision_freebase_mapping',
				$batch,
				__METHOD__
			);
			$this->commitTransaction( $this->dbw, __METHOD__ );
			$this->output( '.' );
		}
		$this->output( "\nOK\n" );
	}

	private function getMappingBatches() {
		$filename = $this->getOption( 'mappingFile' );
		$f = ( $filename === '-' ) ? STDIN : fopen( $filename, 'rt' );
		if ( !$f ) {
			$this->fatalError( 'Could not open mapping file' );
		}

		$i = 0;
		$batch = [];
		while ( !feof( $f ) ) {
			$line = trim( fgets( $f ) );
			if ( substr( $line, 0, 1 ) === "#" || $line === '' ) {
				// ignore comments and empty lines
				continue;
			}
			$matches = [
				'freebase' => [],
				'wikidata' => [],
			];
			preg_match( '/m\.([0-9]|[a-z]|_)+/', $line, $matches['freebase'] );
			preg_match( '/Q[0-9]+/', $line, $matches['wikidata'] );
			// Update Freebase ID to use current Google Knowledge Graph ID format
			$oldFreebaseId = array_shift( $matches['freebase'] );
			$freebaseId = preg_replace( '/^m\./', '/m/', $oldFreebaseId );
			$wikidataId = array_shift( $matches['wikidata'] );
			$batch[] = [
				'mvfm_freebase_id' => $freebaseId,
				'mvfm_wikidata_id' => $wikidataId
			];
			$i++;
			if ( $i >= $this->getBatchSize() ) {
				$i = 0;
				yield $batch;
				$batch = [];
			}
		}

		if ( $batch ) {
			yield $batch;
		}
		if ( $f !== STDIN ) {
			fclose( $f );
		}
	}
}

$maintClass = PopulateFreebaseMapping::class;
require_once RUN_MAINTENANCE_IF_MAIN;
