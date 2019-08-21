'use strict';

var TemplateRenderingDOMLessGroupWidget = require( './../base/TemplateRenderingDOMLessGroupWidget.js' ),
SuggestionRejectedWidget = require( './SuggestionRejectedWidget.js' ),
SuggestionsRejectedGroupWidget = function WikibaseMachineAssistedDepictsSuggestionsRejectedGroupWidget( config ) {
	SuggestionsRejectedGroupWidget.parent.call( this, $.extend( {}, config ) );
	this.$element.addClass('wbmad-rejected-suggestion-group');
	this.suggestionDataArray = config.suggestionDataArray;
	this.aggregate( {
		unrejectSuggestion: 'unrejectSuggestion'
	} );
	this.titleLabel = new OO.ui.LabelWidget( {
		label: config.label,
		classes: [ 'wbmad-suggestion-group-title-label' ]
	} );
	this.render();
};
OO.inheritClass( SuggestionsRejectedGroupWidget, TemplateRenderingDOMLessGroupWidget );

var getSuggestionWidgetForSuggestionData = function ( suggestionData ) {
	return new SuggestionRejectedWidget( {
		suggestionData: suggestionData
	} );
};

SuggestionsRejectedGroupWidget.prototype.render = function () {
	this.clearItems()
		.addItems( $.map( this.suggestionDataArray, getSuggestionWidgetForSuggestionData ) );

	this.$element.css( 'display', this.suggestionDataArray.length > 0 ? 'block' : 'none' );
	this.renderTemplate(
		'resources/widgets/SuggestionsRejectedGroupWidget.mustache+dom',
		{
			titleLabel: this.titleLabel,
			suggestions: this.items
		}
	);
};

module.exports = SuggestionsRejectedGroupWidget;
