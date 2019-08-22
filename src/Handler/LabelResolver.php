<?php

namespace MediaWiki\Extension\MachineVision\Handler;

use MediaWiki\Http\HttpRequestFactory;

class LabelResolver {

	/** @var HttpRequestFactory */
	private $httpRequestFactory;

	/** @var string */
	private $userAgent;

	/**
	 * LabelResolver constructor.
	 * @param HttpRequestFactory $httpRequestFactory
	 * @param string $userAgent
	 */
	public function __construct( HttpRequestFactory $httpRequestFactory, $userAgent ) {
		$this->httpRequestFactory = $httpRequestFactory;
		$this->userAgent = $userAgent;
	}

	/**
	 * HACK: Resolve Wikidata item labels via the Wikidata public API. This is necessary for
	 * testing labeling providers that return genuine Wikidata ids, since outside production we
	 * won't have Wikidata as a Wikibase repo.
	 * In production, we'll resolve these via EntityLookup and not the public API.
	 * TODO: Add (preferred) internal EntityLookup resolution
	 * @param string[] $ids Wikidata IDs
	 * @param string $lang language code
	 * @return string[] labels mapped to IDs
	 */
	public function resolve( $ids, $lang ) {
		$result = [];
		$url = "https://www.wikidata.org/w/api.php?action=wbgetentities&format=json" .
			   "&props=labels&ids=" . implode( '|', $ids );
		$rawWbEntitiesResponse = $this->httpRequestFactory->get( $url, [
			'userAgent' => $this->userAgent,
		], __METHOD__ );
		$wbEntitiesResponse = json_decode( $rawWbEntitiesResponse, true );
		foreach ( $ids as $id ) {
			$labels = $wbEntitiesResponse['entities'][$id]['labels'];
			if ( $labels && $labels[$lang] ) {
				$result[$id] = $labels[$lang]['value'];
			}
		}
		return $result;
	}

}
