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
	 * @param int $priority priority value between -128 & 127
	 * @return FetchGoogleCloudVisionAnnotationsJob
	 */
	public function createJob(
		string $provider,
		LocalFile $file,
		int $priority = 0
	): FetchGoogleCloudVisionAnnotationsJob {
		$params = [
			'title' => $file->getTitle()->getDBkey(),
			'namespace' => $file->getTitle()->getNamespace(),
			'provider' => $provider,
			'priority' => $priority,
		];
		if ( $this->delay ) {
			$params['jobReleaseTimestamp'] = (int)wfTimestamp() + $this->delay;
		}
		return new FetchGoogleCloudVisionAnnotationsJob(
			'fetchGoogleCloudVisionAnnotations',
			$params
		);
	}

}
