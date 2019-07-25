<?php

namespace MediaWiki\Extension\MachineVision\Handler;

use LocalFile;
use Psr\Log\LoggerAwareInterface;

/**
 * Interface for machine vision provider handlers.
 * Handlers are responsible for processing file uploads (fetching/producing MV data and
 * storing it).
 */
interface Handler extends LoggerAwareInterface {

	/**
	 * Process a file that has been successfully uploaded.
	 * @param LocalFile $file
	 */
	public function handleUploadComplete( LocalFile $file );

}
