'use strict';

var TemplateRenderingDOMLessGroupWidget = require( './../base/TemplateRenderingDOMLessGroupWidget.js' ),
	SuggestionWidget;

/**
 * A single suggested tag for an image.
 *
 * @param {Object} config
 * @cfg {Object} suggestionData
 */
SuggestionWidget = function ( config ) {
	var iconText;

	this.suggestionData = config.suggestionData;
	this.confirmed = this.suggestionData.confirmed;

	SuggestionWidget.parent.call( this, $.extend( {}, config ) );
	this.$element.addClass( 'wbmad-suggestion-wrapper' );

	this.suggestionLabel = new OO.ui.LabelWidget( {
		label: this.suggestionData.text
	} );

	// Create an icon for a confirmed suggestion.
	iconText = mw.message(
		'machinevision-suggestion-confirm-undo-title',
		this.suggestionData.text
	).parse();
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
	var className = ( this.confirmed ) ? 'wbmad-suggestion--confirmed' : 'wbmad-suggestion--unconfirmed';

	this.renderTemplate( 'resources/widgets/SuggestionWidget.mustache+dom', {
		className: className,
		suggestionLabel: this.suggestionLabel,
		confirmed: this.confirmed,
		checkIcon: this.checkIcon
	} );
};

/**
 * Handle click/enter on suggestion widget.
 *
 * Store confirmed status in local "state", tell parent widget about this
 * change, then re-render the suggestion widget.
 */
SuggestionWidget.prototype.toggleSuggestion = function () {
	this.confirmed = !this.confirmed;
	this.emit( 'toggleSuggestion', this.confirmed );
	this.render();
};

/**
 * Toggle the suggestion on enter keypress.
 * @param {Object} e
 */
SuggestionWidget.prototype.onKeypress = function ( e ) {
	if ( e.keyCode === 13 ) {
		this.toggleSuggestion();
	}
};

module.exports = SuggestionWidget;
