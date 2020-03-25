<?php

namespace MediaWiki\Extension\MachineVision\Api;

use ApiBase;
use ApiQuery;
use ApiQueryBase;
use ApiResult;
use LocalFile;
use MediaWiki\Extension\MachineVision\Handler\LabelResolver;
use MediaWiki\Extension\MachineVision\Repository;
use MediaWiki\Extension\MachineVision\Services;
use MediaWiki\MediaWikiServices;
use RepoGroup;
use Title;

class ApiQueryImageLabels extends ApiQueryBase {

	private static $reviewStateNames = [
		Repository::REVIEW_UNREVIEWED => 'unreviewed',
		Repository::REVIEW_ACCEPTED => 'accepted',
		Repository::REVIEW_REJECTED => 'rejected',
		Repository::REVIEW_WITHHELD => 'withheld',
	];

	/** @var RepoGroup */
	private $repoGroup;

	/** @var LabelResolver */
	private $labelResolver;

	/** @var Repository */
	private $repository;

	/**
	 * @param ApiQuery $query
	 * @param string $moduleName
	 * @return self
	 */
	public static function factory( ApiQuery $query, $moduleName ) {
		$services = MediaWikiServices::getInstance();
		$extensionServices = new Services( $services );
		return new self( $query, $moduleName, $services->getRepoGroup(),
			$extensionServices->getLabelResolver(), $extensionServices->getRepository() );
	}

	/**
	 * @param ApiQuery $query
	 * @param string $moduleName
	 * @param RepoGroup $repoGroup
	 * @param LabelResolver $labelResolver
	 * @param Repository $repository
	 */
	public function __construct(
		// API extra args
		ApiQuery $query,
		$moduleName,
		// services
		RepoGroup $repoGroup,
		LabelResolver $labelResolver,
		Repository $repository
	) {
		parent::__construct( $query, $moduleName, 'il' );
		$this->repoGroup = $repoGroup;
		$this->labelResolver = $labelResolver;
		$this->repository = $repository;
	}

	/** @inheritDoc */
	public function execute() {
		// TODO: move some of this to Handler?
		$params = $this->extractRequestParams();
		$continuePageId = $params['continue'] ?? -1 * PHP_INT_MAX;

		// For now, we handle local images only. Still, use an approach that would work for
		// remote images as well - too many things to go wrong with naked joins.
		$titlesByPageId = array_filter( $this->getPageSet()->getGoodTitles(),
			function ( Title $title ) use ( $continuePageId ) {
				return $title->inNamespace( NS_FILE ) && ( $title->getArticleID() >= $continuePageId );
			} );
		$filenameToPageId = array_flip( array_map( function ( Title $title ) {
			return $title->getDBkey();
		}, $titlesByPageId ) );
		$filesByFilename = $this->repoGroup->getLocalRepo()->findFiles( $titlesByPageId );
		$filenameToSha1 = array_map( function ( LocalFile $file ) {
			return $file->getSha1();
		}, $filesByFilename );
		if ( !$filenameToSha1 ) {
			// Nothing to do, and the SQL query would error out on 'mvl_image_sha1 IN ()',
			return;
		}
		$sha1ToFilename = array_flip( $filenameToSha1 );

		$res = $this->repository->getLabels( array_values( $filenameToSha1 ) );

		$apiResult = $this->getResult();
		$data = [];
		foreach ( $res as $row ) {
			$pageId = $filenameToPageId[$sha1ToFilename[$row['sha1']]];
			$state = self::$reviewStateNames[$row['review']];

			if ( $params['state'] !== null && !in_array( $state, $params['state'] ) ) {
				continue;
			}

			$data[$pageId][$row['wikidata_id']]['wikidata_id'] = $row['wikidata_id'];
			$data[$pageId][$row['wikidata_id']]['state'] = self::$reviewStateNames[$row['review']];
		}

		foreach ( $data as $pageId => $pageData ) {
			$ids = array_keys( $pageData );
			$labels = $this->labelResolver->resolve( $this->getContext(), $ids );
			foreach ( $labels as $id => $label ) {
				$data[$pageId][$id]['label'] = $label;
			}
		}

		asort( $data );
		foreach ( $data as $pageId => $pageData ) {
			$pageData = array_values( $pageData );
			ApiResult::setIndexedTagName( $pageData, 'label' );
			$fit = $apiResult->addValue( [ 'query', 'pages', $pageId ], $this->getModuleName(),
				 $pageData );
			if ( !$fit ) {
				$this->setContinueEnumParameter( 'continue', $pageId );
				return;
			}
		}
	}

	/** @inheritDoc */
	protected function getAllowedParams() {
		return [
			'state' => [
				ApiBase::PARAM_TYPE => array_values( self::$reviewStateNames ),
				ApiBase::PARAM_ISMULTI => true,
				ApiBase::PARAM_HELP_MSG_PER_VALUE => [],
			],
			// Given the small number of labels per image, a limit parameter seems not worth the effort.
			'continue' => [
				ApiBase::PARAM_HELP_MSG => 'api-help-param-continue',
			],
		];
	}

	/** @inheritDoc */
	protected function getExamplesMessages() {
		return [
			'action=query&prop=imagelabels&titles=File:Example.png' => 'apihelp-query+imagelabels-example-1',
		];
	}

	/** @inheritDoc */
	public function getHelpUrls() {
		return 'https://www.mediawiki.org/wiki/Special:MyLanguage/Extension:MachineVision#API';
	}

}
