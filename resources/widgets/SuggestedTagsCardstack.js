'use strict';

var IMAGES_PER_PAGE = 10,
	TemplateRenderingDOMLessGroupWidget = require( '../base/TemplateRenderingDOMLessGroupWidget.js' ),
	ImageWithSuggestionsWidget = require( './ImageWithSuggestionsWidget.js' ),
	UserMessage = require( './UserMessage.js' ),
	SuggestionData = require( '../models/SuggestionData.js' ),
	ImageData = require( '../models/ImageData.js' ),
	SuggestedTagsCardstack,
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
SuggestedTagsCardstack = function ( config ) {
	this.config = config || {};
	SuggestedTagsCardstack.parent.call( this, $.extend( {}, config ) );

	this.queryType = this.config.queryType;
	this.$element.addClass( 'wbmad-suggested-tags-cardstack' );
	this.connect( this, {
		itemRemoved: 'onItemRemoved',
		tagsPublished: 'onTagsPublished'
	} );

	this.needLogin = this.queryType === 'user' && !mw.config.get( 'wgUserName' );
	this.isLoading = true;
	this.hasError = false;
	this.showCta = false;

	this.render();

	// Fetch the first batch of items.
	if ( !this.needLogin ) {
		this.fetchItems();
	}
};

OO.inheritClass(
	SuggestedTagsCardstack,
	TemplateRenderingDOMLessGroupWidget
);

SuggestedTagsCardstack.prototype.render = function () {
	var config = {
		heading: mw.message( 'machinevision-cta-heading' ).text(),
		text: mw.message( 'machinevision-cta-text' ).text(),
		cta: mw.message( 'machinevision-cta-cta' ).text(),
		event: 'popularTabCtaClick'
	};

	this.renderTemplate( 'resources/widgets/SuggestedTagsCardstack.mustache+dom', {
		queryType: this.queryType,
		needLogin: this.needLogin,
		isLoading: this.isLoading,
		hasError: this.hasError,
		showCta: this.showCta,
		cta: new UserMessage( config ).connect( this, { popularTabCtaClick: 'onPopularTabCtaClick' } ),
		loginMessage: $( '<p>' ).msg( 'machinevision-personal-uploads-login-message' )
	} );
};

SuggestedTagsCardstack.prototype.onPopularTabCtaClick = function () {
	this.emit( 'goToPopularTab' );
};

/**
 * Each time an image is removed (published or skipped), check if new ones need
 * to be fetched. When there are no items remaining, fetch another batch (unless
 * no results were found last time).
 *
 * TODO: (T233232) Improve loading experience.
 */
SuggestedTagsCardstack.prototype.onItemRemoved = function () {
	// If there are no more image cards, fetch more.
	// TODO: Do we need the resultsFound check?
	if ( this.resultsFound && this.$element.find( '.wbmad-image-with-suggestions' ).length === 0 ) {
		this.fetchItems();
	}
};

/**
 * After user publishes tags for an image, show a success message.
 */
SuggestedTagsCardstack.prototype.onTagsPublished = function () {
	var successMessage = new OO.ui.MessageWidget( {
		label: mw.message( 'machinevision-success-message' ).text(),
		classes: [ 'wbmad-success-message' ]
	} );
	this.$element.append( successMessage.$element );

	setTimeout( function () {
		successMessage.$element.remove();
	}, 4000 );
};

/**
 * Build a query URL.
 *
 * @param {number} count
 * @param {string} queryType
 * @return {string}
 */
queryURLWithCount = function ( count, queryType ) {
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

	if ( queryType === 'user' ) {
		query.guiluploader = mw.user.getId();
	}

	urlString = mw.config.get( 'wgServer' ) +
		mw.config.get( 'wgScriptPath' ) + '/api.php?';

	urlString += Object.keys( query ).map( function ( key ) {
		return key + '=' + query[ key ];
	} ).join( '&' );

	return urlString;
};

/**
 * Get a formatted object of image data.
 *
 * @param {Object} item An item from the query response
 * @return {Object}
 */
getImageDataForQueryResponse = function ( item ) {
	if ( item.imageinfo && item.imagelabels && item.imagelabels.length ) {
		return new ImageData(
			item.title,
			item.imageinfo[ 0 ].thumburl,
			item.imagelabels.map( function ( labelData ) {
				return new SuggestionData( labelData.label, labelData.wikidata_id );
			} )
		);
	}
};

/**
 * Add items from the latest query to the pager.
 *
 * @param {Object} response
 */
SuggestedTagsCardstack.prototype.showItemsForQueryResponse = function ( response ) {
	var newWidget,
		self = this,
		imageDataArray = response.query && response.query.pages &&
		Array.isArray( response.query.pages ) ? response.query.pages.map( function ( page ) {
				return getImageDataForQueryResponse( page );
			} ) : [];

	this.resultsFound = !!imageDataArray.length;

	// Clear out loading message.
	// TODO: (T233232) Remove this.
	$( '#wbmad-suggested-tags-cards-' + this.queryType ).empty();

	if ( this.resultsFound ) {
		// Append a new ImageWithSuggestionsWidget element for each item.
		$( '#wbmad-suggested-tags-cards-' + this.queryType ).append(
			imageDataArray.map( function ( imageData ) {
				newWidget = new ImageWithSuggestionsWidget( {
					imageData: imageData
				} );
				newWidget.connect( self, {
					itemRemoved: 'onItemRemoved',
					tagsPublished: 'onTagsPublished'
				} );
				return newWidget.$element;
			} )
		);
	}

	if ( !this.resultsFound && this.queryType === 'user' ) {
		this.showCta = true;
		this.render();
	}
};

/**
 * Fetch a batch of items.
 */
SuggestedTagsCardstack.prototype.fetchItems = function () {
	showLoadingMessage( this.queryType );

	// Do the query with the appropriate # items
	// Then show the page (or a failure message);
	$.getJSON( queryURLWithCount( IMAGES_PER_PAGE, this.config.queryType ) )
		.done( this.showItemsForQueryResponse.bind( this ) )
		.fail( showFailureMessage );
};

// TODO: (T233232) Add "failed" state and show content in the template instead.
showFailureMessage = function () {
	// $( '#content' ).append( '<p>Oh no, something went wrong!</p>' );
};

// TODO: (T233232) Add "loading" state and show content in the template instead.
showLoadingMessage = function ( queryType ) {
	$( '#wbmad-suggested-tags-cards-' + queryType ).append(
		'<p class="wbmad-loading-message">' + mw.message( 'machinevision-loading-message' ).text() + '</p>'
	);
};

module.exports = SuggestedTagsCardstack;
