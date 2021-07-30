<?php

namespace MediaWiki\Extension\MachineVision\Maintenance;

use Maintenance;
use MediaWiki\Extension\MachineVision\Repository;
use MediaWiki\Extension\MachineVision\Services;
use MediaWiki\MediaWikiServices;
use MWException;
use Wikimedia\Rdbms\DBConnRef;
use Wikimedia\Rdbms\LBFactory;

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

class WithholdImages extends Maintenance {

	/** @var DBConnRef */
	private $dbw;

	/** @var DBConnRef */
	private $dbr;

	/** @var LBFactory */
	private $loadBalancerFactory;

	/** @var array */
	private $withholdList;

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'MachineVision' );
		$this->addDescription( 'Withhold images from Special:SuggestedTags based on config' );
		$this->setBatchSize( 10000 );
	}

	public function init() {
		$services = MediaWikiServices::getInstance();
		$extensionServices = new Services( $services );

		$extensionConfig = $extensionServices->getExtensionConfig();
		$this->loadBalancerFactory = $services->getDBLoadBalancerFactory();

		$cluster = $extensionConfig->get( 'MachineVisionCluster' );
		$database = $extensionConfig->get( 'MachineVisionDatabase' );

		$loadBalancer = $cluster
			? $this->loadBalancerFactory->getExternalLB( $cluster )
			: $this->loadBalancerFactory->getMainLB( $database );

		$this->dbw = $loadBalancer->getLazyConnectionRef( DB_PRIMARY, [], $database );
		$this->dbr = $loadBalancer->getLazyConnectionRef( DB_REPLICA, [], $database );

		$this->withholdList = $extensionConfig->get( 'MachineVisionWithholdImageList' );
	}

	/** @inheritDoc */
	public function execute() {
		$this->init();
		if ( count( $this->withholdList ) == 0 ) {
			$this->output( "No images to withhold in wgMachineVisionWithholdImageList.\n" );
			return;
		}
		foreach ( $this->withholdList as $wikidataId ) {
			$continue = true;
			while ( $continue ) {
				$imageIdsToWithhold = array_unique(
					$this->dbr->selectFieldValues(
						'machine_vision_label',
						'mvl_mvi_id',
						[
							'mvl_wikidata_id' => $wikidataId,
							'mvl_review' => [
								Repository::REVIEW_UNREVIEWED,
								Repository::REVIEW_WITHHELD_POPULAR
							],
						],
						__METHOD__,
						[ 'LIMIT' => $this->getBatchSize() ]
					)
				);
				if ( count( $imageIdsToWithhold ) < 1 ) {
					$continue = false;
					break;
				}
				$this->dbw->update(
					'machine_vision_label',
					[ 'mvl_review' => Repository::REVIEW_WITHHELD_ALL ],
					[
						'mvl_mvi_id' => $imageIdsToWithhold,
						'mvl_review' => [
							Repository::REVIEW_UNREVIEWED,
							Repository::REVIEW_WITHHELD_POPULAR
						],
					],
					__METHOD__
				);
				$this->loadBalancerFactory->waitForReplication();
				$this->output( '.' );
			}
		}
		$this->output( "\nOK\n" );
	}
}

$maintClass = WithholdImages::class;

$doMaintenancePath = RUN_MAINTENANCE_IF_MAIN;
if ( !( file_exists( $doMaintenancePath ) &&
	$doMaintenancePath === "$basePath/maintenance/doMaintenance.php" ) ) {
	throw new MWException( "Bad maintenance script location: $basePath" );
}

require_once RUN_MAINTENANCE_IF_MAIN;
