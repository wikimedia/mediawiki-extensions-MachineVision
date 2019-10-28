<?php

namespace MediaWiki\Extension\MachineVision;

use DomainException;
use MediaWiki\MediaWikiServices;

class Util {

	/** @var array */
	public static $allowedMediaTypes = [
		// @phan-suppress-next-line PhanUndeclaredConstant
		MEDIATYPE_BITMAP,
	];

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
			throw new DomainException( "MediaInfo property '$prop' is not defined" );
		}
		return $mediaInfoProperties[$prop];
	}

	/**
	 * Return true if the media type is allowed
	 *
	 * @param string $mediaType
	 * @return bool
	 */
	public static function isMediaTypeAllowed( $mediaType ) {
		return array_search( $mediaType, self::$allowedMediaTypes ) !== false;
	}

}
