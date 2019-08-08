<?php

namespace MediaWiki\Extension\MachineVision\Api;

use ApiBase;
use ApiQuery;
use ApiQueryBase;
use ApiResult;
use LocalFile;
use MediaWiki\Extension\MachineVision\Repository;
use MediaWiki\Extension\MachineVision\Services;
use MediaWiki\MediaWikiServices;
use MediaWiki\Storage\NameTableStore;
use RepoGroup;
use Title;

class ApiQueryImageLabels extends ApiQueryBase {

	private static $reviewStateNames = [
		Repository::REVIEW_UNREVIEWED => 'unreviewed',
		Repository::REVIEW_ACCEPTED => 'accepted',
		Repository::REVIEW_REJECTED => 'rejected',
		Repository::REVIEW_SKIPPED => 'skipped',
	];

	/** @var RepoGroup */
	private $repoGroup;

	/** @var NameTableStore */
	private $nameTableStore;

	/**
	 * @param ApiQuery $query
	 * @param string $moduleName
	 * @return self
	 */
	public static function factory( ApiQuery $query, $moduleName ) {
		$services = MediaWikiServices::getInstance();
		$extensionServices = new Services( $services );
		return new self( $query, $moduleName, $services->getRepoGroup(),
			$extensionServices->getNameTableStore() );
	}

	/**
	 * @param ApiQuery $query
	 * @param string $moduleName
	 * @param RepoGroup $repoGroup
	 * @param NameTableStore $nameTableStore
	 */
	public function __construct(
		// API extra args
		ApiQuery $query,
		$moduleName,
		// services
		RepoGroup $repoGroup,
		NameTableStore $nameTableStore
	) {
		parent::__construct( $query, $moduleName, 'il' );
		$this->repoGroup = $repoGroup;
		$this->nameTableStore = $nameTableStore;
	}

	/** @inheritDoc */
	public function execute() {
		// TODO move some of this to Handler?
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

		$res = $this->getDB()->select(
			'machine_vision_label',
			[ 'mvl_image_sha1', 'mvl_wikidata_id', 'mvl_review' ],
			[ 'mvl_image_sha1' => array_values( $filenameToSha1 ) ],
			__METHOD__
		);

		$apiResult = $this->getResult();
		$data = [];
		foreach ( $res as $row ) {
			$pageId = $filenameToPageId[$sha1ToFilename[$row->mvl_image_sha1]];
			$state = self::$reviewStateNames[$row->mvl_review];

			if ( $params['state'] !== null && !in_array( $state, $params['state'] ) ) {
				continue;
			}

			$data[$pageId][$row->mvl_wikidata_id]['wikidata_id'] = $row->mvl_wikidata_id;
			$data[$pageId][$row->mvl_wikidata_id]['provider'][]
				= $this->nameTableStore->getName( (int)$row->mvl_provider_id );
			// There could be all kinds of weirdness if the same label is sent by multiple providers
			// and reviewed differently. We assume DB writes are handled in a way to avoid that.
			$data[$pageId][$row->mvl_wikidata_id]['state'] = self::$reviewStateNames[$row->mvl_review];
		}
		asort( $data );
		foreach ( $data as $pageId => $pageData ) {
			asort( $pageData );
			$pageData = array_values( $pageData );
			foreach ( $pageData as &$labelData ) {
				sort( $labelData['provider'] );
				ApiResult::setIndexedTagName( $labelData['provider'], 'provider' );
			}
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
		return 'https://www.mediawiki.org/wiki/Extension:MachineVision#API';
	}

}
