'use strict';

var SuggestionBaseWidget = require( './SuggestionBaseWidget.js' ),
	SuggestionWidget;

/**
 * TODO: Document this
 * @param {Object} config
 */
SuggestionWidget = function ( config ) {
	SuggestionWidget.parent.call( this, $.extend( {}, config ) );
	this.$element.addClass( 'wbmad-suggestion' );

	this.suggestionLabel = new OO.ui.LabelWidget( {
		label: this.suggestionData.text
	} );

	this.$element.on( {
		click: this.emitConfirmSuggestion.bind( this )
	} );

	this.render();
};

OO.inheritClass( SuggestionWidget, SuggestionBaseWidget );

SuggestionWidget.prototype.render = function () {
	this.renderTemplate( 'resources/widgets/SuggestionWidget.mustache+dom', {
		suggestionLabel: this.suggestionLabel
	} );
};

module.exports = SuggestionWidget;
