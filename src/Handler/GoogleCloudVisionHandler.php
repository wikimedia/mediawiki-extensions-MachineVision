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

	/**
	 * Maximum requests per minute to send to the Google Cloud Vision API when running the label
	 * fetcher script.
	 * @var int
	 */
	private $maxRequestsPerMinute;

	/**
	 * @param FetchGoogleCloudVisionAnnotationsJobFactory $fetchAnnotationsJobFactory
	 * @param Repository $repository
	 * @param RepoGroup $repoGroup
	 * @param WikidataDepictsSetter $depictsSetter
	 * @param LabelResolver $labelResolver
	 * @param int $maxRequestsPerMinute
	 */
	public function __construct(
		FetchGoogleCloudVisionAnnotationsJobFactory $fetchAnnotationsJobFactory,
		Repository $repository,
		RepoGroup $repoGroup,
		WikidataDepictsSetter $depictsSetter,
		LabelResolver $labelResolver,
		$maxRequestsPerMinute = 0
	) {
		parent::__construct( $repository, $depictsSetter, $labelResolver );
		$this->fetchAnnotationsJobFactory = $fetchAnnotationsJobFactory;
		$this->repoGroup = $repoGroup;
		$this->maxRequestsPerMinute = $maxRequestsPerMinute;

		$this->setLogger( LoggerFactory::getInstance( 'machinevision' ) );
	}

	/** @inheritDoc */
	public function getMaxRequestsPerMinute(): int {
		return $this->maxRequestsPerMinute;
	}

	/**
	 * See https://cloud.google.com/apis/design/errors for the API error format.
	 * @inheritDoc
	 */
	public function isTooManyRequestsError( Throwable $t ): bool {
		return $t->getCode() === 429;
	}

	/**
	 * Handle a new upload, after the core handling has been completed.
	 * Retrieves machine vision metadata about the image and stores it.
	 * @param string $provider provider name
	 * @param LocalFile $file
	 */
	public function handleUploadComplete( $provider, LocalFile $file ) {
		$fetchAnnotationsJob = $this->fetchAnnotationsJobFactory->createJob( $provider, $file );
		JobQueueGroup::singleton()->push( $fetchAnnotationsJob );
	}

}
