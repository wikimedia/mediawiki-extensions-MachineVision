<?php

namespace MediaWiki\Extension\MachineVision\Api;

use ApiBase;
use ApiPageSet;
use ApiQuery;
use ApiQueryGeneratorBase;
use MediaWiki\Extension\MachineVision\Repository;
use MediaWiki\Extension\MachineVision\Services;
use MediaWiki\MediaWikiServices;
use Title;

class ApiQueryUnreviewedImageLabels extends ApiQueryGeneratorBase {

	/** @var Repository */
	private $repository;

	/**
	 * @param ApiQuery $main
	 * @param string $moduleName
	 * @return self
	 */
	public static function factory( ApiQuery $main, $moduleName ) {
		$services = MediaWikiServices::getInstance();
		$extensionServices = new Services( $services );
		return new self(
			$main,
			$moduleName,
			$extensionServices->getRepository()
		);
	}

	/**
	 * ApiQueryUnreviewedImageLabels constructor.
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
		$result = array_map( function ( $item ) {
			$title = Title::newFromText( $item, NS_FILE );
			return [
				'title' => $title->getPrefixedDBkey(),
				'ns' => NS_FILE,
			];
			// FIXME: this uses FOR UPDATE but locks do not (and should not) last accross
			// different requests by a client. Also, this API does not need to be POSTed,
			// so using the master DB is not appropriate.
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
		// FIXME: this uses FOR UPDATE but locks do not (and should not) last accross
		// different requests by a client. Also, this API does not need to be POSTed,
		// so using the master DB is not appropriate.
		$resultPageSet->populateFromTitles( array_map( function ( $title ) {
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
