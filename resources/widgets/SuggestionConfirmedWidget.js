'use strict';

var SuggestionBaseWidget = require( './SuggestionBaseWidget.js' ),
	SuggestionConfirmedWidget;

/**
 * TODO: Document this
 * @param {Object} config
 */
SuggestionConfirmedWidget = function ( config ) {
	var iconText;

	SuggestionConfirmedWidget.parent.call( this, $.extend( {}, config ) );
	this.$element.addClass( 'wbmad-confirmed-suggestion' );

	this.suggestionLabel = new OO.ui.LabelWidget( {
		label: this.suggestionData.text
	} );

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
		click: this.emitUnconfirmSuggestion.bind( this )
	} );

	this.render();
};

OO.inheritClass( SuggestionConfirmedWidget, SuggestionBaseWidget );

SuggestionConfirmedWidget.prototype.render = function () {
	this.renderTemplate( 'resources/widgets/SuggestionConfirmedWidget.mustache+dom', {
		suggestionLabel: this.suggestionLabel,
		checkIcon: this.checkIcon
	} );
};

module.exports = SuggestionConfirmedWidget;
