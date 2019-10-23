'use strict';

var TemplateRenderingDOMLessGroupWidget = require( '../base/TemplateRenderingDOMLessGroupWidget.js' ),
	ImageWithSuggestionsWidget = require( './ImageWithSuggestionsWidget.js' ),
	PersonalUploadsCount = require( './PersonalUploadsCount.js' ),
	UserMessage = require( './UserMessage.js' ),
	SuggestedTagsCardstack;

/**
 * Container element for ImageWithSuggestionsWidgets. This element fetches a set
 * number of items initially, then checks each time an item is published or
 * skipped to see when another batch of items needs to be fetched.
 *
 * @param {Object} config
 * @cfg {string} queryType
 * @cfg {bool} resultsFound
 * @cfg {Array} imageDataArray
 * @cfg {number} userImageCount
 */
SuggestedTagsCardstack = function ( config ) {
	this.config = config || {};
	SuggestedTagsCardstack.parent.call( this, $.extend( {}, config ) );

	this.$element.addClass( 'wbmad-suggested-tags-cardstack' );
	this.connect( this, {
		itemRemoved: 'onItemRemoved',
		tagsPublished: 'onTagsPublished',
		publishError: 'onPublishError'
	} );

	this.queryType = this.config.queryType;
	this.resultsFound = this.config.resultsFound;
	this.imageDataArray = this.config.imageDataArray;
	this.countString = ( this.queryType === 'user' ) ?
		new PersonalUploadsCount( { userImageCount: this.config.userImageCount } ) :
		null;

	this.items = this.getItems();
	this.render();
};

OO.inheritClass(
	SuggestedTagsCardstack,
	TemplateRenderingDOMLessGroupWidget
);

SuggestedTagsCardstack.prototype.render = function () {
	var countString = this.countString,
		showCta = !this.resultsFound && this.queryType === 'user',
		config = {
			heading: mw.message( 'machinevision-cta-heading' ).text(),
			text: mw.message( 'machinevision-cta-text' ).text(),
			cta: mw.message( 'machinevision-cta-cta' ).text(),
			event: 'popularTabCtaClick'
		};

	this.renderTemplate( 'resources/widgets/SuggestedTagsCardstack.mustache+dom', {
		queryType: this.queryType,
		countString: countString,
		items: this.items,
		showCta: showCta,
		cta: new UserMessage( config ).connect( this, { popularTabCtaClick: 'onPopularTabCtaClick' } )
	} );
};

/**
 * Get an array of ImageWithSuggestionWidgets based on query results.
 * @return {Array|null}
 */
SuggestedTagsCardstack.prototype.getItems = function () {
	var self = this;

	if ( !this.resultsFound ) {
		return null;
	}

	return this.imageDataArray.map( function ( imageData ) {
		return new ImageWithSuggestionsWidget( {
			imageData: imageData
		} ).connect( self, {
			itemRemoved: 'onItemRemoved',
			tagsPublished: 'onTagsPublished',
			publishError: 'onPublishError'
		} );
	} );
};

/**
 * Tell the parent component to go to the popular tab.
 */
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
		this.emit( 'fetchItems' );
	}
};

/**
 * After user publishes tags for an image, show a success message.
 */
SuggestedTagsCardstack.prototype.onTagsPublished = function () {
	this.emit( 'showSuccessMessage' );

	if ( this.countString ) {
		this.countString.userImageCount--;
		this.countString.render();
	}
};

/**
 * After an error on publish, show user a message describing the issue.
 */
SuggestedTagsCardstack.prototype.onPublishError = function () {
	this.emit( 'showPublishErrorMessage' );
};

module.exports = SuggestedTagsCardstack;
