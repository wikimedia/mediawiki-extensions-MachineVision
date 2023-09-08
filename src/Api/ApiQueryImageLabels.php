<?php

namespace MediaWiki\Extension\MachineVision\Api;

use ApiBase;
use ApiQuery;
use ApiQueryBase;
use MediaWiki\Extension\MachineVision\Handler\LabelResolver;
use MediaWiki\Extension\MachineVision\Repository;
use RepoGroup;
use Wikimedia\ParamValidator\ParamValidator;

class ApiQueryImageLabels extends ApiQueryBase {

	private static $reviewStateNames = [
		Repository::REVIEW_UNREVIEWED => 'unreviewed',
		Repository::REVIEW_ACCEPTED => 'accepted',
		Repository::REVIEW_REJECTED => 'rejected',
		Repository::REVIEW_WITHHELD_POPULAR => 'withheld',
		Repository::REVIEW_WITHHELD_ALL => 'withheld',
		Repository::REVIEW_NOT_DISPLAYED => 'not-displayed',
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
		$this->dieWithError( 'machinevision-disabled-notice', null, null, 410 );
	}

	/** @inheritDoc */
	protected function getAllowedParams() {
		return [
			'state' => [
				ParamValidator::PARAM_TYPE => array_unique( array_values( self::$reviewStateNames ) ),
				ParamValidator::PARAM_ISMULTI => true,
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
