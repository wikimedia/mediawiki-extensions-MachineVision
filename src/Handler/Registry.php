<?php

namespace MediaWiki\Extension\MachineVision\Handler;

use LocalFile;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Wikimedia\ObjectFactory;

class Registry implements LoggerAwareInterface {

	use LoggerAwareTrait;

	/** @var ObjectFactory */
	private $objectFactory;

	/** @var array[] provider => handler config for ObjectFactory */
	private $handlerConfig;

	/**
	 * @param ObjectFactory $objectFactory
	 * @param array $handlerConfig
	 */
	public function __construct( ObjectFactory $objectFactory, array $handlerConfig ) {
		$this->objectFactory = $objectFactory;
		$this->handlerConfig = $handlerConfig;

		$this->setLogger( new NullLogger() );
	}

	/**
	 * Get the handlers that are appropriate for a given file.
	 * @param LocalFile $file
	 * @return Handler[]
	 */
	public function getHandlers( LocalFile $file ) {
		// Not bothering with caching as we don't expect this to be called multiple times per request.
		$handlers = [];
		foreach ( $this->handlerConfig as $provider => $spec ) {
			/** @var Handler $handler */
			$handler = $this->objectFactory->createObject( $spec, [ 'assertClass' => Handler::class ] );
			$handler->setLogger( $this->logger );
			$handlers[] = $handler;
		}
		return $handlers;
	}

}
