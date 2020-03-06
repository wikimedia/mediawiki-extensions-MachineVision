'use strict';

var EntityAutocompleteInputWidget = require( './EntityAutocompleteInputWidget.js' );

/**
 * Dialog for adding a custom tag via autocomplete input widget.
 *
 * @constructor
 */
function AddCustomTagDialog() {
	AddCustomTagDialog.parent.call( this, {} );
	this.wikidataIds = [];
	this.$element.addClass( 'wbmad-add-custom-tag-dialog' );
	this.connect( this, {
		lookupMenuChoose: 'onLookupMenuChoose',
		enter: 'onEnter'
	} );
}
OO.inheritClass( AddCustomTagDialog, OO.ui.ProcessDialog );

/**
 * @inheritdoc
 * @property name
 */
AddCustomTagDialog.static.name = 'AddCustomTagDialog';

/**
 * @inheritdoc
 * @property title
 */
AddCustomTagDialog.static.title = mw.message( 'machinevision-add-custom-tag-dialog-title' ).parse();

/**
 * @inheritdoc
 * @property actions
 */
AddCustomTagDialog.static.actions = [
	{
		action: 'addCustomTag',
		label: mw.message( 'machinevision-add-custom-tag-dialog-add-action' ).parse(),
		flags: [ 'primary', 'progressive' ],
		disabled: true
	},
	{
		label: mw.message( 'machinevision-add-custom-tag-dialog-cancel-action' ).parse(),
		flags: [ 'safe', 'close' ]
	}
];

/**
 * @inheritdoc
 */
AddCustomTagDialog.prototype.initialize = function () {
	var dialog = this;
	AddCustomTagDialog.parent.prototype.initialize.call( this );

	// Add content to the dialog.
	this.input = new EntityAutocompleteInputWidget( {
		placeholder: mw.message( 'wikibasemediainfo-statements-item-input-placeholder' ).text(),
		icon: 'search',
		$overlay: this.$overlay
	} ).connect( dialog, {
		lookupMenuChoose: 'onLookupMenuChoose',
		enter: 'onEnter'
	} );
	this.$body.append( this.input.$element );
};

/**
 * @inheritdoc
 */
AddCustomTagDialog.prototype.getActionProcess = function ( action ) {
	var dialog = this;
	if ( action === 'addCustomTag' ) {
		// Emit a "add" event to the parent, then close the dialog.
		return new OO.ui.Process( function () {
			this.emit( 'addCustomTag', this.input.getData() );
			this.input.setValue( '' );
			dialog.close( { action: 'addCustomTag' } );
		}, this );
	}

	// Fallback to parent handler.
	return AddCustomTagDialog.parent.prototype.getActionProcess.call( this, action );
};

/**
 * Enable the "Add" button when an item is selected.
 */
AddCustomTagDialog.prototype.onLookupMenuChoose = function () {
	this.getActions().get( { actions: 'addCustomTag' } )[ 0 ].setDisabled( false );
};

/**
 * Trigger add action (after enter keypress within input).
 * @param {Object} e
 */
AddCustomTagDialog.prototype.onEnter = function ( e ) {
	var addCustomTagAction = this.getActions().get( { actions: 'addCustomTag' } )[ 0 ];

	if ( e &&
		e.target.classList.contains( 'oo-ui-inputWidget-input' ) &&
		addCustomTagAction.isDisabled() === false ) {
		this.executeAction( 'addCustomTag' );
	}
};

/**
 * Update list of existing Wikidata IDs to be filtered out.
 * @param {Array} wikidataIds
 */
AddCustomTagDialog.prototype.setFilter = function ( wikidataIds ) {
	this.input.filter = { field: '!id', value: wikidataIds.join( '|' ) };
};

module.exports = AddCustomTagDialog;
