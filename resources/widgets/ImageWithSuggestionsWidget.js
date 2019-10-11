/* global wikibase */
'use strict';

var TemplateRenderingDOMLessGroupWidget = require( './../base/TemplateRenderingDOMLessGroupWidget.js' ),
	SuggestionsGroupWidget = require( './SuggestionsGroupWidget.js' ),
	ConfirmTagsDialog = require( './ConfirmTagsDialog.js' ),
	ImageWithSuggestionsWidget,
	deepArrayCopy,
	moveItemBetweenArrays;

/**
 * A card within the cardstack on the Suggested Tags page. Each card contains
 * an image and a SuggestionsGroupWidget.
 *
 * @param {Object} config
 */
ImageWithSuggestionsWidget = function ( config ) {
	ImageWithSuggestionsWidget.parent.call( this, $.extend( {}, config ) );

	this.$element.addClass( 'wbmad-image-with-suggestions' );

	this.imageData = config.imageData;
	this.suggestions = this.imageData.suggestions;
	this.originalSuggestions = deepArrayCopy( this.suggestions );
	this.confirmedSuggestions = [];
	this.imageTitle = this.imageData.title.split( ':' ).pop();

	this.suggestionGroupWidget = new SuggestionsGroupWidget( {
		label: this.imageTitle,
		suggestionDataArray: this.originalSuggestions,
		confirmedSuggestionDataArray: this.confirmedSuggestions
	} ).connect( this, {
		confirmSuggestion: 'onConfirmSuggestion',
		unconfirmSuggestion: 'onUnconfirmSuggestion'
	} );

	this.skipButton = new OO.ui.ButtonWidget( {
		classes: [ 'wbmad-skip-button' ],
		title: mw.message( 'machinevision-skip-title', this.imageTitle ).text(),
		label: mw.message( 'machinevision-skip' ).text(),
		framed: false
	} ).on( 'click', this.onSkip, [], this );

	this.resetButton = new OO.ui.ButtonWidget( {
		classes: [ 'wbmad-button-reset' ],
		title: mw.message( 'machinevision-reset-title' ).text(),
		label: mw.message( 'machinevision-reset' ).text(),
		framed: false,
		disabled: true
	} ).on( 'click', this.onReset, [], this );

	this.publishButton = new OO.ui.ButtonWidget( {
		classes: [ 'wbmad-publish-button' ],
		title: mw.message( 'machinevision-publish-title' ).text(),
		label: mw.message( 'machinevision-publish' ).text(),
		disabled: true,
		flags: [
			'primary',
			'progressive'
		]
	} ).on( 'click', this.onPublish, [], this );

	this.api = wikibase.api.getLocationAgnosticMwApi(
		mw.config.get( 'wbmiRepoApiUrl', mw.config.get( 'wbRepoApiUrl' ) )
	);

	this.connect( this, { confirm: 'onFinalConfirm' } );

	this.render();
};

OO.inheritClass( ImageWithSuggestionsWidget, TemplateRenderingDOMLessGroupWidget );

ImageWithSuggestionsWidget.prototype.render = function () {
	this.renderTemplate( 'resources/widgets/ImageWithSuggestionsWidget.mustache+dom', {
		skipButton: this.skipButton,
		imageTagTitle: this.imageTitle,
		suggestions: this.suggestionGroupWidget,
		thumburl: this.imageData.thumburl,
		resetButton: this.resetButton,
		publishButton: this.publishButton
	} );
};

deepArrayCopy = function ( array ) {
	return $.extend( true, [], array );
};

ImageWithSuggestionsWidget.prototype.getOriginalSuggestions = function () {
	return deepArrayCopy( this.originalSuggestions );
};

moveItemBetweenArrays = function ( item, fromArray, toArray ) {
	var fromIndex;

	if ( toArray.indexOf( item ) === -1 ) {
		toArray.push( item );
		fromIndex = fromArray.indexOf( item );
		if ( fromIndex > -1 ) {
			fromArray.splice( fromIndex, 1 );
		}
	}
};

ImageWithSuggestionsWidget.prototype.rerenderGroups = function () {
	var isAnythingSelected;

	// TODO: Implement a setData() method or similar to avoid direct
	// manipulation of child widget properties
	this.suggestionGroupWidget.suggestionDataArray = this.originalSuggestions;
	this.suggestionGroupWidget.confirmedSuggestionDataArray = this.confirmedSuggestions;
	this.suggestionGroupWidget.render();

	isAnythingSelected = this.confirmedSuggestions.length > 0;

	this.publishButton.setDisabled( !isAnythingSelected );
	this.resetButton.setDisabled( !isAnythingSelected );
};

ImageWithSuggestionsWidget.prototype.onConfirmSuggestion = function ( suggestionWidget ) {
	moveItemBetweenArrays(
		suggestionWidget.suggestionData,
		this.suggestions,
		this.confirmedSuggestions
	);

	this.rerenderGroups();
};

ImageWithSuggestionsWidget.prototype.onUnconfirmSuggestion = function ( suggestionWidget ) {
	moveItemBetweenArrays(
		suggestionWidget.suggestionData,
		this.confirmedSuggestions,
		this.suggestions
	);

	this.rerenderGroups();
};

ImageWithSuggestionsWidget.prototype.onConfirmAll = function () {
	this.suggestions = [];
	this.confirmedSuggestions = this.getOriginalSuggestions();
	this.rerenderGroups();
};

ImageWithSuggestionsWidget.prototype.onReset = function () {
	this.suggestions = this.getOriginalSuggestions();
	this.confirmedSuggestions = [];
	this.rerenderGroups();
};

/**
 * Show a dialog prmopting user to confirm tags before publishing.
 */
ImageWithSuggestionsWidget.prototype.onPublish = function () {
	var self = this,
		tagsList = this.confirmedSuggestions.map( function ( suggestion ) {
			return suggestion.text;
		} ).join( ', ' ),
		confirmTagsDialog,
		windowManager;

	confirmTagsDialog = new ConfirmTagsDialog( {
		tagsList: tagsList,
		imgUrl: this.imageData.thumburl,
		imgTitle: this.imageTitle,
		imgDescription: this.imageData.description
	} )
		.connect( self, { confirm: 'onFinalConfirm' } );

	windowManager = new OO.ui.WindowManager();
	$( document.body ).append( windowManager.$element );

	windowManager.addWindows( [ confirmTagsDialog ] );
	windowManager.openWindow( confirmTagsDialog );
};

/**
 * Publish new tags and move to the next image.
 */
ImageWithSuggestionsWidget.prototype.onFinalConfirm = function () {
	// TODO: keep approved/rejected state in the SuggestionData model rather
	// than bouncing suggestions between two different arrays
	var self = this,
		batch = [];
	this.confirmedSuggestions.forEach( function ( suggestion ) {
		batch.push( { label: suggestion.wikidataId, review: 'accept' } );
	} );
	this.suggestions.forEach( function ( suggestion ) {
		batch.push( { label: suggestion.wikidataId, review: 'reject' } );
	} );
	this.api.postWithToken(
		'csrf',
		{
			action: 'reviewimagelabels',
			filename: this.imageTitle,
			batch: JSON.stringify( batch )
		}
	)
		// eslint-disable-next-line no-unused-vars
		.done( function ( result ) {
			// Show success message.
			self.emit( 'tagsPublished' );
		} )
		// eslint-disable-next-line no-unused-vars
		.fail( function ( errorCode, error ) {
			// TODO: indicate failure
		} )
		.always( function () {
			// Move to the next image.
			self.onSkip();
		} );
};

/**
 * Remove this image. As a result, the next image will display (via CSS).
 *
 * @fires itemRemoved
 */
ImageWithSuggestionsWidget.prototype.onSkip = function () {
	this.$element.remove();

	// Emit an event so parent element can see if we need to fetch more images.
	this.emit( 'itemRemoved' );
};

module.exports = ImageWithSuggestionsWidget;
