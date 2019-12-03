'use strict';

var TemplateRenderingDOMLessGroupWidget = require( '../base/TemplateRenderingDOMLessGroupWidget.js' ),
	ImageWithSuggestionsWidget = require( './ImageWithSuggestionsWidget.js' ),
	PersonalUploadsCount = require( './PersonalUploadsCount.js' ),
	UserMessage = require( './UserMessage.js' );

/**
 * Container element for ImageWithSuggestionsWidgets. This element fetches a set
 * number of items initially, then checks each time an item is published or
 * skipped to see when another batch of items needs to be fetched.
 *
 * @param {Object} config
 * @cfg {string} queryType
 * @cfg {Array} imageDataArray
 * @cfg {number} userUnreviewedImageCount
 * @cfg {number} userTotalImageCount
 */
function SuggestedTagsCardstack( config ) {
	this.config = config || {};
	SuggestedTagsCardstack.parent.call( this, $.extend( {}, config ) );

	this.$element.addClass( 'wbmad-suggested-tags-cardstack' );
	this.connect( this, {
		itemRemoved: 'onItemRemoved',
		tagsPublished: 'onTagsPublished',
		publishError: 'onPublishError'
	} );

	this.queryType = this.config.queryType;
	this.imageDataArray = this.config.imageDataArray;
	this.resultsFound = this.imageDataArray.length !== 0;
	this.countString = ( this.queryType === 'user' ) ?
		new PersonalUploadsCount( {
			unreviewed: this.config.userUnreviewedImageCount
		} ) :
		null;
	this.userHasLabeledUploads = this.config.userTotalImageCount > 0;

	this.items = this.getItems();
	this.showCurrentItem();
	this.render();
}

OO.inheritClass(
	SuggestedTagsCardstack,
	TemplateRenderingDOMLessGroupWidget
);

SuggestedTagsCardstack.prototype.render = function () {
	var countString = this.countString,
		showCta = !this.resultsFound && this.queryType === 'user',
		config = {
			className: this.userHasLabeledUploads ? 'wbmad-user-cta' : 'wbmad-user-cta--no-uploads',
			heading: mw.message( this.userHasLabeledUploads ? 'machinevision-cta-heading' : 'machinevision-no-uploads-cta-heading' ).parse(),
			text: mw.message( this.userHasLabeledUploads ? 'machinevision-cta-text' : 'machinevision-no-uploads-cta-text' ).parse(),
			cta: mw.message( 'machinevision-cta-cta' ).parse(),
			event: 'popularTabCtaClick'
		};

	this.renderTemplate( 'widgets/SuggestedTagsCardstack.mustache+dom', {
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
		return new ImageWithSuggestionsWidget( imageData, self.queryType )
			.connect( self, {
				itemRemoved: 'onItemRemoved',
				tagsPublished: 'onTagsPublished',
				publishError: 'onPublishError'
			} );
	} );
};

SuggestedTagsCardstack.prototype.showCurrentItem = function () {
	if ( this.items && this.items.length > 0 ) {
		this.items[ 0 ].loadImage();
	}
};

/**
 * Each time an image is removed (published or skipped), check if new ones need
 * to be fetched. When there are no items remaining, fetch another batch (unless
 * no results were found last time).
 */
SuggestedTagsCardstack.prototype.onItemRemoved = function () {
	// If there are no more image cards, fetch more.
	if ( this.$element.find( '.wbmad-image-with-suggestions' ).length === 0 ) {
		this.emit( 'fetchItems' );
	} else {
		// Otherwise, load the image for the next card.
		this.items.shift();
		this.showCurrentItem();
	}
};

/**
 * After user publishes tags for an image, show a success message.
 */
SuggestedTagsCardstack.prototype.onTagsPublished = function () {
	this.emit( 'showSuccessMessage' );

	if ( this.countString ) {
		this.countString.unreviewed--;
		this.countString.render();
	}
};

/**
 * After an error on publish, show user a message describing the issue.
 */
SuggestedTagsCardstack.prototype.onPublishError = function () {
	this.emit( 'showPublishErrorMessage' );
};

/**
 * Tell the parent component to go to the popular tab.
 */
SuggestedTagsCardstack.prototype.onPopularTabCtaClick = function () {
	this.emit( 'goToPopularTab' );
};

module.exports = SuggestedTagsCardstack;
