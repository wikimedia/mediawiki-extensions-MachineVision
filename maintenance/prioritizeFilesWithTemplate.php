<?php

namespace MediaWiki\Extension\MachineVision\Maintenance;

use Maintenance;
use MediaWiki\Extension\MachineVision\Repository;
use MediaWiki\Extension\MachineVision\Services;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\TitleValue;
use Wikimedia\Rdbms\IDatabase;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

// Find files with a particular template in the CAT queue, and prioritise for classification
class PrioritizeFilesWithTemplate extends Maintenance {

	/** @var IDatabase */
	private $dbw;
	/** @var IDatabase */
	private $dbr;

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'MachineVision' );
		$this->addDescription( 'Prioritize files with a particular template for MachineVision tagging' );
		$this->addOption( 'template', 'Name of the template', true, true );
		$this->addOption( 'priority', 'Priority for files containing the template (-128 to 127)', true, true );
		$this->setBatchSize( 1000 );
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
	}

	/** @inheritDoc */
	public function execute() {
		$this->init();

		$lastMviId = 0;
		do {
			$unreviewed = [];
			$unreviewedResult = $this->dbr->select(
				[
					'page',
					'image',
					'mvi' => 'machine_vision_image',
					'mvl' => 'machine_vision_label',
				],
				[ 'page_id', 'mvi.mvi_id' ],
				[
					'mvl.mvl_review' => Repository::REVIEW_UNREVIEWED,
					// Only prioritise images that haven't already been prioritised
					// (i.e. that have the default priority, which is zero)
					'mvi.mvi_priority' => 0,
					'mvi.mvi_id>' . $lastMviId,
				],
				__METHOD__,
				[
					'LIMIT' => $this->getBatchSize()
				],
				[
					'image' => [ 'JOIN', [ 'image.img_name=page.page_title', 'page.page_namespace' => NS_FILE ] ],
					'mvi' => [ 'JOIN', 'mvi.mvi_sha1=image.img_sha1' ],
					'mvl' => [ 'JOIN', 'mvl.mvl_mvi_id=mvi.mvi_id' ],
				]
			);

			if ( $unreviewedResult->numRows() < 1 ) {
				break;
			}

			foreach ( $unreviewedResult as $row ) {
				$unreviewed[ $row->page_id ] = $row->mvi_id;
			}
			$lastMviId = max( $unreviewed );
			$targetConds = MediaWikiServices::getInstance()->getLinksMigration()->getLinksConditions(
				'templatelinks',
				new TitleValue( NS_TEMPLATE, $this->getOption( 'template' ) )
			);

			$uncategorizedPageIds = array_unique(
				$this->dbr->selectFieldValues(
					'templatelinks',
					'tl_from',
					array_merge( $targetConds, [ 'tl_from' => array_keys( $unreviewed ) ] ),
					__METHOD__,
					[],
					[]
				)
			);

			$uncategorizedMviIds = array_filter(
				$unreviewed,
				static function ( $key ) use ( $uncategorizedPageIds ) {
					return in_array( $key, $uncategorizedPageIds );
				},
				ARRAY_FILTER_USE_KEY
			);

			if ( count( $uncategorizedMviIds ) > 0 ) {
				$this->beginTransaction( $this->dbw, __METHOD__ );
				$this->dbw->update(
					'machine_vision_image',
					[ 'mvi_priority' => $this->getOption( 'priority' ) ],
					[ 'mvi_id' => $uncategorizedMviIds ],
					__METHOD__
				);
				$this->commitTransaction( $this->dbw, __METHOD__ );
				$this->output( '.' );
				$this->waitForReplication();
			}
		} while ( count( $unreviewed ) > 0 );

		$this->output( "\nOK\n" );
	}
}

$maintClass = PrioritizeFilesWithTemplate::class;
require_once RUN_MAINTENANCE_IF_MAIN;
