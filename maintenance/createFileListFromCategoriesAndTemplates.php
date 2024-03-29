<?php

namespace MediaWiki\Extension\MachineVision\Maintenance;

use Maintenance;
use MediaWiki\Extension\MachineVision\Services;
use MediaWiki\Extension\MachineVision\TitleFilter;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\TitleValue;
use RuntimeException;
use Wikimedia\Rdbms\IDatabase;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

/**
 * Create a list of files for which to request image labels, in a suitable format for passing as an
 * input file to fetchSuggestions.php.
 */
class CreateFileListFromCategoriesAndTemplates extends Maintenance {

	/** @var TitleFilter */
	private $titleFilter;

	/** @var IDatabase */
	private $dbr;

	/** @var string */
	private $outputFile;

	/** @var array */
	private $categoriesAlreadyChecked = [];

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
		// Check outputFile path for validity before going any further
		$this->outputFile = $this->getOption( 'outputFile', '' );
		$path = substr( $this->outputFile, 0, strrpos( $this->outputFile, '/' ) );
		if ( !is_dir( $path ) ) {
			throw new RuntimeException( "Bad output file location: $this->outputFile" );
		}

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

		$this->dbr = $loadBalancer->getConnection( DB_REPLICA, [], $database );
	}

	/** @inheritDoc */
	public function execute() {
		$this->init();
		$categories = $this->getOption( 'category', [] );
		$deepcat = (bool)$this->getOption( 'deepcat', false );
		$templates = $this->getOption( 'template', [] );
		file_put_contents( $this->outputFile, '' );

		foreach ( $categories as $category ) {
			$this->processPagesInCategory( $category, $deepcat );
		}
		foreach ( $templates as $template ) {
			$targetConds = MediaWikiServices::getInstance()->getLinksMigration()->getLinksConditions(
				'templatelinks',
				new TitleValue( NS_TEMPLATE, $template )
			);
			$candidates = $this->dbr->selectFieldValues(
				[ 'page', 'templatelinks' ],
				'page_title',
				array_merge( $targetConds, [ 'page_namespace' => NS_FILE ] ),
				__METHOD__,
				[],
				[
					'templatelinks' => [
						'LEFT JOIN',
						'tl_from = page_id',
					]
				]
			);
			file_put_contents(
				$this->outputFile,
				array_map(
					static function ( $title ) {
						return "$title\n";
					},
					array_unique( $this->titleFilter->filterGoodTitles( $candidates ) )
				),
				FILE_APPEND
			);
		}
		// remove duplicates
		$allFiles = file( $this->outputFile );
		file_put_contents( $this->outputFile, array_unique( $allFiles ) );
	}

	/**
	 * @param string $categoryName
	 * @param bool $includeSubcats
	 */
	private function processPagesInCategory(
		string $categoryName,
		bool $includeSubcats = false
	) {
		$filesInCategory = $this->getCategoryMembers( $categoryName, NS_FILE );
		file_put_contents(
			$this->outputFile,
			implode( "\n", $filesInCategory ) . "\n",
			FILE_APPEND
		);

		$this->categoriesAlreadyChecked[] = $categoryName;
		if ( $includeSubcats ) {
			$subcategories = $this->getCategoryMembers(
				$categoryName,
				NS_CATEGORY
			);
			foreach ( $subcategories as $subcategory ) {
				// guard against cyclic category relationships
				if ( !in_array( $subcategory, $this->categoriesAlreadyChecked ) ) {
					$this->processPagesInCategory(
						$subcategory,
						$includeSubcats
					);
				}
			}
		}
	}

	private function getCategoryMembers( string $categoryName, int $namespace ): array {
		$members = array_unique(
			$this->dbr->selectFieldValues(
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
			)
		);
		if ( $namespace === NS_FILE ) {
			$members = $this->titleFilter->filterGoodTitles( $members );
		}
		return $members;
	}
}

$maintClass = CreateFileListFromCategoriesAndTemplates::class;
require_once RUN_MAINTENANCE_IF_MAIN;
