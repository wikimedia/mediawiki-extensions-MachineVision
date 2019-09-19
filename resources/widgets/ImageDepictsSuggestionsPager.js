'use strict';

// TODO: remove jQuery global selectors
/* eslint-disable no-jquery/no-global-selector */

var IMAGES_PER_PAGE = 10,
	TemplateRenderingDOMLessGroupWidget = require( './../base/TemplateRenderingDOMLessGroupWidget.js' ),
	ImageWithSuggestionsWidget = require( './ImageWithSuggestionsWidget.js' ),
	SuggestionData = require( './../models/SuggestionData.js' ),
	ImageData = require( './../models/ImageData.js' ),
	ImageDepictsSuggestionsPager,
	randomDescription,
	showFailureMessage,
	showLoadingMessage,
	queryURLWithCount,
	getImageDataForQueryResponse;

/**
 * Container element for ImageWithSuggestionsWidgets. This element fetches a set
 * number of items initially, then checks each time an item is published or
 * skipped to see when another batch of items needs to be fetched.
 *
 * @param {Object} config
 */
ImageDepictsSuggestionsPager = function ( config ) {
	ImageDepictsSuggestionsPager.parent.call( this, $.extend( {}, config ) );

	this.$element.addClass( 'wbmad-suggested-tags-pager' );
	this.connect( this, { itemRemoved: 'onItemRemoved' } );

	// TODO: move this to a parent component.
	this.descriptionLabel = new OO.ui.LabelWidget( {
		label: mw.message( 'machinevision-machineaidedtagging-intro' ).text()
	} );

	this.render();

	// Fetch the first batch of items.
	this.fetchItems();
};

OO.inheritClass(
	ImageDepictsSuggestionsPager,
	TemplateRenderingDOMLessGroupWidget
);

ImageDepictsSuggestionsPager.prototype.render = function () {
	this.renderTemplate( 'resources/widgets/ImageDepictsSuggestionsPager.mustache+dom', {
		descriptionLabel: this.descriptionLabel
	} );
};

/**
 * Each time an image is removed (published or skipped), check if new ones need
 * to be fetched. When there are no items remaining, fetch another batch (unless
 * no results were found last time).
 *
 * TODO: (T233232) Improve loading experience.
 */
ImageDepictsSuggestionsPager.prototype.onItemRemoved = function () {
	if ( this.resultsFound && $( '.wbmad-image-with-suggestions' ).length === 0 ) {
		this.fetchItems();
	}
};

/**
 * Build a query URL.
 *
 * @param {number} count
 * @return {string}
 */
queryURLWithCount = function ( count ) {
	var query, urlString;

	query = {
		action: 'query',
		format: 'json',
		formatversion: 2,
		generator: 'unreviewedimagelabels',
		guillimit: count,
		prop: 'imageinfo|imagelabels',
		iiprop: 'url',
		iiurlwidth: 800,
		ilstate: 'unreviewed'
	};

	urlString = mw.config.get( 'wgServer' ) +
		mw.config.get( 'wgScriptPath' ) + '/api.php?';

	urlString += Object.keys( query ).map( function ( key ) {
		return key + '=' + query[ key ];
	} ).join( '&' );

	return urlString;
};

// TODO: remove.
randomDescription = function () {
	var array, randomNumber;

	array = [
		'This is a thing. This is a random description.',
		'This is another such thing. This is a random description.',
		'This is some other stuff. This is a random description.',
		'This is a dodad. This is a random description.'
	];

	randomNumber = Math.floor( Math.random() * array.length );

	return array[ randomNumber ];
};

/**
 * Get a formatted object of image data.
 *
 * @param {Object} item An item from the query response
 * @return {Object}
 */
getImageDataForQueryResponse = function ( item ) {
	// TODO: grab actual description and suggestions from middleware endpoint
	// once it exists, then delete the random methods and the
	// `thumbwidth != // 320` check (which the middleware will enforce)
	if ( item.imageinfo && item.imagelabels && item.imagelabels.length ) {
		return new ImageData(
			item.title,
			item.imageinfo[ 0 ].thumburl,
			randomDescription(),
			item.imagelabels.map( function ( labelData ) {
				return new SuggestionData( labelData.label );
			} )
		);
	}
};

/**
 * Add items from the latest query to the pager.
 *
 * @param {Object} response
 */
ImageDepictsSuggestionsPager.prototype.showItemsForQueryResponse = function ( response ) {
	var newWidget,
		self = this,
		imageDataArray = response.query.pages.map( function ( page ) {
			return getImageDataForQueryResponse( page );
		} );

	// Clear out loading message.
	// TODO: (T233232) Remove this.
	$( '#wbmad-suggested-tags-items' ).empty();

	// Append a new ImageWithSuggestionsWidget element for each item.
	$( '#wbmad-suggested-tags-items' ).append(
		imageDataArray.map( function ( imageData ) {
			newWidget = new ImageWithSuggestionsWidget( {
				imageData: imageData
			} );
			newWidget.connect( self, { itemRemoved: 'onItemRemoved' } );
			return newWidget.$element;
		} )
	);

	this.resultsFound = ( response.query.pages && response.query.pages.length > 0 );
};

/**
 * Fetch a batch of items.
 */
ImageDepictsSuggestionsPager.prototype.fetchItems = function () {
	showLoadingMessage();

	// Do the query with the appropriate # items
	// Then show the page (or a failure message);
	$.getJSON( queryURLWithCount( IMAGES_PER_PAGE ) )
		.done( this.showItemsForQueryResponse.bind( this ) )
		.fail( showFailureMessage );
};

// TODO: (T233232) Add "failed" state and show content in the template instead.
showFailureMessage = function () {
	// $( '#content' ).append( '<p>Oh no, something went wrong!</p>' );
};

// TODO: (T233232) Add "loading" state and show content in the template instead.
showLoadingMessage = function () {
	$( '#wbmad-suggested-tags-items' ).append(
		'<p class="wbmad-loading-message">' + mw.message( 'machinevision-loading-message' ).text() + '</p>'
	);
};

module.exports = ImageDepictsSuggestionsPager;
