'use strict';

var ensureTabExists = require( './utils.js' ).ensureTabExists;

module.exports = {
	/**
	 * Set the current tab; name must be one of the predefined items in state.tabs.
	 *
	 * @param {Object} state
	 * @param {string} tab
	 */
	setTab: function ( state, tab ) {
		ensureTabExists( state, tab );
		state.currentTab = tab;
	},

	/**
	 * Sets the fetch pending state
	 *
	 * @param {Object} state
	 * @param {Object} payload
	 * @param {boolean} payload.pending
	 * @param {string} [payload.queue]
	 */
	setFetchPending: function ( state, payload ) {
		if ( payload.queue ) {
			ensureTabExists( state, payload.queue );
			state.fetchPending[ payload.queue ] = !!payload.pending;
		} else {
			state.fetchPending[ state.currentTab ] = !!payload.pending;
		}
	},

	/**
	 * Sets the fetch error state
	 *
	 * @param {Object} state
	 * @param {Object} payload
	 * @param {boolean} payload.error
	 * @param {string} [payload.queue]
	 */
	setFetchError: function ( state, payload ) {
		if ( payload.queue ) {
			ensureTabExists( state, payload.queue );
			state.fetchError[ payload.queue ] = !!payload.error;
		} else {
			state.fetchError[ state.currentTab ] = !!payload.error;
		}
	},

	/**
	 * Add an image object to the queue.
	 *
	 * @param {Object} state
	 * @param {Object} payload
	 * @param {Object} payload.image
	 * @param {string} [payload.queue] Target queue to add image to; defaults to current
	 */
	addImage: function ( state, payload ) {
		if ( payload.queue ) {
			ensureTabExists( state, payload.queue );
			state.images[ payload.queue ].push( payload.image );
		} else {
			state.images[ state.currentTab ].push( payload.image );
		}
	},

	/**
	 * @param {Object} state
	 * @param {Object} suggestion
	 */
	addSuggestionToCurrentImage: function ( state, suggestion ) {
		state.images[ state.currentTab ][ 0 ].suggestions.push( suggestion );
	},

	/**
	 * Remove the first image from the queue.
	 *
	 * @param {Object} state
	 */
	removeImage: function ( state ) {
		state.images[ state.currentTab ].shift();
	},

	/**
	 * Clear all images in the queue and set pending back to true
	 *
	 * @param {Object} state
	 */
	clearImages: function ( state ) {
		state.images[ state.currentTab ] = [];
		state.fetchPending[ state.currentTab ] = true;
	},

	/**
	 * Toggle the confirmation state of a single suggestion of an image
	 *
	 * @param {Object} state
	 * @param {Object} suggestion
	 * @param {string} suggestion.wikidataId
	 */
	toggleSuggestion: function ( state, suggestion ) {
		var currentImage = state.images[ state.currentTab ][ 0 ],
			selected = currentImage.suggestions.find( function ( s ) {
				return s.wikidataId === suggestion.wikidataId;
			} );

		selected.confirmed = !selected.confirmed;
	},

	/**
	 * Set publish pending status.
	 *
	 * @param {Object} state
	 * @param {boolean} publishPendingStatus
	 */
	setPublishPending: function ( state, publishPendingStatus ) {
		state.publishPending = publishPendingStatus;
	},

	/**
	 * Set user stats (number of images to review and total images labelled).
	 *
	 * @param {Object} state
	 * @param {Object} payload
	 */
	setUserStats: function ( state, payload ) {
		state.userStats = payload;
	},

	/**
	 * Set the initial count of user's unreviewed images
	 *
	 * @param {Object} state
	 * @param {number} count
	 */
	setUnreviewedCount: function ( state, count ) {
		state.unreviewedCount = count;
	},

	/**
	 * @param {Object} state
	 */
	decrementUnreviewedCount: function ( state ) {
		state.unreviewedCount--;
	},

	/**
	 * Add a new image message to the store.
	 *
	 * @param {Object} state
	 * @param {Object} messageData
	 * @param {string} messageData.key Unique key for the toast component
	 * @param {string} messageData.messageKey The i18n message key to display
	 * @param {string} messageData.type The message type (success, error, etc.)
	 * @param {number} messageData.duration Display duration in seconds
	 */
	setImageMessage: function ( state, messageData ) {
		state.imageMessages = state.imageMessages.concat( [ messageData ] );
	},

	/**
	 * Remove an image message from the store.
	 *
	 * @param {Object} state
	 * @param {string} key Unique key of the Vue component to be hidden
	 */
	removeImageMessage: function ( state, key ) {
		state.imageMessages = state.imageMessages.filter( function ( message ) {
			return message.key !== key;
		} );
	},

	/**
	 * Toggle expansion of tag details.
	 *
	 * @param {Object} state
	 */
	toggleTagDetails: function ( state ) {
		state.tagDetailsExpanded = !state.tagDetailsExpanded;
	}
};
