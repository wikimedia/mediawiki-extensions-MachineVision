'use strict';

( function () {
	var Vue = require( 'vue' ),
		App = require( './components/App.vue' ),
		api = require( './plugins/api.js' ),
		logger = require( './plugins/logger.js' ),
		store = require( './store/index.js' );

	// Remove placeholder UI
	$( document.body ).addClass( 'wbmad-ui-initialized' );

	/**
	 * Vue plugins need to be initialized with Vue.use() before the Vue
	 * instance is created. These plugins live in the resources/plugins
	 * directory. More information about Vue plugins can be found here:
	 * https://vuejs.org/v2/guide/plugins.html
	 */
	Vue.use( api );
	Vue.use( logger );

	// Create the Vue instance
	// eslint-disable-next-line no-new
	new Vue( {
		el: '#wbmad-app',
		store: store,
		render: function ( h ) {
			return h( App );
		}
	} );
}() );
