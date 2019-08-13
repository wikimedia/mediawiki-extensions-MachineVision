<?php

use MediaWiki\Extension\MachineVision\Client;
use MediaWiki\Extension\MachineVision\Handler\Registry;
use MediaWiki\Extension\MachineVision\Repository;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Storage\NameTableStore;
use Wikimedia\ObjectFactory;

return [

	'MachineVisionClient' => function ( MediaWikiServices $services ): Client {
		$httpRequestFactory = $services->getHttpRequestFactory();
		$wikiId = wfWikiID();

		$labelingClient = new Client(
			$httpRequestFactory,
			$httpRequestFactory->getUserAgent() . "($wikiId)"
		);
		$labelingClient->setLogger( LoggerFactory::getInstance( 'machinevision' ) );
		return $labelingClient;
	},

	'MachineVisionNameTableStore' => function ( MediaWikiServices $services ): NameTableStore {
		$extensionConfig = $services->getConfigFactory()->makeConfig( 'MachineVision' );
		$loadBalancerFactory = $services->getDBLoadBalancerFactory();
		$wanObjectCache = $services->getMainWANObjectCache();

		$cluster = $extensionConfig->get( 'MachineVisionCluster' );
		$database = $extensionConfig->get( 'MachineVisionDatabase' );

		$loadBalancer = $cluster
			? $loadBalancerFactory->getExternalLB( $cluster )
			: $loadBalancerFactory->getMainLB( $database );

		return new NameTableStore(
			$loadBalancer,
			$wanObjectCache,
			LoggerFactory::getInstance( 'machinevision' ),
			'machine_vision_provider',
			'mvp_id',
			'mvp_name',
			null,
			$database
		);
	},

	'MachineVisionRepository' => function ( MediaWikiServices $services ): Repository {
		$extensionConfig = $services->getConfigFactory()->makeConfig( 'MachineVision' );
		$loadBalancerFactory = $services->getDBLoadBalancerFactory();

		$cluster = $extensionConfig->get( 'MachineVisionCluster' );
		$database = $extensionConfig->get( 'MachineVisionDatabase' );

		$loadBalancer = $cluster
			? $loadBalancerFactory->getExternalLB( $cluster )
			: $loadBalancerFactory->getMainLB( $database );

		return new Repository(
			$services->getService( 'MachineVisionNameTableStore' ),
			$loadBalancer->getLazyConnectionRef( DB_REPLICA, [], $database ),
			$loadBalancer->getLazyConnectionRef( DB_MASTER, [], $database )
		);
	},

	'MachineVisionHandlerRegistry' => function ( MediaWikiServices $services ): Registry {
		$objectFactory = new ObjectFactory( $services );
		$extensionConfig = $services->getConfigFactory()->makeConfig( 'MachineVision' );
		$handlerConfig = $extensionConfig->get( 'MachineVisionHandlers' );

		$registry = new Registry( $objectFactory, $handlerConfig );
		$registry->setLogger( LoggerFactory::getInstance( 'machinevision' ) );
		return $registry;
	},

];
