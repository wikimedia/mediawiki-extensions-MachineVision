<?php

namespace MediaWiki\Extension\MachineVision\Handler;

use IContextSource;
use LocalFile;
use Psr\Log\LoggerAwareInterface;
use Throwable;
use User;

/**
 * Interface for machine vision provider handlers.
 * Handlers are responsible for processing file uploads (fetching/producing MV data and
 * storing it).
 */
interface Handler extends LoggerAwareInterface {

	/**
	 * Get the maximum requests per minute that this handler should make when running a script.
	 * @return int
	 */
	public function getMaxRequestsPerMinute(): int;

	/**
	 * Return true if the error is a 429 according to the provider's error output format.
	 * @param Throwable $t
	 * @return bool
	 */
	public function isTooManyRequestsError( Throwable $t ): bool;

	/**
	 * Request image annotations from a machine vision provider.
	 * @param string $provider provider name
	 * @param LocalFile $file
	 */
	public function requestAnnotations( string $provider, LocalFile $file ): void;

	/**
	 * Add extra data to the action=info page.
	 * @param IContextSource $context
	 * @param LocalFile $file
	 * @param array &$pageInfo See https://www.mediawiki.org/wiki/Manual:Hooks/InfoAction
	 */
	public function handleInfoAction( IContextSource $context, LocalFile $file, array &$pageInfo );

	/**
	 * Take any needed follow-up action when a label is human-reviewed.
	 * @param User $user
	 * @param LocalFile $file
	 * @param string $label label
	 * @param string $token CSRF token
	 * @param int $reviewState review state (as defined in Repository)
	 */
	public function handleLabelReview( User $user, LocalFile $file, $label, $token, $reviewState );

}
