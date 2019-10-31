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

	/**
	 * FetchGoogleCloudVisionAnnotationsJobFactory constructor.
	 * @param bool $sendFileContents
	 * @param array $safeSearchLimits
	 * @param bool $proxy
	 */
	public function __construct( bool $sendFileContents, array $safeSearchLimits, $proxy ) {
		$this->sendFileContents = $sendFileContents;
		$this->safeSearchLimits = $safeSearchLimits;
		$this->proxy = $proxy;
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
			]
		);
	}

}
