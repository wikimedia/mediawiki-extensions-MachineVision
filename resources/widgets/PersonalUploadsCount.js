'use strict';

var TemplateRenderingDOMLessGroupWidget = require( '../base/TemplateRenderingDOMLessGroupWidget.js' ),
	PersonalUploadsCount;

/**
 * Text informing the user how many personal uploads they have for review.
 *
 * @param {Object} config
 * @cfg {number} userImageCount
 */
PersonalUploadsCount = function ( config ) {
	this.config = config || {};
	PersonalUploadsCount.parent.call( this, $.extend( {}, config ) );
	this.$element.addClass( 'wbmad-personal-uploads-count' );

	this.userImageCount = this.config.userImageCount;
	this.render();
};

OO.inheritClass(
	PersonalUploadsCount,
	TemplateRenderingDOMLessGroupWidget
);

PersonalUploadsCount.prototype.render = function () {
	this.renderTemplate( 'resources/widgets/PersonalUploadsCount.mustache+dom', {
		countString: mw.message( 'machinevision-personal-uploads-count', this.userImageCount ).text()
	} );
};

module.exports = PersonalUploadsCount;
