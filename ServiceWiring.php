<?php

use MediaWiki\Extension\MachineVision\Client\GoogleCloudVisionClient;
use MediaWiki\Extension\MachineVision\Client\GoogleOAuthClient;
use MediaWiki\Extension\MachineVision\Client\RandomWikidataIdClient;
use MediaWiki\Extension\MachineVision\Handler\LabelResolver;
use MediaWiki\Extension\MachineVision\Handler\Registry;
use MediaWiki\Extension\MachineVision\Job\FetchGoogleCloudVisionAnnotationsJobFactory;
use MediaWiki\Extension\MachineVision\Repository;
use MediaWiki\Extension\MachineVision\TitleFilter;
use MediaWiki\Extension\MachineVision\Util;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Storage\NameTableStore;
use Wikibase\Repo\WikibaseRepo;
use Wikimedia\AtEase\AtEase;
use Wikimedia\ObjectFactory;

return [

	'MachineVisionRandomWikidataIdClient' => function ( MediaWikiServices $services ):
		RandomWikidataIdClient {
		$httpRequestFactory = $services->getHttpRequestFactory();
		$wikiDomain = $services->getMainConfig()->get( 'ServerName' );

		$client = new RandomWikidataIdClient(
			$httpRequestFactory,
			$httpRequestFactory->getUserAgent() . " ($wikiDomain)"
		);
		$client->setLogger( LoggerFactory::getInstance( 'machinevision' ) );
		return $client;
	},

	'MachineVisionGoogleCloudVisionClient' => function ( MediaWikiServices $services ):
	GoogleCloudVisionClient {
		$configFactory = $services->getConfigFactory();
		$extensionConfig = $configFactory->makeConfig( 'MachineVision' );

		$credentialsData = $extensionConfig->get( 'MachineVisionGoogleApiCredentials' );
		if ( !$credentialsData ) {
			// Allow providing a filesystem path for local development
			$filename = $extensionConfig->get( 'MachineVisionGoogleCredentialsFileLocation' );
			AtEase::suppressWarnings();
			$json = file_get_contents( $filename );
			AtEase::restoreWarnings();
			if ( $json === false ) {
				throw new RuntimeException( "File not found: $filename" );
			}
			$credentialsData = json_decode( $json, true );
		}

		$safeSearchLimits = $extensionConfig->get( 'MachineVisionGoogleSafeSearchLimits' );
		$sendFileContents = $extensionConfig->get( 'MachineVisionGCVSendFileContents' );
		$proxy = $extensionConfig->get( 'MachineVisionHttpProxy' );

		$loadBalancerFactory = $services->getDBLoadBalancerFactory();
		$cluster = $extensionConfig->get( 'MachineVisionCluster' );
		$database = $extensionConfig->get( 'MachineVisionDatabase' );
		$wikidataIdBlacklist = $extensionConfig->get( 'MachineVisionWikidataIdBlacklist' );
		$withholdImageList = $extensionConfig->get( 'MachineVisionWithholdImageList' );
		$loadBalancer = $cluster
			? $loadBalancerFactory->getExternalLB( $cluster )
			: $loadBalancerFactory->getMainLB( $database );
		$repository = new Repository(
			$services->getService( 'MachineVisionNameTableStore' ),
			$loadBalancer->getLazyConnectionRef( DB_REPLICA, [], $database ),
			$loadBalancer->getLazyConnectionRef( DB_MASTER, [], $database )
		);

		$client = new GoogleCloudVisionClient(
			new GoogleOAuthClient( $services->getHttpRequestFactory(), $credentialsData, $proxy ),
			$services->getHttpRequestFactory(),
			$services->getRepoGroup(),
			$repository,
			$sendFileContents,
			$safeSearchLimits,
			$proxy,
			$withholdImageList,
			$wikidataIdBlacklist
		);
		$client->setLogger( LoggerFactory::getInstance( 'machinevision' ) );
		return $client;
	},

	'MachineVisionNameTableStore' => function ( MediaWikiServices $services ): NameTableStore {
		$extensionConfig = $services->getService( 'MachineVisionConfig' );
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
		$extensionConfig = $services->getService( 'MachineVisionConfig' );
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
		$extensionConfig = $services->getService( 'MachineVisionConfig' );
		$handlerConfig = $extensionConfig->get( 'MachineVisionHandlers' );

		$registry = new Registry( $objectFactory, $handlerConfig );
		$registry->setLogger( LoggerFactory::getInstance( 'machinevision' ) );
		return $registry;
	},

	'MachineVisionConfig' => function ( MediaWikiServices $services ): Config {
		return $services->getConfigFactory()->makeConfig( 'MachineVision' );
	},

	'MachineVisionRepoGroup' => function ( MediaWikiServices $services ): RepoGroup {
		return $services::getInstance()->getRepoGroup();
	},

	'MachineVisionLabelResolver' => function ( MediaWikiServices $services ): LabelResolver {
		$extensionConfig = $services->getService( 'MachineVisionConfig' );
		$entityLookup = WikibaseRepo::getDefaultInstance()->getEntityLookup();
		$httpRequestFactory = $services->getHttpRequestFactory();
		$wikiDomain = $services->getMainConfig()->get( 'ServerName' );
		$userAgent = $httpRequestFactory->getUserAgent() . " ($wikiDomain)";
		$useWikidataPublicApi =
			$extensionConfig->get( 'MachineVisionRequestLabelsFromWikidataPublicApi' );

		return new LabelResolver(
			$entityLookup,
			$httpRequestFactory,
			$userAgent,
			$useWikidataPublicApi
		);
	},

	'MachineVisionTitleFilter' => function ( MediaWikiServices $services ): TitleFilter {
		$configFactory = $services->getConfigFactory();
		$extensionConfig = $configFactory->makeConfig( 'MachineVision' );
		return new TitleFilter(
			$services->getRepoGroup()->getLocalRepo(),
			$services->getRevisionStore(),
			$extensionConfig->get( 'MachineVisionMinImageWidth' ),
			$extensionConfig->get( 'MachineVisionMaxExistingDepictsStatements' ),
			$extensionConfig->get( 'MachineVisionCategoryBlacklist' ),
			$extensionConfig->get( 'MachineVisionTemplateBlacklist' ),
			Util::getMediaInfoPropertyId( 'depicts' )
		);
	},

	'MachineVisionFetchGoogleCloudVisionAnnotationsJobFactory' =>
		function ( MediaWikiServices $services ): FetchGoogleCloudVisionAnnotationsJobFactory {
			$configFactory = $services->getConfigFactory();
			$extensionConfig = $configFactory->makeConfig( 'MachineVision' );
			$safeSearchLimits = $extensionConfig->get( 'MachineVisionGoogleSafeSearchLimits' );
			$sendFileContents = $extensionConfig->get( 'MachineVisionGCVSendFileContents' );
			$proxy = $extensionConfig->get( 'MachineVisionHttpProxy' );
			$delay = $extensionConfig->get( 'MachineVisionNewUploadLabelingJobDelay' );
			return new FetchGoogleCloudVisionAnnotationsJobFactory(
				$sendFileContents,
				$safeSearchLimits,
				$proxy,
				$delay
			);
		},
];
