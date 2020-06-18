'use strict';

/* eslint-disable no-implicit-globals */
var userGroups = mw.config.get( 'wgUserGroups' ) || [];

module.exports = {
	/**
	 * @param {Object} state
	 * @return {Array} tabs Tab names
	 */
	tabs: function ( state ) {
		return Object.keys( state.images );
	},

	/**
	 * @param {Object} state
	 * @return {Object} image
	 */
	currentImage: function ( state ) {
		return state.images[ state.currentTab ][ 0 ];
	},

	/**
	 * @param {Object} state
	 * @param {Object} getters
	 * @return {string|null} title
	 */
	currentImageTitle: function ( state, getters ) {
		var title;
		if ( getters.currentImage ) {
			title = mw.Title.newFromText( getters.currentImage.title );
			return title.getRelativeText( mw.config.get( 'wgNamespaceIds' ).file );
		} else {
			return null;
		}
	},

	/**
	 * @param {Object} state
	 * @param {Object} getters
	 * @return {string|null} title
	 */
	currentImageMediaInfoId: function ( state, getters ) {
		var pageId;

		if ( getters.currentImage ) {
			pageId = getters.currentImage.pageid;
			return 'M' + pageId;
		} else {
			return null;
		}
	},

	/**
	 * @param {Object} state
	 * @param {Object} getters
	 * @return {Array} suggestions
	 */
	currentImageSuggestions: function ( state, getters ) {
		if ( getters.currentImage ) {
			// Filter out suggestions with no label.
			return getters.currentImage.suggestions.filter( function ( suggestion ) {
				return suggestion.text;
			} );
		} else {
			return [];
		}
	},

	currentImageNonDisplayableSuggestions: function ( state, getters ) {
		if ( getters.currentImage ) {
			// Return *only* suggestions with no label
			return getters.currentImage.suggestions.filter( function ( suggestion ) {
				return !suggestion.text;
			} );
		} else {
			return [];
		}
	},

	/**
	 * Whether or not the user is logged in. Derived from non-Vuex global
	 * state.
	 *
	 * @return {boolean}
	 */
	isAuthenticated: function () {
		return !!mw.config.get( 'wgUserName' );
	},

	/**
	 * Whether or not the user is autoconfirmed. Derived from non-Vuex
	 * global state.
	 *
	 * @return {boolean}
	 */
	isAutoconfirmed: function () {
		return userGroups.indexOf( 'autoconfirmed' ) !== -1;
	}
};
