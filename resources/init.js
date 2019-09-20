( function () {

	'use strict';

	var SuggestedTagsPage = require( './widgets/SuggestedTagsPage.js' );

	/* eslint-disable-next-line no-jquery/no-global-selector */
	$( '#bodyContent' ).append( new SuggestedTagsPage().$element );
}() );
