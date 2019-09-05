'use strict';

// TODO: remove jQuery global selectors
/* eslint-disable no-jquery/no-global-selector */

var IMAGES_PER_PAGE = 10,
	TemplateRenderingDOMLessGroupWidget = require( './../base/TemplateRenderingDOMLessGroupWidget.js' ),
	ImageDepictsSuggestionsPage = require( './ImageDepictsSuggestionsPage.js' ),
	SuggestionData = require( './../models/SuggestionData.js' ),
	ImageData = require( './../models/ImageData.js' ),
	ImageDepictsSuggestionsPager,
	randomDescription,
	showFailureMessage,
	queryURLWithCountAndOffset,
	getImageDataForQueryResponsePage,
	updateMoreButtonVisibility;

ImageDepictsSuggestionsPager = function ( config ) {
	ImageDepictsSuggestionsPager.parent.call( this, $.extend( {}, config ) );

	this.$element.addClass( 'wbmad-image-depicts-suggestions-pager' );

	this.descriptionLabel = new OO.ui.LabelWidget( {
		label: mw.message( 'machinevision-machineaidedtagging-intro' ).text()
	} );

	this.moreButton = new OO.ui.ButtonWidget( {
		classes: [ 'wbmad-more-button' ],
		title: mw.message( 'machinevision-more-title', IMAGES_PER_PAGE ).text(),
		label: mw.message( 'machinevision-more', IMAGES_PER_PAGE ).text()
	} ).on( 'click', this.onMore, [], this );

	this.render();
	// $(window).scroll(this.fetchAndShowPageIfScrolledToBottom.bind(this));
	this.fetchAndShowPage();
};

OO.inheritClass(
	ImageDepictsSuggestionsPager,
	TemplateRenderingDOMLessGroupWidget
);

ImageDepictsSuggestionsPager.prototype.onMore = function () {
	this.fetchAndShowPage();
};

ImageDepictsSuggestionsPager.prototype.render = function () {
	this.renderTemplate( 'resources/widgets/ImageDepictsSuggestionsPager.mustache+dom', {
		descriptionLabel: this.descriptionLabel,
		moreButton: this.moreButton
	} );
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

queryURLWithCountAndOffset = function ( count, offset ) {
	var query, urlString;

	query = {
		action: 'query',
		format: 'json',
		formatversion: 2,
		generator: 'unreviewedimagelabels',
		guillimit: count,
		prop: 'imageinfo|imagelabels',
		iiprop: 'url',
		iiurlwidth: 320,
		ilstate: 'unreviewed'
	};

	if ( offset ) {
		query.gqpoffset = count * offset;
		query.continue = 'gqpoffset||';
	}

	urlString = mw.config.get( 'wgServer' ) +
		mw.config.get( 'wgScriptPath' ) + '/api.php?';

	urlString += Object.keys( query ).map( function ( key ) {
		return key + '=' + query[ key ];
	} ).join( '&' );

	return urlString;
};

randomDescription = function () {
	var array, randomNumber;

	array = [
		'This is a thing. This is a random description.',
		'This is another such thing. This is a random description.',
		'This is some other stuff. This is a random description.',
		'This is a dodad. This is a random description.'
	];

	randomNumber = Math.floor( Math.random() * array.length );

	return array[ randomNumber ];
};

getImageDataForQueryResponsePage = function ( page ) {
	// TODO: grab actual description and suggestions from middleware endpoint
	// once it exists, then delete the random methods and the
	// `thumbwidth != // 320` check (which the middleware will enforce)
	if ( page.imageinfo && page.imagelabels && page.imagelabels.length ) {
		return new ImageData(
			page.title,
			page.imageinfo[ 0 ].thumburl,
			randomDescription(),
			page.imagelabels.map( function ( labelData ) {
				return new SuggestionData( labelData.label );
			} )
		);
	}
};

updateMoreButtonVisibility = function ( resultsFound ) {
	$( '.wbmad-more-button' ).css( 'display', resultsFound ? 'block' : 'none' );
};

ImageDepictsSuggestionsPager.prototype.showPageForQueryResponse = function ( response ) {
	var resultsFound;

	$( '#wbmad-image-depicts-suggestions-pages' ).append(
		new ImageDepictsSuggestionsPage( {
			imageDataArray: response.query.pages.map( function ( page ) {
				return getImageDataForQueryResponsePage( page );
			} )
		} ).$element
	);

	resultsFound = ( response.query.pages && response.query.pages.length > 0 );
	updateMoreButtonVisibility( resultsFound );
};

ImageDepictsSuggestionsPager.prototype.fetchAndShowPage = function () {
	$.getJSON( queryURLWithCountAndOffset( IMAGES_PER_PAGE, $( '.wbmad-image-depicts-suggestions-page' ).length ) )
		.done( this.showPageForQueryResponse.bind( this ) )
		.fail( showFailureMessage );
};

showFailureMessage = function () {
	// FIXME
	// $( '#content' ).append( '<p>Oh no, something went wrong!</p>' );
};

module.exports = ImageDepictsSuggestionsPager;
