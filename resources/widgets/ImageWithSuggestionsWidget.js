'use strict';

var TemplateRenderingDOMLessGroupWidget = require( './../base/TemplateRenderingDOMLessGroupWidget.js' ),
	SuggestionsGroupWidget = require( './SuggestionsGroupWidget.js' ),
	ImageWithSuggestionsWidget,
	deepArrayCopy,
	moveItemBetweenArrays;

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

	this.render();
};

OO.inheritClass( ImageWithSuggestionsWidget, TemplateRenderingDOMLessGroupWidget );

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

ImageWithSuggestionsWidget.prototype.getPublishDebugString = function () {
	var debugString;

	debugString = 'IMAGE:\n' +
		this.imageTitle;

	debugString += '\n\nDEPICTS:\n';

	debugString += this.confirmedSuggestions.map( function ( suggestion ) {
		return suggestion.text;
	} ).join( ', ' );

	return debugString;
};

ImageWithSuggestionsWidget.prototype.onPublish = function () {
	// TODO: wire up to middleware 'save' endpoint once it exists
	/* eslint-disable-next-line no-alert */
	if ( confirm( this.getPublishDebugString() ) ) {
		this.onSkip();
	}
};

/**
 * Handle removed card.
 *
 * @fires itemRemoved
 */
ImageWithSuggestionsWidget.prototype.onSkip = function () {
	this.$element.remove();

	// Emit an event so parent element can see if we need to fetch more images.
	this.emit( 'itemRemoved' );
};

ImageWithSuggestionsWidget.prototype.render = function () {
	this.renderTemplate( 'resources/widgets/ImageWithSuggestionsWidget.mustache+dom', {
		skipButton: this.skipButton,
		imageDescriptionLabel: this.imageDescriptionLabel,
		imageTagTitle: this.imageTitle + '\n' + this.imageData.description,
		suggestions: this.suggestionGroupWidget,
		thumburl: this.imageData.thumburl,
		resetButton: this.resetButton,
		publishButton: this.publishButton
	} );
};

module.exports = ImageWithSuggestionsWidget;
