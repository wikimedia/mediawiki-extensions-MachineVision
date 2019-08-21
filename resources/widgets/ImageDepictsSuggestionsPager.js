'use strict';

var IMAGES_PER_PAGE = 25,
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
	// TODO: switch to middleware endpoint once it exists
	var url = "https://en.wikipedia.org/w/api.php?action=query&format=json&prop=imageinfo&generator=querypage&formatversion=2&iiprop=url&iiurlwidth=320&iiurlparam=&gqppage=Uncategorizedimages&origin=*"
		+ "&gqplimit=" + count;
	if (offset == 0) {
		return url;
	}
	return url
		+ "&gqpoffset=" + (count * offset)
		+ "&continue=gqpoffset||";
}

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

var randomSuggestions = function() {
	var array = 'this,that,other,bird,dog,cat,fish,horse,goat,frog,soup,chicken,stick,sky,mountain,house,beach,face,eye,book,shirt,watch,hair,arm,bricks,map,hand,window,floor,lamp,river,can,logo,bottle,form,stamp,chart,graph,lines'.split(',');
	array = $.map( array, function( string ) {
		return new SuggestionData( string );
	});
	var shuffled = array.sort( function () {
		return .5 - Math.random();
	});
	return shuffled.slice( 0, Math.min( 15, Math.floor( Math.random() * array.length ) + 5 ) );
};

var getImageDataForQueryResponsePage = function( page ) {
	// TODO: grab actual description and suggestions from middleware endpoint once it exists,
	// then delete the random methods and the `thumbwidth != 320` check (which the middleware will enforce)
	return ( !page.imageinfo || page.imageinfo[0].thumbwidth != 320 ) ? null : new ImageData( page.title, page.imageinfo[0].thumburl, randomDescription(), randomSuggestions() );
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
