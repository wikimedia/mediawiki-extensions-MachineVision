<?php

namespace MediaWiki\Extension\MachineVision\Maintenance;

use Maintenance;
use MediaWiki\Extension\MachineVision\Services;
use MediaWiki\Extension\MachineVision\TitleFilter;
use MediaWiki\MediaWikiServices;
use MWException;
use Wikimedia\Rdbms\IDatabase;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

/**
 * Create a list of files for which to request image labels based on a minimum number of incoming
 * image links. The resulting list is formatted for passing as input to fetchSuggestions.php.
 */
class CreateFileListFromGlobalImageLinks extends Maintenance {

	/** @var TitleFilter */
	private $titleFilter;

	/** @var IDatabase */
	private $dbr;

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'MachineVision' );
		$this->addDescription( 'Creates a list of files for which to request image labels based '
			. 'on their total incoming image links, formatted for passing as input to '
			. 'fetchSuggestions.php' );
		$this->addOption( 'minLinks', 'Minimum incoming links for a file to be added',
			true, true, 'm' );
		$this->addOption( 'namespace', 'Count incoming links for pages in this namespace '
			. '(default: NS_MAIN)',
			false, true, 'n' );
		$this->addOption( 'outputFile', 'Filename to which to write results',
			true, true, 'o' );
	}

	public function init() {
		$services = MediaWikiServices::getInstance();
		$extensionServices = new Services( $services );
		$this->titleFilter = $extensionServices->getTitleFilter();

		$extensionConfig = $extensionServices->getExtensionConfig();
		$loadBalancerFactory = $services->getDBLoadBalancerFactory();

		$cluster = $extensionConfig->get( 'MachineVisionCluster' );
		$database = $extensionConfig->get( 'MachineVisionDatabase' );

		$loadBalancer = $cluster
			? $loadBalancerFactory->getExternalLB( $cluster )
			: $loadBalancerFactory->getMainLB( $database );

		$this->dbr = $loadBalancer->getConnectionRef( DB_REPLICA, [], $database );
	}

	/** @inheritDoc */
	public function execute() {
		$this->init();
		$minLinks = $this->getOption( 'minLinks' );
		$namespace = $this->getOption( 'namespace' ) ?: NS_MAIN;
		$outputFile = $this->getOption( 'outputFile' );

		// Check outputFile path for validity before going any further
		$path = substr( $outputFile, 0, strrpos( $outputFile, '/' ) );
		if ( !is_dir( $path ) ) {
			throw new MWException( "Bad output file location: $outputFile" );
		}

		$result = [];

		$query = $this->dbr->select(
			'globalimagelinks',
			[ 'gil_to', 'COUNT(*)' ],
			[ 'gil_page_namespace_id' => $namespace ],
			__METHOD__,
			[ 'GROUP BY gil_to', "HAVING COUNT(*) >= $minLinks" ]
		);

		foreach ( $query as $row ) {
			$title = $row->gil_to;
			if ( $this->titleFilter->isGoodTitle( $title ) ) {
				$result[] = $title;
			}
		}

		file_put_contents( $outputFile, array_map( static function ( $title ) {
			return "$title\n";
		}, $result ) );
	}

}

$maintClass = CreateFileListFromGlobalImageLinks::class;
require_once RUN_MAINTENANCE_IF_MAIN;
