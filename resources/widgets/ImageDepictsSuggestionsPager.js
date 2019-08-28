'use strict';

var IMAGES_PER_PAGE = 10,
TemplateRenderingDOMLessGroupWidget = require( './../base/TemplateRenderingDOMLessGroupWidget.js' ),
ImageDepictsSuggestionsPage = require( './ImageDepictsSuggestionsPage.js' ),
SuggestionData = require( './../models/SuggestionData.js' ),
ImageData = require( './../models/ImageData.js' ),
ImageDepictsSuggestionsPager = function WikibaseMachineAssistedDepictsImageDepictsSuggestionsPager( config ) {
	ImageDepictsSuggestionsPager.parent.call( this, $.extend( {}, config ) );
	this.$element.addClass('wbmad-image-depicts-suggestions-pager');
	this.descriptionLabel = new OO.ui.LabelWidget( {
		label: mw.message( 'machinevision-machineaidedtagging-intro' ).text()
	} );
	this.moreButton = new OO.ui.ButtonWidget( {
		classes: ['wbmad-more-button'],
		title: mw.message( 'machinevision-more-title', IMAGES_PER_PAGE ).text(),
		label: mw.message( 'machinevision-more', IMAGES_PER_PAGE ).text()
	} )
	.on('click', this.onMore, [], this );

	this.render();
	// $(window).scroll(this.fetchAndShowPageIfScrolledToBottom.bind(this));
	this.fetchAndShowPage();
};
OO.inheritClass( ImageDepictsSuggestionsPager, TemplateRenderingDOMLessGroupWidget );

ImageDepictsSuggestionsPager.prototype.onMore = function () {
	this.fetchAndShowPage();
};

ImageDepictsSuggestionsPager.prototype.render = function () {
	this.renderTemplate(
		'resources/widgets/ImageDepictsSuggestionsPager.mustache+dom',
		{
			descriptionLabel: this.descriptionLabel,
			moreButton: this.moreButton
		}
	);
};

/*
var isScrolledToBottom = function() {
	return ( $( window ).scrollTop() + $( window ).height() == $( document ).height() );
}

ImageDepictsSuggestionsPager.prototype.fetchAndShowPageIfScrolledToBottom = function () {
	if ( !isScrolledToBottom() ) {
		return;
	}
	this.fetchAndShowPage();
}
*/

var queryURLWithCountAndOffset = function( count, offset ) {
	var query = {
		action: 'query',
		format: 'json',
		formatversion: 2,
		generator: 'unreviewedimagelabels',
		guillimit: count,
		prop: 'imageinfo|imagelabels',
		iiprop: 'url',
		iiurlwidth: 320,
	};

	if ( offset ) {
		query['gqpoffset'] = count * offset;
		query['continue'] = 'gqpoffset||';
	}

	return mw.config.get( 'wgServer' )
		+ mw.config.get ( 'wgScriptPath' ) + '/api.php?'
		+ Object.keys( query ).map( k => `${k}=${query[k]}` ).join('&');
};

var randomDescription = function() {
	var array = [
		'This is a thing. This is a random description.',
		'This is another such thing. This is a random description.',
		'This is some other stuff. This is a random description.',
		'This is a dodad. This is a random description.'
	];
	var randomNumber = Math.floor( Math.random() * array.length );
	return array[randomNumber];
};

var getImageDataForQueryResponsePage = function( page ) {
	// TODO: grab actual description and suggestions from middleware endpoint once it exists,
	// then delete the random methods and the `thumbwidth != 320` check (which the middleware will enforce)
	if ( page.imageinfo && page.imagelabels && page.imagelabels.length ) {
		return new ImageData(
			page.title,
			page.imageinfo[0].thumburl,
			randomDescription(),
			page.imagelabels.map( labelData => new SuggestionData( labelData.label ) )
		);
	}
};

var updateMoreButtonVisibility = function ( resultsFound ) {
	$( '.wbmad-more-button' ).css( 'display', resultsFound ? 'block' : 'none' );
}

ImageDepictsSuggestionsPager.prototype.showPageForQueryResponse = function( response ) {
	$( '#wbmad-image-depicts-suggestions-pages' ).append(
			new ImageDepictsSuggestionsPage({
				imageDataArray: $.map( response.query.pages, getImageDataForQueryResponsePage )
			}).$element
	);
	var resultsFound = ( response.query.pages && response.query.pages.length > 0 );
	updateMoreButtonVisibility( resultsFound );
};

ImageDepictsSuggestionsPager.prototype.fetchAndShowPage = function () {
	$.getJSON( queryURLWithCountAndOffset(IMAGES_PER_PAGE, $( '.wbmad-image-depicts-suggestions-page' ).length) )
		.done( this.showPageForQueryResponse.bind(this) )
		.fail( showFailureMessage );
};

var showFailureMessage = function() {
	// FIXME
	// $( '#content' ).append( '<p>Oh no, something went wrong!</p>' );
};

module.exports = ImageDepictsSuggestionsPager;
