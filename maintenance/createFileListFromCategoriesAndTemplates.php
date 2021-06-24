<?php

namespace MediaWiki\Extension\MachineVision\Maintenance;

use Maintenance;
use MediaWiki\Extension\MachineVision\Services;
use MediaWiki\Extension\MachineVision\TitleFilter;
use MediaWiki\MediaWikiServices;
use MWException;
use Wikimedia\Rdbms\IDatabase;

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
 * Create a list of files for which to request image labels, in a suitable format for passing as an
 * input file to fetchSuggestions.php.
 */
class CreateFileListFromCategoriesAndTemplates extends Maintenance {

	/** @var TitleFilter */
	private $titleFilter;

	/** @var IDatabase */
	private $dbr;

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'MachineVision' );
		$this->addDescription( 'Creates a list of files for which to request image labels based '
			. 'on their categories and/or templates, formatted for passing as input to '
			. 'fetchSuggestions.php' );
		$this->addOption( 'category', 'Add files in this category to the output file',
			false, true, 'c', true );
		$this->addOption( 'template', 'Add files with this template to the output file',
			false, true, 't', true );
		$this->addOption( 'outputFile', 'Filename to which to write results',
			true, true, 'o' );
		$this->addOption(
			'disableBlocklists',
			'Add files to file list even if they would normally be excluded because the category or template blocklists'
		);
		$this->addOption(
			'deepcat',
			'Include subcategories when adding files in a category'
		);
	}

	public function init() {
		$services = MediaWikiServices::getInstance();
		$extensionServices = new Services( $services );
		$this->titleFilter = $extensionServices->getTitleFilter();
		if ( $this->getOption( 'disableBlocklists', false ) ) {
			$this->titleFilter->disableBlocklists();
		} else {
			$this->titleFilter->enableBlocklists();
		}

		$extensionConfig = $extensionServices->getExtensionConfig();
		$loadBalancerFactory = $services->getDBLoadBalancerFactory();

		$cluster = $extensionConfig->get( 'MachineVisionCluster' );
		$database = $extensionConfig->get( 'MachineVisionDatabase' );

		$loadBalancer = $cluster
			? $loadBalancerFactory->getExternalLB( $cluster )
			: $loadBalancerFactory->getMainLB( $database );

		$this->dbr = $loadBalancer->getLazyConnectionRef( DB_REPLICA, [], $database );
	}

	/** @inheritDoc */
	public function execute() {
		$this->init();
		$categories = $this->getOption( 'category', [] );
		$deepcat = $this->getOption( 'deepcat', false );
		$templates = $this->getOption( 'template', [] );
		$outputFile = $this->getOption( 'outputFile' );
		$result = [];

		// Check outputFile path for validity before going any further
		$path = substr( $outputFile, 0, strrpos( $outputFile, '/' ) );
		if ( !is_dir( $path ) ) {
			throw new MWException( "Bad output file location: $outputFile" );
		}

		foreach ( $categories as $category ) {
			$result = array_merge(
				$result,
				$this->getPagesInCategory( $category, NS_FILE, [], $deepcat )
			);
		}
		foreach ( $templates as $template ) {
			$candidates = $this->dbr->selectFieldValues(
				[ 'page', 'templatelinks' ],
				'page_title',
				[
					'page_namespace' => NS_FILE,
					'tl_namespace' => NS_TEMPLATE,
					'tl_title' => $template,
				],
				__METHOD__,
				[],
				[
					'templatelinks' => [
						'LEFT JOIN',
						'tl_from = page_id',
					]
				]
			);
			$result = array_merge( $result, $this->titleFilter->filterGoodTitles( $candidates ) );
		}

		file_put_contents( $outputFile, array_map( static function ( $title ) {
			return "$title\n";
		}, array_unique( $result ) ) );
	}

	/**
	 * @param string $categoryName
	 * @param int $namespace
	 * @param array $categoriesToExclude Categories that have already been checked, and must be
	 * 	excluded to avoid recursion
	 * @param bool $includeSubcats
	 * @return array
	 */
	private function getPagesInCategory(
		string $categoryName,
		int $namespace,
		array $categoriesToExclude,
		bool $includeSubcats = false
	) {
		$pages = $this->dbr->selectFieldValues(
			[ 'page', 'categorylinks' ],
			'page_title',
			[
				'page_namespace' => $namespace,
				'cl_to' => $categoryName,
			],
			__METHOD__,
			[],
			[
				'categorylinks' => [
					'LEFT JOIN',
					'cl_from = page_id',
				]
			]
		);
		if ( $namespace == NS_FILE ) {
			$pages = $this->titleFilter->filterGoodTitles( $pages );
		}
		$categoriesToExclude[] = $categoryName;
		if ( $includeSubcats ) {
			$subcategories = $this->getPagesInCategory(
				$categoryName,
				NS_CATEGORY,
				$categoriesToExclude
			);
			foreach ( $subcategories as $subcategory ) {
				// guard against cyclic category relationships
				if ( !in_array( $subcategory, $categoriesToExclude ) ) {
					$pages = array_merge(
						$pages,
						$this->getPagesInCategory(
							$subcategory,
							$namespace,
							$categoriesToExclude,
							$includeSubcats
						)
					);
					$categoriesToExclude[] = $subcategory;
				}
			}
		}

		return array_unique( $pages );
	}
}

$maintClass = CreateFileListFromCategoriesAndTemplates::class;

$doMaintenancePath = RUN_MAINTENANCE_IF_MAIN;
if ( !( file_exists( $doMaintenancePath ) &&
	$doMaintenancePath === "$basePath/maintenance/doMaintenance.php" ) ) {
	throw new MWException( "Bad maintenance script location: $basePath" );
}

require_once RUN_MAINTENANCE_IF_MAIN;
