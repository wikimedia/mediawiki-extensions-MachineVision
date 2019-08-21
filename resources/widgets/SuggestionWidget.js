'use strict';

var SuggestionBaseWidget = require( './SuggestionBaseWidget.js' ),
SuggestionWidget = function WikibaseMachineAssistedDepictsSuggestionWidget( config ) {
	SuggestionWidget.parent.call( this, $.extend( {}, config ) );
	this.$element.addClass('wbmad-suggestion');

	this.suggestionLabel = new OO.ui.LabelWidget( {
		label: this.suggestionData.text
	} );

	this.closeButton = new OO.ui.ButtonWidget( {
		title: mw.message( 'machinevision-suggestion-reject-title', this.suggestionData.text ).text(),
		icon: 'close',
		framed: false
	}).on( 'click', this.emitRejectSuggestion, null, this);

	this.$element.on( {
		click: this.emitConfirmSuggestion.bind( this )
	} );

	this.render();
};
OO.inheritClass( SuggestionWidget, SuggestionBaseWidget );

SuggestionWidget.prototype.render = function () {
	this.renderTemplate(
		'resources/widgets/SuggestionWidget.mustache+dom',
		{
			suggestionLabel: this.suggestionLabel,
			closeButton: this.closeButton
		}
	);
};

module.exports = SuggestionWidget;
