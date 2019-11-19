'use strict';

var ConfirmTagsDialog,
	ConfirmTagsDialogContent = require( './ConfirmTagsDialogContent.js' );

/**
 * Process dialog for users to confirm tags before they're published.
 *
 * When a user clicks "Publish", this dialog will appear prompting them to
 * confirm tags. When the user clicks "OK", the tags will be saved and the user
 * will be taken to the next image.
 *
 * @constructor
 * @param {Object} [config]
 * @cfg {string} [tagsList] A comma-delimited list of tags to be confirmed.
 * @cfg {string} [imgUrl]
 * @cfg {string} [imgTitle]
 */
ConfirmTagsDialog = function ( config ) {
	this.config = config || {};
	ConfirmTagsDialog.parent.call( this, config );
	this.$element.addClass( 'wbmad-confirm-tags-dialog' );
};
OO.inheritClass( ConfirmTagsDialog, OO.ui.ProcessDialog );

/**
 * @inheritdoc
 * @property name
 */
ConfirmTagsDialog.static.name = 'confirmTagsDialog';

/**
 * @inheritdoc
 * @property title
 */
ConfirmTagsDialog.static.title = mw.message( 'machinevision-confirm-tags-dialog-title' ).parse();

/**
 * @inheritdoc
 * @property actions
 */
ConfirmTagsDialog.static.actions = [
	{
		action: 'confirm',
		label: mw.message( 'machinevision-confirm-tags-dialog-confirm-action' ).parse(),
		flags: [ 'primary', 'progressive' ]
	},
	{
		label: mw.message( 'machinevision-confirm-tags-dialog-cancel-action' ).parse(),
		flags: [ 'safe', 'close' ]
	}
];

/**
 * @inheritdoc
 */
ConfirmTagsDialog.prototype.initialize = function () {
	var dialogContent = new ConfirmTagsDialogContent( this.config );
	ConfirmTagsDialog.parent.prototype.initialize.call( this );

	// Add content to the dialog.
	this.$body.append( dialogContent.$element );
};

/**
 * @inheritdoc
 */
ConfirmTagsDialog.prototype.getActionProcess = function ( action ) {
	var dialog = this;
	if ( action === 'confirm' ) {
		// Emit a "confirm" event to the parent, then close the dialog.
		return new OO.ui.Process( function () {
			this.emit( 'confirm' );
			dialog.close( { action: action } );
		}, this );
	}

	// Fallback to parent handler.
	return ConfirmTagsDialog.parent.prototype.getActionProcess.call( this, action );
};

module.exports = ConfirmTagsDialog;
