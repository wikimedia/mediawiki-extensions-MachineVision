<?php

namespace MediaWiki\Extension\MachineVision\Job;

use LocalFile;

class FetchGoogleCloudVisionAnnotationsJobFactory {

	/** @var bool */
	private $sendFileContents;

	/** @var array */
	private $safeSearchLimits;

	/** @var string|bool */
	private $proxy;

	/** @var int */
	private $delay;

	/**
	 * FetchGoogleCloudVisionAnnotationsJobFactory constructor.
	 * @param bool $sendFileContents
	 * @param array $safeSearchLimits
	 * @param bool|string $proxy
	 * @param int $delay
	 */
	public function __construct(
		bool $sendFileContents,
		array $safeSearchLimits,
		$proxy,
		int $delay
	) {
		$this->sendFileContents = $sendFileContents;
		$this->safeSearchLimits = $safeSearchLimits;
		$this->proxy = $proxy;
		$this->delay = $delay;
	}

	/**
	 * Create a new GCV annotation fetching job.
	 * @param string $provider provider DB string
	 * @param LocalFile $file
	 * @return FetchGoogleCloudVisionAnnotationsJob
	 */
	public function createJob( string $provider, LocalFile $file ):
	FetchGoogleCloudVisionAnnotationsJob {
		return new FetchGoogleCloudVisionAnnotationsJob(
			'fetchGoogleCloudVisionAnnotations',
			[
				'title' => $file->getTitle()->getDBkey(),
				'namespace' => $file->getTitle()->getNamespace(),
				'provider' => $provider,
				'sendFileContents' => $this->sendFileContents,
				'safeSearchLimits' => $this->safeSearchLimits,
				'proxy' => $this->proxy,
				'jobReleaseTimestamp' => wfTimestamp() + $this->delay,
			]
		);
	}

}
