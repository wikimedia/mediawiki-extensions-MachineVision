'use strict';

var TemplateRenderingDOMLessGroupWidget = require( './../base/TemplateRenderingDOMLessGroupWidget.js' ),
	ImageWithSuggestionsWidget = require( './ImageWithSuggestionsWidget.js' ),
	ImageDepictsSuggestionsPage;

/**
 * @param {Object} config
 * @param {Array} config.imageDataArray
 */
ImageDepictsSuggestionsPage = function ( config ) {
	var self = this;

	ImageDepictsSuggestionsPage.parent.call( this, $.extend( {}, config ) );
	this.$element.addClass( 'wbmad-image-depicts-suggestions-page' );

	this.addItems( config.imageDataArray.map( function ( imageData ) {
		return self.createSuggestionWidget( imageData );
	} ) );

	this.render();
};

OO.inheritClass( ImageDepictsSuggestionsPage, TemplateRenderingDOMLessGroupWidget );

ImageDepictsSuggestionsPage.prototype.render = function () {
	this.renderTemplate( 'resources/widgets/ImageDepictsSuggestionsPage.mustache+dom', {
		imageWithSuggestionsWidgets: this.items
	} );
};

ImageDepictsSuggestionsPage.prototype.createSuggestionWidget = function ( imageData ) {
	return new ImageWithSuggestionsWidget( {
		imageData: imageData
	} );
};

module.exports = ImageDepictsSuggestionsPage;
