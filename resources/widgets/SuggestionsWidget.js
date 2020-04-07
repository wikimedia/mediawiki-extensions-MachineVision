'use strict';

/**
 * CheckboxMultiselectWidget styled as lozenges in a flex container.
 * @param {Object} config
 * @cfg {Array} suggestions Suggestion data
 */
function SuggestionsWidget( config ) {
	var self = this,
		options,
		parentConfig;

	this.config = config || {};

	options = this.config.suggestions.map( function ( suggestion ) {
		return self.createOption( suggestion );
	} );

	parentConfig = {
		items: options,
		classes: [ 'wbmad-suggestions' ]
	};

	SuggestionsWidget.parent.call( this, $.extend( {}, parentConfig ) );
	this.connect( this, { select: [ 'emit', 'change' ] } );
}
OO.inheritClass( SuggestionsWidget, OO.ui.CheckboxMultiselectWidget );

/**
 * Create a new option widget.
 * @param {Object} suggestion
 * @return {OO.ui.CheckboxMultioptionWidget}
 */
SuggestionsWidget.prototype.createOption = function ( suggestion ) {
	var option = new OO.ui.CheckboxMultioptionWidget( {
		data: suggestion.wikidataId,
		label: suggestion.text,
		classes: [ 'wbmad-suggestions__suggestion' ]
	} );

	// Swap out classes so we can style things ourselves.
	option.checkbox.$element
		.removeClass( 'oo-ui-checkboxInputWidget' )
		.addClass( 'wbmad-suggestions__suggestion__checkbox-widget' );
	option.checkbox.checkIcon.$element
		.removeClass( 'oo-ui-checkboxInputWidget-checkIcon oo-ui-image-invert' )
		.addClass( 'wbmad-suggestions__suggestion__check-icon' );

	return option;
};

module.exports = SuggestionsWidget;
