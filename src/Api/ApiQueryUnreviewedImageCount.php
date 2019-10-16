<?php

namespace MediaWiki\Extension\MachineVision\Api;

use ApiQuery;
use ApiQueryBase;
use ApiUsageException;
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
		try {
			$totals = [ 'total' => $this->repository->getUnreviewedImageCount() ];
			if ( !$this->getUser()->isAnon() ) {
				$totals['user'] =
					$this->repository->getUnreviewedImageCountForUser( $this->getUser()->getId() );
			}
			$this->getResult()->addValue( 'query', 'unreviewedimagecount', $totals );
		} catch ( ApiUsageException $e ) {
			$this->dieWithException( $e );
		}
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
