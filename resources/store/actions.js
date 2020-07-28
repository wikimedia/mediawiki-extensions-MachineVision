'use strict';

/* eslint-disable no-implicit-globals */
var MvImage = require( '../models/Image.js' ),
	MvSuggestion = require( '../models/Suggestion.js' ),
	api = wikibase.api.getLocationAgnosticMwApi(
		mw.config.get( 'wbmiRepoApiUrl', mw.config.get( 'wbRepoApiUrl' ) )
	),
	datamodel = require( 'wikibase.datamodel' ),
	serialization = require( 'wikibase.serialization' ),
	mvConfig = require( '../config.json' ),
	ensureTabExists = require( './utils.js' ).ensureTabExists,
	getCategories = require( './utils.js' ).getCategories,
	queues = {
		POPULAR: 'popular',
		USER: 'user'
	};

module.exports = {
	/**
	 * @param {Object} context
	 * @param {string} tab
	 */
	updateCurrentTab: function ( context, tab ) {
		context.commit( 'setTab', tab );
	},

	/**
	 * Request images for review in the appropriate queue from the API.
	 * This action should return a promise so we can chain it.
	 *
	 * @param {Object} context
	 * @param {Object} options
	 * @param {string} [options.queue]
	 * @return {jQuery.Deferred} Promise
	 */
	getImages: function ( context, options ) {
		var queue = options && options.queue ? options.queue : context.state.currentTab,
			query = {
				action: 'query',
				format: 'json',
				formatversion: 2,
				generator: 'unreviewedimagelabels',
				guillimit: 10,
				prop: 'imageinfo|imagelabels|categories',
				iiprop: 'url',
				iiurlwidth: 800,
				ilstate: 'unreviewed',
				meta: 'unreviewedimagecount',
				uselang: mw.config.get( 'wgUserLanguage' ),
				cllimit: 500,
				clshow: '!hidden'
			};

		ensureTabExists( context.state, queue );

		if ( queue === queues.USER ) {
			query.guiluploader = mw.user.getId();
			query.ilstate = 'unreviewed|withheld';
		}

		// Set pending to true and reset error state.
		context.commit( 'setFetchPending', {
			queue: queue,
			pending: true
		} );
		context.commit( 'setFetchError', {
			queue: queue,
			error: false
		} );

		// Request images from the API
		return api.get( query ).then( function ( res ) {
			// @TODO is it really necessary to check for all of this?
			var responseIsValid,
				validItems = [],
				images = [];

			// Ensure only images with the data we need get passed along
			responseIsValid = res.query && res.query.pages && Array.isArray( res.query.pages );
			if ( responseIsValid ) {
				validItems = res.query.pages.filter( function ( item ) {
					return item.imageinfo && item.imagelabels && item.imagelabels.length;
				} );
			}

			// Return Image objects for each item in the API response
			images = validItems.map( function ( item ) {
				return new MvImage(
					item.title,
					item.pageid,
					item.imageinfo[ 0 ].descriptionurl,
					item.imageinfo[ 0 ].thumburl,
					item.imageinfo[ 0 ].thumbheight,
					item.imagelabels.map( function ( labelData ) {
						return new MvSuggestion(
							labelData.label,
							labelData.wikidata_id,
							labelData.alias,
							labelData.description
						);
					} ),
					getCategories( item )
				);
			} );

			// Commit the MvImage objects to the state in the appropriate queue
			images.forEach( function ( image ) {
				context.commit( 'addImage', {
					image: image,
					queue: queue
				} );
			} );

			try {
				// Commit user stats and current number of unreviewed personal
				// images into the store.
				context.commit( 'setUserStats', res.query.unreviewedimagecount.user );
				context.commit( 'setUnreviewedCount', res.query.unreviewedimagecount.user.unreviewed );
			} catch ( e ) {
				// Use default state values.
			}
		} ).catch( function ( /* errorCode, error */ ) {
			// Show a generic error message if fetch fails.
			context.commit( 'setFetchError', {
				queue: queue,
				error: true
			} );
		} ).always( function () {
			// Remove the fetch pending state
			context.commit( 'setFetchPending', {
				queue: queue,
				pending: false
			} );
		} );
	},

	/**
	 * @param {Object} context
	 * @param {Object} tag
	 */
	toggleTagConfirmation: function ( context, tag ) {
		context.commit( 'toggleSuggestion', tag );
	},

	/**
	 * submits user selections for current image to API and commits the
	 * mutations for removing image from queue
	 *
	 * @param {Object} context
	 */
	publishTags: function ( context ) {
		var displayedTags = context.getters.currentImageSuggestions,
			nonDisplayedTags = context.getters.currentImageNonDisplayableSuggestions,
			confirmedTags = displayedTags.filter( function ( tag ) {
				return tag.confirmed;
			} ),
			setClaimsRequest = context.dispatch( 'setDepictsStatements', confirmedTags ),
			isUserImage = context.state.currentTab === queues.USER,
			reviewBatch,
			reviewImageLabelsRequest,
			successToast = {
				messageKey: 'machinevision-success-message',
				type: 'success',
				duration: 4
			},
			errorToast = {
				messageKey: 'machinevision-publish-error-message',
				type: 'error',
				duration: 8
			};

		// Set the review state for non-user-provided tags which could be
		// displayed to the user
		reviewBatch = displayedTags.filter( function ( tag ) {
			return !tag.custom;
		} ).map( function ( tag ) {
			return {
				label: tag.wikidataId,
				review: tag.confirmed ? 'accept' : 'reject'
			};
		} );

		// If there were any tags which could not be displayed to the user in their language,
		// set them to the "not-displayed" state
		nonDisplayedTags.forEach( function ( tag ) {
			reviewBatch.push( {
				label: tag.wikidataId,
				review: 'not-displayed'
			} );
		} );

		reviewImageLabelsRequest = api.postWithToken( 'csrf', {
			action: 'reviewimagelabels',
			filename: context.getters.currentImageTitle,
			batch: JSON.stringify( reviewBatch )
		} );

		context.dispatch( 'updatePublishPending', true );

		// Set claims, review labels, show toast notification, and skip to next image
		$.when( setClaimsRequest, reviewImageLabelsRequest ).done( function () {
			context.dispatch( 'showImageMessage', successToast );
		} ).fail( function () {
			context.dispatch( 'showImageMessage', errorToast );
		} ).always( function () {
			context.dispatch( 'skipImage' );
			context.dispatch( 'updatePublishPending', false );

			// We're ready to wrap up at this point, either because
			// both requests have succeeded, or one of them has failed
			// If the reviewimagelabels request (and only if that one) succeeded,
			// we need to decrement the unreviewed count, regardless of whether
			// or not the setclaims request succeeded (in which case we lost data
			// that was submitted, but it's still gone from our unreviewed queue)
			return reviewImageLabelsRequest.then( function () {
				if ( isUserImage ) {
					context.commit( 'decrementUnreviewedCount' );
				}
			} );
		} );
	},

	/**
	 * Set a depicts statement on the current image for each confirmed tag
	 *
	 * @param {Object} context
	 * @param {Array} confirmedTags
	 * @return {jQuery.Deferred} jQuery Promise
	 */
	setDepictsStatements: function ( context, confirmedTags ) {
		var depictsPropertyId = mvConfig.depictsPropertyId,
			guidGenerator = new wikibase.utilities.ClaimGuidGenerator(
				context.getters.currentImageMediaInfoId
			),
			serializer = new serialization.StatementSerializer(),
			promise = $.Deferred().resolve().promise(),
			statements;

		// Create a depicts statement for the current image from each confirmed tag
		// Note: for local testing, you can hard-code tag.wikidataId to
		// something like 'Q1'
		statements = confirmedTags.map( function ( tag ) {
			return new datamodel.Statement(
				new datamodel.Claim(
					new datamodel.PropertyValueSnak(
						depictsPropertyId,
						new datamodel.EntityId( tag.wikidataId )
					),
					null, // qualifiers
					guidGenerator.newGuid()
				)
			);
		} );

		// send wbsetclaim calls one at a time to prevent edit conflicts
		statements.forEach( function ( statement, index ) {
			var correspondingTag = confirmedTags[ index ];

			promise = promise.then( function () {
				return api.postWithToken( 'csrf', {
					action: 'wbsetclaim',
					claim: JSON.stringify( serializer.serialize( statement ) ),
					ignoreduplicatemainsnak: true,
					tags: correspondingTag.custom ?
						'computer-aided-tagging-manual' :
						'computer-aided-tagging'
				} );
			} );
		} );

		return promise;
	},

	/**
	 * Discard the current image without publishing suggestions.
	 * TODO: Should also request more image data if necessary.
	 *
	 * @param {Object} context
	 */
	skipImage: function ( context ) {
		context.commit( 'removeImage' );
	},

	/**
	 * Set publish pending status so we can show a spinner during publish process.
	 *
	 * @param {Object} context
	 * @param {boolean} publishPendingStatus
	 */
	updatePublishPending: function ( context, publishPendingStatus ) {
		context.commit( 'setPublishPending', publishPendingStatus );
	},

	/**
	 * Adds a user-provided tag to the current image suggestions.
	 * Custom tags have their "confirmed" and "custom" props set to true
	 * before they are added.
	 *
	 * @param {Object} context
	 * @param {Object} tag
	 * @param {string} tag.text
	 * @param {string} tag.wikidataId
	 */
	addCustomTag: function ( context, tag ) {
		var suggestion = new MvSuggestion( tag.text, tag.wikidataId );

		suggestion.custom = true; // Set this so we can filter out user-added suggestions on submit
		suggestion.confirmed = true;

		context.commit( 'addSuggestionToCurrentImage', suggestion );
	},

	/**
	 * Display a message related to a specific image.
	 * In practice, we're using this for toast notifications after publish.
	 *
	 * @param {Object} context
	 * @param {Object} messageData
	 * @param {string} messageData.messageKey The i18n message key to display
	 * @param {string} messageData.type The message type (success, error, etc.)
	 * @param {number} messageData.duration Display duration in seconds
	 */
	showImageMessage: function ( context, messageData ) {
		messageData.key = messageData.type + Date.now();
		context.commit( 'setImageMessage', messageData );
	},

	/**
	 * Hide a message related to a specific image.
	 *
	 * @param {Object} context
	 * @param {string} key Unique key of the Vue component to be hidden
	 */
	hideImageMessage: function ( context, key ) {
		context.commit( 'removeImageMessage', key );
	},

	/**
	 * Toggle expansion of tag details.
	 *
	 * @param {Object} context
	 */
	toggleTagDetails: function ( context ) {
		context.commit( 'toggleTagDetails' );
	}
};
