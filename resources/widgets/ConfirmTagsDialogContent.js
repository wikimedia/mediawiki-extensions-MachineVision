'use strict';

var TemplateRenderingDOMLessGroupWidget = require( '../base/TemplateRenderingDOMLessGroupWidget.js' );

/**
 * Content within the "Confirm tags" dialog.
 *
 * @param {Object} config
 * @cfg {string} [tagsList] A comma-delimited list of tags to be confirmed.
 * @cfg {string} [imgUrl]
 * @cfg {string} [imgTitle]
 */
function ConfirmTagsDialogContent( config ) {
	this.config = config || {};
	ConfirmTagsDialogContent.parent.call( this, $.extend( {}, config ) );
	this.$element.addClass( 'wbmad-confirm-tags-dialog-content' );

	this.render();
}
OO.inheritClass( ConfirmTagsDialogContent, TemplateRenderingDOMLessGroupWidget );

ConfirmTagsDialogContent.prototype.render = function () {
	this.renderTemplate( 'widgets/ConfirmTagsDialogContent.mustache+dom', {
		heading: mw.message( 'machinevision-confirm-tags-dialog-heading' ).parse(),
		tagsList: this.config.tagsList,
		imgUrl: this.config.imgUrl,
		imgTitle: this.config.imgTitle
	} );
};

module.exports = ConfirmTagsDialogContent;
