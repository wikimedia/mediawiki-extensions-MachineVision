'use strict';

/**
 * Helper function to ensure the user doesn't try to access an invalid tab.
 *
 * @param {Object} state
 * @param {string} tab
 */
module.exports.ensureTabExists = function ensureTabExists( state, tab ) {
	var tabs = Object.keys( state.images );

	if ( tabs.indexOf( tab ) === -1 ) {
		throw new Error( 'invalid tab' );
	}
};

/**
 * Get categories for an image.
 * @param {Object} item
 * @return {Array}
 */
module.exports.getCategories = function ( item ) {
	var categories = [],
		title;

	if ( item.categories && item.categories.length > 0 ) {
		categories = item.categories.map( function ( category ) {
			title = mw.Title.newFromText( category.title );
			return title.getRelativeText( category.ns );
		} );
	}

	return categories;
};
