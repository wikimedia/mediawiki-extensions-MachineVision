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
		} ),
		self = this;

	// Swap out classes so we can style things ourselves.
	option.checkbox.$element
		.removeClass( 'oo-ui-checkboxInputWidget' )
		.addClass( 'wbmad-suggestions__suggestion__checkbox-widget' )
		.on( {
			focusin: self.onCheckboxFocusIn.bind( self, option ),
			focusout: self.onCheckboxFocusOut.bind( self, option )
		} );
	option.checkbox.checkIcon.$element
		.removeClass( 'oo-ui-checkboxInputWidget-checkIcon oo-ui-image-invert' )
		.addClass( 'wbmad-suggestions__suggestion__check-icon' );

	return option;
};

/**
 * Add class for option focus styles.
 * @param {OO.ui.CheckboxMultioptionWidget} option
 */
SuggestionsWidget.prototype.onCheckboxFocusIn = function ( option ) {
	option.$element.addClass( 'wbmad-suggestions__suggestion--focus' );
};

/**
 * Remove class for option focus styles.
 * @param {OO.ui.CheckboxMultioptionWidget} option
 */
SuggestionsWidget.prototype.onCheckboxFocusOut = function ( option ) {
	option.$element.removeClass( 'wbmad-suggestions__suggestion--focus' );
};

module.exports = SuggestionsWidget;
