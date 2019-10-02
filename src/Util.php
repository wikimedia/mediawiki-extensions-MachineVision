<?php

namespace MediaWiki\Extension\MachineVision;

use DomainException;
use MediaWiki\MediaWikiServices;

class Util {

	/**
	 * Get the configured property ID for a MediaInfo property.
	 * @param MediaWikiServices $services
	 * @param string $prop property name
	 * @return string
	 */
	public static function getMediaInfoPropertyId( MediaWikiServices $services, $prop ) {
		$configFactory = $services->getConfigFactory();
		$wbmiConfig = $configFactory->makeConfig( 'WikibaseMediaInfo' );
		if ( !$wbmiConfig->has( 'MediaInfoProperties' ) ) {
			throw new DomainException( 'MediaInfoProperties not set' );
		}
		$mediaInfoProperties = $wbmiConfig->get( 'MediaInfoProperties' );
		if ( !isset( $mediaInfoProperties[$prop] ) ) {
			throw new DomainException( "MediaInfo property $prop is not defined" );
		}
		return $mediaInfoProperties[$prop];
	}

}
