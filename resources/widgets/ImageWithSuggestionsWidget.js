/* global wikibase */
'use strict';

var TemplateRenderingDOMLessGroupWidget = require( './../base/TemplateRenderingDOMLessGroupWidget.js' ),
	SuggestionWidget = require( './SuggestionWidget.js' ),
	ConfirmTagsDialog = require( './ConfirmTagsDialog.js' ),
	ImageWithSuggestionsWidget;

/**
 * A card within the cardstack on the Suggested Tags page. Each card contains
 * an image and a group of SuggestionWidgets.
 *
 * @param {Object} config
 * @cfg {Object} imageData
 */
ImageWithSuggestionsWidget = function ( config ) {
	ImageWithSuggestionsWidget.parent.call( this, $.extend( {}, config ) );

	this.$element.addClass( 'wbmad-image-with-suggestions' );

	this.imageData = config.imageData;
	this.suggestions = this.imageData.suggestions;
	this.suggestionWidgets = this.getSuggestionWidgets();
	this.confirmedCount = 0;
	this.imageTitle = this.imageData.title.split( ':' ).pop();
	this.filePageUrl = this.imageData.descriptionurl;

	this.titleLabel = new OO.ui.LabelWidget( {
		label: this.imageTitle,
		classes: [ 'wbmad-suggestion-group-title-label' ]
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

	this.connect( this, {
		confirm: 'onFinalConfirm',
		toggleSuggestion: 'onToggleSuggestion'
	} );

	this.render();
};

OO.inheritClass( ImageWithSuggestionsWidget, TemplateRenderingDOMLessGroupWidget );

ImageWithSuggestionsWidget.prototype.render = function () {
	this.renderTemplate( 'resources/widgets/ImageWithSuggestionsWidget.mustache+dom', {
		skipButton: this.skipButton,
		imageTagTitle: this.imageTitle,
		titleLabel: this.titleLabel,
		suggestions: this.suggestionWidgets,
		thumburl: this.imageData.thumburl,
		filePageUrl: this.filePageUrl,
		resetButton: this.resetButton,
		publishButton: this.publishButton,
		showSpinner: this.showSpinner,
		spinnerClass: ( this.showSpinner ) ? 'wbmad-spinner-active' : ''
	} );
};

/**
 * Create an array of suggestion widgets based on suggestion data.
 * @return {Array}
 */
ImageWithSuggestionsWidget.prototype.getSuggestionWidgets = function () {
	var self = this,
		validSuggestions = this.suggestions.filter( function ( suggestion ) {
			return !!suggestion.text;
		} );

	return validSuggestions.map( function ( data ) {
		return new SuggestionWidget( { suggestionData: data } )
			.connect( self, { toggleSuggestion: 'onToggleSuggestion' } );
	} );
};

/**
 * When a suggestion is toggled, see if buttons should be disabled.
 *
 * This widget has a property keeping track of how many suggestions are
 * currently confirmed. When this value is 0, the publish and reset buttons
 * should be disabled.
 *
 * @param {bool} confirmed Whether or not the suggestion is confirmed
 */
ImageWithSuggestionsWidget.prototype.onToggleSuggestion = function ( confirmed ) {
	var addend = ( confirmed ) ? 1 : -1,
		hasConfirmed;

	// If the suggestion is confirmed, add 1 to count. If not, that means it
	// has been un-confirmed, so subtract 1.
	this.confirmedCount = this.confirmedCount + addend;
	hasConfirmed = this.confirmedCount > 0;

	this.publishButton.setDisabled( !hasConfirmed );
	this.resetButton.setDisabled( !hasConfirmed );
};

/**
 * Set all suggestion widgets to unconfirmed.
 */
ImageWithSuggestionsWidget.prototype.onReset = function () {
	this.suggestionWidgets.forEach( function ( widget ) {
		widget.confirmed = false;
		widget.render();
	} );
	this.confirmedCount = 0;
};

/**
 * Show a dialog prmopting user to confirm tags before publishing.
 */
ImageWithSuggestionsWidget.prototype.onPublish = function () {
	var self = this,
		confirmedSuggestions = this.suggestionWidgets.filter( function ( widget ) {
			return widget.confirmed;
		} ),
		tagsList = confirmedSuggestions.map( function ( widget ) {
			return widget.suggestionData.text;
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
	var self = this,
		batch = this.suggestionWidgets.map( function ( widget ) {
			return {
				label: widget.suggestionData.wikidataId,
				review: widget.confirmed ? 'accept' : 'reject'
			};
		} );

	this.showSpinner = true;
	this.publishButton.setDisabled( true );
	this.resetButton.setDisabled( true );
	this.skipButton.setDisabled( true );
	this.render();

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
			self.emit( 'publishError' );
		} )
		.always( function () {
			// Move to next image.
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
