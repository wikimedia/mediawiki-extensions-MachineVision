<?php

namespace MediaWiki\Extension\MachineVision\Api;

use ApiQuery;
use ApiQueryBase;
use MediaWiki\Extension\MachineVision\Repository;

class ApiQueryUnreviewedImageCount extends ApiQueryBase {

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
		parent::__construct( $queryModule, $moduleName, 'uic' );
		$this->repository = $repository;
	}

	/**
	 * Entry point for executing the module
	 * @inheritDoc
	 */
	public function execute() {
		$this->dieWithError( 'machinevision-disabled-notice', null, null, 410 );
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

}
