'use strict';

var TemplateRenderingDOMLessGroupWidget = require( './../base/TemplateRenderingDOMLessGroupWidget.js' ),
SuggestionWidget = require( './SuggestionWidget.js' ),
SuggestionConfirmedWidget = require( './SuggestionConfirmedWidget.js' ),
SuggestionsGroupWidget = function WikibaseMachineAssistedDepictsSuggestionsGroupWidget( config ) {
	SuggestionsGroupWidget.parent.call( this, $.extend( {}, config ) );
	this.$element.addClass('wbmad-suggestion-group');

	this.suggestionDataArray = config.suggestionDataArray;
	this.confirmedSuggestionDataArray = config.confirmedSuggestionDataArray;
	this.rejectedSuggestionDataArray = config.rejectedSuggestionDataArray;

	this.aggregate( {
		confirmSuggestion: 'confirmSuggestion',
		unconfirmSuggestion: 'unconfirmSuggestion',
		rejectSuggestion: 'rejectSuggestion'
	} );
	this.titleLabel = new OO.ui.LabelWidget( {
		label: config.label,
		classes: [ 'wbmad-suggestion-group-title-label' ]
	} );
	this.render();
};
OO.inheritClass( SuggestionsGroupWidget, TemplateRenderingDOMLessGroupWidget );

SuggestionsGroupWidget.prototype.getSuggestionWidgetForSuggestionData = function (suggestionData) {
	if ( $.inArray( suggestionData, this.confirmedSuggestionDataArray ) > -1 ) {
		return new SuggestionConfirmedWidget( {
			suggestionData: suggestionData
		} );
	}
	if ( $.inArray( suggestionData, this.rejectedSuggestionDataArray ) > -1 ) {
		return null;
	}
	return new SuggestionWidget( {
		suggestionData: suggestionData
	} );
};

SuggestionsGroupWidget.prototype.render = function () {
	this.clearItems()
		.addItems( $.map( this.suggestionDataArray, this.getSuggestionWidgetForSuggestionData.bind( this ) ) );

	this.renderTemplate(
		'resources/widgets/SuggestionsGroupWidget.mustache+dom',
		{
			titleLabel: this.titleLabel,
			suggestions: this.items
		}
	);
};

module.exports = SuggestionsGroupWidget;
