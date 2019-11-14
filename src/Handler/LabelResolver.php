<?php

namespace MediaWiki\Extension\MachineVision\Handler;

use IContextSource;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Logger\LoggerFactory;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\LanguageFallbackChain;
use Wikibase\LanguageFallbackChainFactory;

class LabelResolver implements LoggerAwareInterface {

	use LoggerAwareTrait;

	/** @var EntityLookup */
	private $entityLookup;

	/** @var LanguageFallbackChainFactory */
	// @phan-suppress-next-line PhanUndeclaredTypeProperty
	private $languageFallbackChainFactory;

	/** @var HttpRequestFactory */
	private $httpRequestFactory;

	/** @var string */
	private $userAgent;

	/** @var bool */
	private $useWikidataPublicApi;

	/**
	 * LabelResolver constructor.
	 * @param EntityLookup $entityLookup
	 * @param LanguageFallbackChainFactory $languageFallbackChainFactory
	 * @param HttpRequestFactory $httpRequestFactory
	 * @param string $userAgent
	 * @param bool $useWikidataPublicApi if true, request labels from the Wikidata public API
	 * @suppress PhanUndeclaredTypeParameter
	 */
	public function __construct( EntityLookup $entityLookup,
								 LanguageFallbackChainFactory $languageFallbackChainFactory,
								 HttpRequestFactory $httpRequestFactory,
								 $userAgent,
								 $useWikidataPublicApi ) {
		$this->entityLookup = $entityLookup;
		$this->languageFallbackChainFactory = $languageFallbackChainFactory;
		$this->httpRequestFactory = $httpRequestFactory;
		$this->userAgent = $userAgent;
		$this->useWikidataPublicApi = $useWikidataPublicApi;

		$this->setLogger( LoggerFactory::getInstance( 'machinevision' ) );
	}

	/**
	 * @param IContextSource $context
	 * @param string[] $ids Wikidata IDs
	 * @return string[] labels mapped to IDs
	 * @suppress PhanUndeclaredClassMethod
	 */
	public function resolve( $context, $ids ) {
		$languageFallbackChain = $this->languageFallbackChainFactory->newFromContext( $context );
		return $this->useWikidataPublicApi
			? $this->resolveExternal( $languageFallbackChain, $ids )
			: $this->resolveInternal( $languageFallbackChain, $ids );
	}

	/**
	 * Resolve item labels through the configured Wikibase repo.
	 * @param LanguageFallbackChain $languageFallbackChain
	 * @param string[] $ids Wikidata IDs
	 * @return string[] labels mapped to IDs
	 * @suppress PhanUndeclaredTypeParameter
	 */
	private function resolveInternal( LanguageFallbackChain $languageFallbackChain, $ids ) {
		$result = [];
		foreach ( $ids as $id ) {
			$item = $this->entityLookup->getEntity( new ItemId( $id ) );
			if ( !$item ) {
				$this->logger->warning(
					"No entity found for ID $id",
					[ 'caller' => __METHOD__ ]
				);
				continue;
			}
			// @phan-suppress-next-line PhanUndeclaredMethod
			$labels = $item->getLabels()->toTextArray();
			if ( $labels ) {
				$result[$id] =
					$this->getLabelByLanguageFallbackChain( $languageFallbackChain, $labels );
			}
		}
		return $result;
	}

	/**
	 * Resolve Wikidata item labels via the Wikidata public API. This is necessary for testing
	 * labeling providers that return genuine Wikidata ids, since outside production we won't have
	 * Wikidata as a Wikibase repo.
	 * In production, we'll resolve these via EntityLookup and not the public API.
	 * @param LanguageFallbackChain $languageFallbackChain
	 * @param string[] $ids Wikidata IDs
	 * @return string[] labels mapped to IDs
	 * @suppress PhanUndeclaredTypeParameter
	 */
	private function resolveExternal( LanguageFallbackChain $languageFallbackChain, $ids ) {
		$result = [];
		$url = "https://www.wikidata.org/w/api.php?action=wbgetentities&format=json" .
			   "&props=labels&ids=" . implode( '|', $ids );
		$rawWbEntitiesResponse = $this->httpRequestFactory->get( $url, [
			'userAgent' => $this->userAgent,
		], __METHOD__ );
		$wbEntitiesResponse = json_decode( $rawWbEntitiesResponse, true );
		foreach ( $ids as $id ) {
			// @phan-suppress-next-line PhanTypeArraySuspiciousNullable
			$labelData = $wbEntitiesResponse['entities'][$id]['labels'];
			$labels = [];
			foreach ( $labelData as $lang => $data ) {
				$labels[$lang] = $data['value'];
			}
			if ( $labels ) {
				$result[$id] =
					$this->getLabelByLanguageFallbackChain( $languageFallbackChain, $labels );
			}
		}
		return $result;
	}

	/**
	 * Return the best available label according to the provided language fallback chain.
	 * @param LanguageFallbackChain $languageFallbackChain
	 * @param string[] $labels
	 * @return string
	 * @suppress PhanUndeclaredTypeParameter,PhanUndeclaredClassMethod
	 */
	private function getLabelByLanguageFallbackChain( LanguageFallbackChain $languageFallbackChain,
		$labels ) {
		if ( !$labels ) {
			return null;
		}
		foreach ( $languageFallbackChain->getFetchLanguageCodes() as $lang ) {
			if ( array_key_exists( $lang, $labels ) ) {
				return $labels[$lang];
			}
		}
		return array_values( $labels )[0];
	}

}
