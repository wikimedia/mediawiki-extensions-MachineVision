'use strict';

var TemplateRenderingDOMLessGroupWidget = require( '../base/TemplateRenderingDOMLessGroupWidget.js' );

/**
 * List of file categories.
 */
function CategoryList() {
	CategoryList.parent.call( this );

	this.categories = [];
	this.render();
}
OO.inheritClass( CategoryList, TemplateRenderingDOMLessGroupWidget );

CategoryList.prototype.render = function () {
	this.renderTemplate( 'widgets/CategoryList.mustache+dom', {
		hasCategories: this.categories.length > 0,
		categoriesLabel: mw.message( 'machinevision-categories-label' ).parse(),
		categories: this.categories
	} );
};

module.exports = CategoryList;
