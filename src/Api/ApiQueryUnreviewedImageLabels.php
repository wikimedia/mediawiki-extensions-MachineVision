<?php

namespace MediaWiki\Extension\MachineVision\Api;

use ApiBase;
use ApiPageSet;
use ApiQuery;
use ApiQueryGeneratorBase;
use MediaWiki\Extension\MachineVision\Repository;
use Title;

class ApiQueryUnreviewedImageLabels extends ApiQueryGeneratorBase {

	/** @var Repository */
	private $repository;

	/**
	 * @param ApiQuery $queryModule
	 * @param string $moduleName
	 * @param Repository $repository
	 */
	public function __construct(
		// API extra args
		ApiQuery $queryModule,
		$moduleName,
		// services
		Repository $repository
	) {
		parent::__construct( $queryModule, $moduleName, 'uil' );
		$this->repository = $repository;
	}

	/** @inheritDoc */
	public function execute() {
		$params = $this->extractRequestParams();
		$result = array_map( static function ( $item ) {
			$title = Title::newFromText( $item, NS_FILE );
			return [
				'title' => $title->getPrefixedDBkey(),
				'ns' => NS_FILE,
			];
		}, $this->repository->getTitlesWithUnreviewedLabels( $params['limit'],
			$params['uploader'] ) );
		$this->getResult()->addValue( 'query', 'unreviewedimagelabels', $result );
	}

	/**
	 * @inheritDoc
	 * @param ApiPageSet $resultPageSet
	 */
	public function executeGenerator( $resultPageSet ) {
		$params = $this->extractRequestParams();
		$resultPageSet->populateFromTitles( array_map( static function ( $title ) {
			return Title::newFromText( $title, NS_FILE );
		}, $this->repository->getTitlesWithUnreviewedLabels( $params['limit'],
			$params['uploader'] ) ) );
	}

	/** @inheritDoc */
	protected function getAllowedParams() {
		return [
			'uploader' => [ ApiBase::PARAM_TYPE => 'user' ],
			'limit' => [
				ApiBase::PARAM_TYPE => 'limit',
				ApiBase::PARAM_MIN => 1,
				ApiBase::PARAM_MAX => 10,
				ApiBase::PARAM_MAX2 => 100,
				ApiBase::PARAM_DFLT => 1,
			],
		];
	}

}
