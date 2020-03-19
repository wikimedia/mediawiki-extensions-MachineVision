'use strict';

var TemplateRenderingDOMLessGroupWidget = require( '../base/TemplateRenderingDOMLessGroupWidget.js' );

/**
 * UI skeleton for cardstack for use during API calls.
 */
function CardstackPlaceholder() {
	CardstackPlaceholder.parent.call( this );
	this.$element.addClass( 'wbmad-cardstack-placeholder' );
	this.render();
}
OO.inheritClass( CardstackPlaceholder, TemplateRenderingDOMLessGroupWidget );

CardstackPlaceholder.prototype.render = function () {
	this.renderTemplate( 'widgets/CardstackPlaceholder.mustache+dom', {} );
};

module.exports = CardstackPlaceholder;
