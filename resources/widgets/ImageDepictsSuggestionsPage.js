'use strict';

var TemplateRenderingDOMLessGroupWidget = require( './../base/TemplateRenderingDOMLessGroupWidget.js' ),
ImageWithSuggestionsWidget = require( './ImageWithSuggestionsWidget.js' ),
ImageDepictsSuggestionsPage = function WikibaseMachineAssistedDepictsImageDepictsSuggestionsPage( config ) {
	ImageDepictsSuggestionsPage.parent.call( this, $.extend( {}, config ) );
	this.$element.addClass('wbmad-image-depicts-suggestions-page');
	this.addItems($.map( config.imageDataArray, getImageWithSuggestionsWidgetForImageData ));
	this.render();
};
OO.inheritClass( ImageDepictsSuggestionsPage, TemplateRenderingDOMLessGroupWidget );

var getImageWithSuggestionsWidgetForImageData = function ( imageData ) {
	return new ImageWithSuggestionsWidget( {
		imageData: imageData
	} );
};

ImageDepictsSuggestionsPage.prototype.render = function () {
	this.renderTemplate(
		'resources/widgets/ImageDepictsSuggestionsPage.mustache+dom',
		{
			imageWithSuggestionsWidgets: this.items
		}
	);
};

module.exports = ImageDepictsSuggestionsPage;
