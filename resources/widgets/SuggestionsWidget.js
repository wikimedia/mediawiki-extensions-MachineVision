'use strict';

/**
 * CheckboxMultiselectWidget styled as lozenges in a flex container.
 * @param {Object} config
 * @cfg {Array} suggestions Suggestion data
 */
function SuggestionsWidget( config ) {
	var option,
		options,
		parentConfig;

	this.config = config || {};

	options = this.config.suggestions.map( function ( suggestion ) {
		option = new OO.ui.CheckboxMultioptionWidget( {
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
	} );

	parentConfig = {
		items: options,
		classes: [ 'wbmad-suggestions' ]
	};

	SuggestionsWidget.parent.call( this, $.extend( {}, parentConfig ) );
	this.connect( this, { select: 'onSelect' } );
}
OO.inheritClass( SuggestionsWidget, OO.ui.CheckboxMultiselectWidget );

/**
 * Handle user action of toggling a suggestion.
 * @param {OO.ui.CheckboxMultioptionWidget} suggestion
 */
SuggestionsWidget.prototype.onSelect = function ( suggestion ) {
	// Adding this class only after a suggestion has been changed once, that is,
	// the first time it is selected, will allow us to animate the process of
	// un-selecting a suggestion (sliding it back to center) without firing off
	// that animation when the component first mounts.
	suggestion.$element.addClass( 'wbmad-suggestions__suggestion--toggled' );
	this.emit( 'change' );
};

module.exports = SuggestionsWidget;
