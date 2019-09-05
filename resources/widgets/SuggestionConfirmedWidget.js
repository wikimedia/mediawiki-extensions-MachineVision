'use strict';

var SuggestionBaseWidget = require( './SuggestionBaseWidget.js' ),
	SuggestionConfirmedWidget;

SuggestionConfirmedWidget = function ( config ) {
	SuggestionConfirmedWidget.parent.call( this, $.extend( {}, config ) );
	this.$element.addClass( 'wbmad-confirmed-suggestion' );

	this.suggestionLabel = new OO.ui.LabelWidget( {
		label: this.suggestionData.text
	} );

	this.checkButton = new OO.ui.ButtonWidget( {
		title: mw.message( 'machinevision-suggestion-confirm-undo-title', this.suggestionData.text ).text(),
		icon: 'check',
		framed: false
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
		checkButton: this.checkButton
	} );
};

module.exports = SuggestionConfirmedWidget;
