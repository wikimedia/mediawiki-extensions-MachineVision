<?php

use Google\Cloud\Vision\V1\ImageAnnotatorClient;
use MediaWiki\Extension\MachineVision\Client;
use MediaWiki\Extension\MachineVision\Handler\WikidataDepictsSetter;
use MediaWiki\Extension\MachineVision\Handler\LabelResolver;
use MediaWiki\Extension\MachineVision\Handler\Registry;
use MediaWiki\Extension\MachineVision\Repository;
use MediaWiki\Extension\MachineVision\TitleFilter;
use MediaWiki\Extension\MachineVision\Util;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Storage\NameTableStore;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\LanguageFallbackChainFactory;
use Wikibase\MediaInfo\Services\MediaInfoByLinkedTitleLookup;
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

	'MachineVisionGoogleImageAnnotatorClient' => function ( MediaWikiServices $services ):
		ImageAnnotatorClient {
		return new ImageAnnotatorClient();
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

	'MachineVisionConfig' => function ( MediaWikiServices $services ): Config {
		return $services->getConfigFactory()->makeConfig( 'MachineVision' );
	},

	'MachineVisionRepoGroup' => function ( MediaWikiServices $services ): RepoGroup {
		return $services::getInstance()->getRepoGroup();
	},

	'MachineVisionLabelResolver' => function ( MediaWikiServices $services ): LabelResolver {
		$extensionConfig = $services->getConfigFactory()->makeConfig( 'MachineVision' );
		$entityLookup = WikibaseRepo::getDefaultInstance()->getEntityLookup();
		$languageFallbackChainFactory = new LanguageFallbackChainFactory();
		$httpRequestFactory = $services->getHttpRequestFactory();
		$wikiId = wfWikiID();
		$userAgent = $httpRequestFactory->getUserAgent() . "($wikiId)";
		$useWikidataPublicApi =
			$extensionConfig->get( 'MachineVisionRequestLabelsFromWikidataPublicApi' );

		return new LabelResolver(
			$entityLookup,
			$languageFallbackChainFactory,
			$httpRequestFactory,
			$userAgent,
			$useWikidataPublicApi
		);
	},

	'MachineVisionDepictsSetter' => function ( MediaWikiServices $services ):
		WikidataDepictsSetter {
		$wbRepo = WikibaseRepo::getDefaultInstance();
		$entityByLinkedTitleLookup = $wbRepo->getStore()->getEntityByLinkedTitleLookup();
		$changeOpFactoryProvider = $wbRepo->getChangeOpFactoryProvider();

		return new WikidataDepictsSetter(
			$services->getRevisionStore(),
			new MediaInfoByLinkedTitleLookup( $entityByLinkedTitleLookup ),
			$wbRepo->getEntityLookup(),
			$wbRepo->newEditEntityFactory(),
			$changeOpFactoryProvider->getStatementChangeOpFactory(),
			$wbRepo->getSummaryFormatter(),
			Util::getMediaInfoPropertyId( 'depicts' )
		);
	},

	'MachineVisionEntityLookup' => function ( MediaWikiServices $services ): EntityLookup {
		return WikibaseRepo::getDefaultInstance()->getEntityLookup();
	},

	'MachineVisionTitleFilter' => function ( MediaWikiServices $services ): TitleFilter {
		$extensionConfig = $services->getConfigFactory()->makeConfig( 'MachineVision' );
		return new TitleFilter(
			$services->getRepoGroup()->getLocalRepo(),
			$extensionConfig->get( 'MachineVisionMinImageWidth' ),
			$extensionConfig->get( 'MachineVisionCategoryBlacklist' ),
			$extensionConfig->get( 'MachineVisionTemplateBlacklist' )
		);
	}

];
