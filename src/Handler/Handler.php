<?php

namespace MediaWiki\Extension\MachineVision\Handler;

use IContextSource;
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

	/**
	 * Add extra data to the action=info page.
	 * @param IContextSource $context
	 * @param LocalFile $file
	 * @param array &$pageInfo See https://www.mediawiki.org/wiki/Manual:Hooks/InfoAction
	 */
	public function handleInfoAction( IContextSource $context, LocalFile $file, array &$pageInfo );

}
