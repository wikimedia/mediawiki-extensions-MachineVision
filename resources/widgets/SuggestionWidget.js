'use strict';

var TemplateRenderingDOMLessGroupWidget = require( './../base/TemplateRenderingDOMLessGroupWidget.js' ),
	SuggestionWidget;

/**
 * A single suggested tag for an image.
 *
 * @param {Object} config
 * @cfg {Object} suggestionData
 * @cfg {bool} confirmed Whether or not the suggestion has been confirmed
 */
SuggestionWidget = function ( config ) {
	var iconText;

	this.confirmed = config.confirmed;
	this.suggestionData = config.suggestionData;

	SuggestionWidget.parent.call( this, $.extend( {}, config ) );
	this.$element.addClass( 'wbmad-suggestion' );

	if ( this.confirmed ) {
		this.$element.addClass( 'wbmad-suggestion--confirmed' );
	}

	this.suggestionLabel = new OO.ui.LabelWidget( {
		label: this.suggestionData.text
	} );

	// Create an icon for confirmed suggestions.
	iconText = mw.message(
		'machinevision-suggestion-confirm-undo-title',
		this.suggestionData.text
	).text();
	this.checkIcon = new OO.ui.IconWidget( {
		icon: 'check',
		label: iconText,
		title: iconText
	} );

	this.$element.on( {
		click: this.toggleSuggestion.bind( this ),
		keypress: this.onKeypress.bind( this )
	} );

	// Ensure element is focusable.
	this.$element.attr( 'tabindex', 0 );

	this.render();
};

OO.inheritClass( SuggestionWidget, TemplateRenderingDOMLessGroupWidget );

SuggestionWidget.prototype.render = function () {
	this.renderTemplate( 'resources/widgets/SuggestionWidget.mustache+dom', {
		suggestionLabel: this.suggestionLabel,
		confirmed: this.confirmed,
		checkIcon: this.checkIcon
	} );
};

SuggestionWidget.prototype.toggleSuggestion = function () {
	var event = ( this.confirmed ) ? 'unconfirmSuggestion' : 'confirmSuggestion';
	this.emit( event );
};

SuggestionWidget.prototype.onKeypress = function ( e ) {
	if ( e.keyCode === 13 ) {
		this.toggleSuggestion();
	}
};

module.exports = SuggestionWidget;
