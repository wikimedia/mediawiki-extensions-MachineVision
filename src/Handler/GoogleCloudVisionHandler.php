<?php

namespace MediaWiki\Extension\MachineVision\Handler;

use JobQueueGroup;
use LocalFile;
use MediaWiki\Extension\MachineVision\Job\FetchGoogleCloudVisionAnnotationsJobFactory;
use MediaWiki\Extension\MachineVision\Repository;
use MediaWiki\Logger\LoggerFactory;
use RepoGroup;
use Throwable;

class GoogleCloudVisionHandler extends WikidataIdHandler {

	/** @var FetchGoogleCloudVisionAnnotationsJobFactory */
	private $fetchAnnotationsJobFactory;

	/** @var RepoGroup */
	private $repoGroup;

	/** @var JobQueueGroup */
	private $jobQueueGroup;

	/**
	 * @param FetchGoogleCloudVisionAnnotationsJobFactory $fetchAnnotationsJobFactory
	 * @param Repository $repository
	 * @param RepoGroup $repoGroup
	 * @param LabelResolver $labelResolver
	 * @param JobQueueGroup $jobQueueGroup
	 */
	public function __construct(
		FetchGoogleCloudVisionAnnotationsJobFactory $fetchAnnotationsJobFactory,
		Repository $repository,
		RepoGroup $repoGroup,
		LabelResolver $labelResolver,
		JobQueueGroup $jobQueueGroup
	) {
		parent::__construct( $repository, $labelResolver );
		$this->fetchAnnotationsJobFactory = $fetchAnnotationsJobFactory;
		$this->repoGroup = $repoGroup;
		$this->jobQueueGroup = $jobQueueGroup;

		$this->setLogger( LoggerFactory::getInstance( 'machinevision' ) );
	}

	/**
	 * See https://cloud.google.com/apis/design/errors for the API error format.
	 * @inheritDoc
	 */
	public function isTooManyRequestsError( Throwable $t ): bool {
		return $t->getCode() === 429;
	}

	/**
	 * Retrieves machine vision metadata about the image and stores it.
	 * @param string $provider provider name
	 * @param LocalFile $file
	 * @param int $priority priority value between -128 & 127
	 */
	public function requestAnnotations( string $provider, LocalFile $file, int $priority = 0 ): void {
		$fetchAnnotationsJob = $this->fetchAnnotationsJobFactory->createJob( $provider, $file, $priority );
		$this->jobQueueGroup->push( $fetchAnnotationsJob );
	}

}
