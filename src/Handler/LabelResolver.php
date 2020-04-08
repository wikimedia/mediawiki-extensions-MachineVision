<?php

namespace MediaWiki\Extension\MachineVision\Handler;

use IContextSource;
use Language;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Logger\LoggerFactory;
use MWException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Services\Lookup\EntityLookup;

/**
 * This class looks up Wikibase entities by ID and retreives human-readable
 * text for them (label, description, alias). It attempts to provide this text
 * in a relevant language to the user via a language fallback chain.
 */
class LabelResolver implements LoggerAwareInterface {

	use LoggerAwareTrait;

	/** @var EntityLookup */
	private $entityLookup;

	/** @var HttpRequestFactory */
	private $httpRequestFactory;

	/** @var string */
	private $userAgent;

	/** @var bool */
	private $useWikidataPublicApi;

	/**
	 * LabelResolver constructor.
	 * @param EntityLookup $entityLookup
	 * @param HttpRequestFactory $httpRequestFactory
	 * @param string $userAgent
	 * @param bool $useWikidataPublicApi if true, request labels from the Wikidata public API
	 */
	public function __construct( EntityLookup $entityLookup,
								 HttpRequestFactory $httpRequestFactory,
								 $userAgent,
								 $useWikidataPublicApi ) {
		$this->entityLookup = $entityLookup;
		$this->httpRequestFactory = $httpRequestFactory;
		$this->userAgent = $userAgent;
		$this->useWikidataPublicApi = $useWikidataPublicApi;

		$this->setLogger( LoggerFactory::getInstance( 'machinevision' ) );
	}

	/**
	 * @param IContextSource $context
	 * @param string[] $ids Wikidata IDs
	 * @return array[] strings for labels, descriptions, and aliases mapped to IDs
	 */
	public function resolve( $context, $ids ) {
		// Determine UI language and fallback chain for later use
		$uiLang = $context->getLanguage();

		return $this->useWikidataPublicApi
			? $this->resolveExternal( $uiLang, $ids )
			: $this->resolveInternal( $uiLang, $ids );
	}

	/**
	 * Resolve item labels through the configured Wikibase repo.
	 * @param Language $uiLang
	 * @param string[] $ids Wikidata IDs
	 * @return array[] strings for labels, descriptions, and aliases mapped to IDs
	 */
	private function resolveInternal(
		Language $uiLang,
		$ids
	) {
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
			// @phan-suppress-next-line PhanUndeclaredMethod
			$descriptions = $item->getDescriptions()->toTextArray();
			// @phan-suppress-next-line PhanUndeclaredMethod
			$aliases = $item->getAliasGroups()->toArray();

			if ( $labels ) {
				$result[$id]['label'] =
					$this->getTextByLanguageFallbackChain( $uiLang, $labels );
			}
			if ( $descriptions ) {
				$result[$id]['description'] =
					$this->getTextByLanguageFallbackChain( $uiLang, $descriptions );
			}
			if ( $aliases ) {
				$result[$id]['alias'] =
					$this->getTextByLanguageFallbackChain( $uiLang, $aliases );
			}

		}
		return $result;
	}

	/**
	 * Resolve Wikidata item labels via the Wikidata public API. This is necessary for testing
	 * labeling providers that return genuine Wikidata ids, since outside production we won't have
	 * Wikidata as a Wikibase repo.
	 * In production, we'll resolve these via EntityLookup and not the public API.
	 * @param Language $uiLang
	 * @param string[] $ids Wikidata IDs
	 * @return array[] strings for labels, descriptions, and aliases mapped to IDs
	 */
	private function resolveExternal(
		Language $uiLang,
		$ids
	) {
		$result = [];
		$url = "https://www.wikidata.org/w/api.php?action=wbgetentities&format=json" .
			   "&props=aliases|labels|descriptions&ids=" . implode( '|', $ids );
		$rawWbEntitiesResponse = $this->httpRequestFactory->get( $url, [
			'userAgent' => $this->userAgent,
		], __METHOD__ );
		if ( !$rawWbEntitiesResponse ) {
			throw new MWException( 'Label resolution request to Wikidata failed' );
		}
		$wbEntitiesResponse = json_decode( $rawWbEntitiesResponse, true );

		foreach ( $ids as $id ) {
			// @phan-suppress-next-line PhanTypeArraySuspiciousNullable
			$entityData = $wbEntitiesResponse['entities'][$id];
			if ( !array_key_exists( 'labels', $entityData ) ) {
				$this->logger->warning(
					"No labels found for ID $id",
					[ 'caller' => __METHOD__ ]
				);
				continue;
			}

			// Data for each language is an associative arrays
			$labelData = $entityData['labels'];
			$descriptionData = $entityData['descriptions'];

			// Data for each language is a regular array
			$aliasData = $entityData['aliases'];

			// Get the best label for the current language
			$labels = [];
			foreach ( $labelData as $lang => $data ) {
				$labels[$lang] = $data['value'];
			}
			if ( $labels ) {
				$result[$id]['label'] =
					$this->getTextByLanguageFallbackChain( $uiLang, $labels );
			}

			// Get the best description for the current language
			$descriptions = [];
			foreach ( $descriptionData as $lang => $data ) {
				$descriptions[$lang] = $data['value'];
			}
			if ( $descriptions ) {
				$result[$id]['description'] =
					$this->getTextByLanguageFallbackChain( $uiLang, $descriptions );
			}

			// Get the first best alias for the current language
			$aliases = [];
			foreach ( $aliasData as $lang => $data ) {
				$aliases[$lang] = $data[ 0 ]['value'];
			}
			if ( $aliases ) {
				$result[$id]['alias'] =
					$this->getTextByLanguageFallbackChain( $uiLang, $aliases );
			}

		}

		return $result;
	}

	/**
	 * Return the best available text according to the provided language fallback chain.
	 * @param Language $uiLang
	 * @param string[] $items
	 * @return string|null
	 */
	private function getTextByLanguageFallbackChain(
		Language $uiLang,
		$items
	) {
		if ( !$items ) {
			return null;
		}

		$langCodes = array_merge(
			[ $uiLang->getCode() ],
			$uiLang->getFallbackLanguages()
		);
		foreach ( $langCodes as $lang ) {
			if ( array_key_exists( $lang, $items ) ) {
				return $items[$lang];
			}
		}
		return null;
	}
}
