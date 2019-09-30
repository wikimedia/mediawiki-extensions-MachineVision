<?php

namespace MediaWiki\Extension\MachineVision\Maintenance;

use Maintenance;
use MediaWiki\Extension\MachineVision\Services;
use MediaWiki\Extension\MachineVision\TitleFilter;
use MediaWiki\MediaWikiServices;
use RepoGroup;
use Wikimedia\Rdbms\IDatabase;

$basePath = getenv( 'MW_INSTALL_PATH' ) !== false
	? getenv( 'MW_INSTALL_PATH' )
	: __DIR__ . '/../../..';
require_once "$basePath/maintenance/Maintenance.php";

/**
 * Create a list of files for which to request image labels, in a suitable format for passing as an
 * input file to fetchSuggestions.php.
 */
class CreateFileListFromCategoriesAndTemplates extends Maintenance {

	/** @var RepoGroup */
	private $repoGroup;

	/** @var TitleFilter */
	private $titleFilter;

	/** @var IDatabase */
	private $dbr;

	/** @var int */
	private $minImageWidth;

	/** @var string[] */
	private $categoryBlacklist;

	/** @var string[] */
	private $templateBlacklist;

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
	}

	public function init() {
		$services = MediaWikiServices::getInstance();
		$extensionServices = new Services( $services );
		$this->repoGroup = $services->getRepoGroup();
		$this->titleFilter = TitleFilter::instance();

		$extensionConfig = $extensionServices->getExtensionConfig();
		$this->minImageWidth = $extensionConfig->get( 'MachineVisionMinImageWidth' );
		$this->categoryBlacklist = $extensionConfig->get( 'MachineVisionCategoryBlacklist' );
		$this->templateBlacklist = $extensionConfig->get( 'MachineVisionTemplateBlacklist' );

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
		$categories = $this->getOption( 'category' );
		$templates = $this->getOption( 'template' );
		$outputFile = $this->getOption( 'outputFile' );
		$result = [];

		foreach ( $categories as $category ) {
			$candidates = $this->dbr->selectFieldValues(
				[ 'page', 'categorylinks' ],
				'page_title',
				[
					'page_namespace' => NS_FILE,
					'cl_to' => $category,
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
			$result = array_merge(
				$result,
				$this->titleFilter->filterGoodTitles(
					$candidates,
					$this->repoGroup->getLocalRepo(),
					$this->minImageWidth,
					$this->categoryBlacklist,
					$this->templateBlacklist
				)
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
			$result = array_merge(
				$result,
				$this->titleFilter->filterGoodTitles(
					$candidates,
					$this->repoGroup->getLocalRepo(),
					$this->minImageWidth,
					$this->categoryBlacklist,
					$this->templateBlacklist
				)
			);
		}

		file_put_contents( $outputFile, array_map( function ( $title ) {
			return "$title\n";
		}, array_unique( $result ) ) );
	}

}

$maintClass = CreateFileListFromCategoriesAndTemplates::class;
require_once RUN_MAINTENANCE_IF_MAIN;
