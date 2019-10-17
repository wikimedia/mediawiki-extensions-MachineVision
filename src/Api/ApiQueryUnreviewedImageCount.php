<?php

namespace MediaWiki\Extension\MachineVision\Api;

use ApiQuery;
use ApiQueryBase;
use MediaWiki\Extension\MachineVision\Repository;
use MediaWiki\Extension\MachineVision\Services;
use MediaWiki\MediaWikiServices;

class ApiQueryUnreviewedImageCount extends ApiQueryBase {

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
		parent::__construct( $queryModule, $moduleName, 'uic' );
		$this->repository = $repository;
	}

	/**
	 * Entry point for executing the module
	 * @inheritDoc
	 */
	public function execute() {
		$totals = [];
		if ( !$this->getUser()->isAnon() ) {
			$userId = $this->getUser()->getId();
			$totals['user'] = $this->repository->getUnreviewedImageCountForUser( $userId );
		}
		$this->getResult()->addValue( 'query', 'unreviewedimagecount', $totals );
	}

	/**
	 * @inheritDoc
	 * @return array
	 */
	protected function getExamplesMessages() {
		return [
			"action=query&meta=unreviewedimagecount" =>
				'apihelp-query+unreviewedimagecount-example',
		];
	}

	/**
	 * @inheritDoc
	 * @return bool
	 */
	public function isInternal() {
		return true;
	}

}
