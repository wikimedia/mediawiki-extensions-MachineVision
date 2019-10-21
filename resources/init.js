( function () {

	'use strict';

	var SuggestedTagsPage = require( './widgets/SuggestedTagsPage.js' ),
		url = new mw.Uri(),
		stp = new SuggestedTagsPage( {
			startTab: url.fragment
		} );

	/* eslint-disable-next-line no-jquery/no-global-selector */
	$( '#bodyContent' ).append( stp.$element );
}() );
