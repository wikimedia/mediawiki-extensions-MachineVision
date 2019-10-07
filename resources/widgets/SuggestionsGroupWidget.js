'use strict';

// TODO: Replace jQuery array methods with real ones
/* eslint-disable no-jquery/no-in-array */
/* eslint-disable no-jquery/no-map-util */

var TemplateRenderingDOMLessGroupWidget = require( './../base/TemplateRenderingDOMLessGroupWidget.js' ),
	SuggestionWidget = require( './SuggestionWidget.js' ),
	SuggestionsGroupWidget;

/**
 * TODO: Document this
 * @param {Object} config
 * @param {Array} config.suggestionDataArray
 * @param {Array} config.confirmedSuggestionDataArray
 */
SuggestionsGroupWidget = function ( config ) {
	SuggestionsGroupWidget.parent.call( this, $.extend( {}, config ) );
	this.$element.addClass( 'wbmad-suggestion-group' );

	this.suggestionDataArray = config.suggestionDataArray;
	this.confirmedSuggestionDataArray = config.confirmedSuggestionDataArray;

	this.aggregate( {
		confirmSuggestion: 'confirmSuggestion',
		unconfirmSuggestion: 'unconfirmSuggestion'
	} );

	this.titleLabel = new OO.ui.LabelWidget( {
		label: config.label,
		classes: [ 'wbmad-suggestion-group-title-label' ]
	} );

	this.render();
};

OO.inheritClass( SuggestionsGroupWidget, TemplateRenderingDOMLessGroupWidget );

/**
 * TODO: Document this
 * @param {Object} data
 * @return {SuggestionWidget}
 */
SuggestionsGroupWidget.prototype.getSuggestionWidgetForSuggestionData = function ( data ) {
	var confirmed = !!( $.inArray( data, this.confirmedSuggestionDataArray ) > -1 );

	return new SuggestionWidget( {
		suggestionData: data,
		confirmed: confirmed
	} );
};

SuggestionsGroupWidget.prototype.render = function () {
	this.clearItems()
		.addItems( $.map(
			this.suggestionDataArray,
			this.getSuggestionWidgetForSuggestionData.bind( this )
		) );

	this.renderTemplate( 'resources/widgets/SuggestionsGroupWidget.mustache+dom', {
		titleLabel: this.titleLabel,
		suggestions: this.items
	} );
};

module.exports = SuggestionsGroupWidget;
