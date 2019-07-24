<?php

use MediaWiki\Extension\MachineVision\Client;
use MediaWiki\Extension\MachineVision\Repository;
use MediaWiki\Extension\MachineVision\Services;
use MediaWiki\Extension\MachineVision\UploadHandler;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Storage\NameTableStore;

return [

	'MachineVisionClient' => function ( MediaWikiServices $services ): Client {
		$machineVisionConfig = $services->getConfigFactory()->makeConfig( 'MachineVision' );
		$httpRequestFactory = $services->getHttpRequestFactory();

		$wikiId = wfWikiID();
		$apiUrl = $machineVisionConfig->get( 'MachineVisionLabelingApi' );

		$labelingClient = new Client(
			$httpRequestFactory,
			$apiUrl,
			$httpRequestFactory->getUserAgent() . "($wikiId)"
		);
		$labelingClient->setLogger( LoggerFactory::getInstance( 'machinevision' ) );
		return $labelingClient;
	},

	'MachineVisionRepository' => function ( MediaWikiServices $services ): Repository {
		$extensionConfig = $services->getConfigFactory()->makeConfig( 'MachineVision' );
		$loadBalancerFactory = $services->getDBLoadBalancerFactory();
		$wanObjectCache = $services->getMainWANObjectCache();

		$cluster = $extensionConfig->get( 'MachineVisionCluster' );
		$database = $extensionConfig->get( 'MachineVisionDatabase' );

		$loadBalancer = $cluster
			? $loadBalancerFactory->getExternalLB( $cluster )
			: $loadBalancerFactory->getMainLB( $database );

		return new Repository(
			new NameTableStore(
				$loadBalancer,
				$wanObjectCache,
				LoggerFactory::getInstance( 'machinevision' ),
				'machine_vision_provider',
				'mvp_id',
				'mvp_name'
			),
			$loadBalancer->getLazyConnectionRef( DB_REPLICA, [], $database ),
			$loadBalancer->getLazyConnectionRef( DB_MASTER, [], $database )
		);
	},

	'MachineVisionUploadHandler' => function ( MediaWikiServices $services ): UploadHandler {
		$extensionServices = new Services( $services );
		$handler = new UploadHandler(
			$extensionServices->getClient(),
			$extensionServices->getRepository()
		);
		$handler->setLogger( LoggerFactory::getInstance( 'machinevision' ) );
		return $handler;
	},

];
