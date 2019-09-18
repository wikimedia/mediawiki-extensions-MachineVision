'use strict';

var TemplateRenderingDOMLessGroupWidget = require( './../base/TemplateRenderingDOMLessGroupWidget.js' ),
	SuggestionBaseWidget;

/**
 * @param {Object} config
 * @param {Object} config.suggestionData
 */
SuggestionBaseWidget = function ( config ) {
	config.classes = [ 'wbmad-base-suggestion' ];
	SuggestionBaseWidget.parent.call( this, $.extend( {}, config ) );
	this.suggestionData = config.suggestionData;
};

OO.inheritClass( SuggestionBaseWidget, TemplateRenderingDOMLessGroupWidget );

SuggestionBaseWidget.prototype.emitConfirmSuggestion = function () {
	this.emit( 'confirmSuggestion' );
};

SuggestionBaseWidget.prototype.emitUnconfirmSuggestion = function () {
	this.emit( 'unconfirmSuggestion' );
};

module.exports = SuggestionBaseWidget;
