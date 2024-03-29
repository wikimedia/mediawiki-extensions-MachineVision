<?php

namespace MediaWiki\Extension\MachineVision\Maintenance;

use Maintenance;
use MediaWiki\Extension\MachineVision\Services;
use MediaWiki\MediaWikiServices;
use Wikimedia\Rdbms\IDatabase;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

// Maintenance script for removing all blacklisted suggestions from the MachineVision tables
// Should be run after the blacklist is updated
class RemoveBlacklistedSuggestions extends Maintenance {

	/** @var IDatabase */
	private $dbw;
	/** @var IDatabase */
	private $dbr;
	/** @var array */
	private $blacklist;

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'MachineVision' );
		$this->addDescription( 'Remove blacklisted suggestions from MachineVision tables' );
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

		$this->dbw = $loadBalancer->getConnection( DB_PRIMARY, [], $database );
		$this->dbr = $loadBalancer->getConnection( DB_REPLICA, [], $database );

		$this->blacklist = $extensionConfig->get( 'MachineVisionWikidataIdBlacklist' );
	}

	/** @inheritDoc */
	public function execute() {
		$this->init();
		if ( count( $this->blacklist ) == 0 ) {
			$this->output( "Blacklist is empty.\n" );
			return;
		}
		$continue = true;
		while ( $continue ) {
			$idsToDelete =
				$this->dbr->selectFieldValues( 'machine_vision_label', 'mvl_id',
					[ 'mvl_wikidata_id' => $this->blacklist ], __METHOD__,
					[ 'LIMIT' => $this->getBatchSize() ] );
			if ( count( $idsToDelete ) < 1 ) {
				$continue = false;
				break;
			}
			$this->beginTransaction( $this->dbw, __METHOD__ );
			$this->dbw->delete(
				'machine_vision_suggestion',
				[ 'mvs_mvl_id' => $idsToDelete ],
				__METHOD__
			);
			$this->dbw->delete(
				'machine_vision_label',
				[ 'mvl_id' => $idsToDelete ],
				__METHOD__
			);
			$this->commitTransaction( $this->dbw, __METHOD__ );
			$this->output( '.' );
		}
		$this->output( "\nOK\n" );
	}
}

$maintClass = RemoveBlacklistedSuggestions::class;
require_once RUN_MAINTENANCE_IF_MAIN;
