( function () {

	'use strict';

	var ImageDepictsSuggestionsPager = require( './widgets/ImageDepictsSuggestionsPager.js' );

	/* eslint-disable-next-line no-jquery/no-global-selector */
	$( '#bodyContent' ).append( new ImageDepictsSuggestionsPager().$element );
}() );
