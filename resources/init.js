'use strict';

require( './polyfills/Array.prototype.find.js' );

( function () {
	var Vue = require( 'vue' ),
		App = require( './components/App.vue' ),
		api = require( './plugins/api.js' ),
		logger = require( './plugins/logger.js' ),
		store = require( './store/index.js' );

	// Remove placeholder UI
	$( document.body ).addClass( 'wbmad-ui-initialized' );

	Vue.createMwApp( $.extend( { store: store }, App ) )
		.use( api )
		.use( logger )
		.mount( '#wbmad-app' );
}() );
