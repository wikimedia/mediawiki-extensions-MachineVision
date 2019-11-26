var pathToWidget = '../../../resources/widgets/SuggestedTagsCardstack.js',
	hooks = require( '../support/hooks.js' ),
	imageDataArray = [
		{
			descriptionUrl: 'https://example.com/File:Cat.jpg',
			suggestions: [
				{ text: 'cat' },
				{ text: 'domestic shorthair' },
				{ text: 'whiskers' }
			],
			thumbUrl: 'https://example.com/thumbnails/Cat.jpg',
			title: 'Domestic shorthair cat with whiskers'
		},
		{
			descriptionUrl: 'https://example.com/File:Dog.jpg',
			suggestions: [
				{ text: 'dog' },
				{ text: 'Shiba Inu' },
				{ text: 'doge' }
			],
			thumbUrl: 'https://example.com/thumbnails/Dog.jpg',
			title: 'Such dog'
		}
	];

QUnit.module( 'SuggestedTagsCardstack', hooks );

QUnit.test( 'Constructor test', function ( assert ) {
	var SuggestedTagsCardstack = require( pathToWidget ),
		config = {
			queryType: 'popular',
			imageDataArray: imageDataArray,
			userUnreviewedImageCount: 2,
			userTotalImageCount: 2
		},
		widget = new SuggestedTagsCardstack( config );
	assert.ok( true );
} );

QUnit.test( 'ImageWithSuggestionWidgets are created for each image', function ( assert ) {
	var SuggestedTagsCardstack = require( pathToWidget ),
		config = {
			queryType: 'popular',
			imageDataArray: imageDataArray,
			userUnreviewedImageCount: 2,
			userTotalImageCount: 2
		},
		widget = new SuggestedTagsCardstack( config ),
		ImageWithSuggestionsWidget = require( '../../../resources/widgets/ImageWithSuggestionsWidget.js' );

	assert.strictEqual( widget.items.length, 2 );
	assert.strictEqual( widget.items[ 0 ] instanceof ImageWithSuggestionsWidget, true );
} );

QUnit.test( 'Count string exists for user query', function ( assert ) {
	var SuggestedTagsCardstack = require( pathToWidget ),
		config = {
			queryType: 'user',
			imageDataArray: imageDataArray,
			userUnreviewedImageCount: 2,
			userTotalImageCount: 2
		},
		widget = new SuggestedTagsCardstack( config ),
		PersonalUploadsCount = require( '../../../resources/widgets/PersonalUploadsCount.js' );

	assert.strictEqual( widget.countString instanceof PersonalUploadsCount, true );
} );

QUnit.test( 'Count string is null for popular query', function ( assert ) {
	var SuggestedTagsCardstack = require( pathToWidget ),
		config = {
			queryType: 'popular',
			imageDataArray: imageDataArray,
			userUnreviewedImageCount: 2,
			userTotalImageCount: 2
		},
		widget = new SuggestedTagsCardstack( config );

	assert.strictEqual( widget.countString, null );
} );

QUnit.test( 'Personal uploads count decrements when tags are published for an image', function ( assert ) {
	var SuggestedTagsCardstack = require( pathToWidget ),
		config = {
			queryType: 'user',
			imageDataArray: imageDataArray,
			userUnreviewedImageCount: 2,
			userTotalImageCount: 2
		},
		widget = new SuggestedTagsCardstack( config ),
		done = assert.async();

	// Start off with 2 images.
	assert.strictEqual( widget.countString.unreviewed, 2 );

	// Publish tags for the first image.
	widget.items[ 0 ].onFinalConfirm()
		.then( function () {
			assert.strictEqual( widget.countString.unreviewed, 1 );

			// Publish tags for the second image.
			widget.items[ 1 ].onFinalConfirm()
				.then( function () {
					assert.strictEqual( widget.countString.unreviewed, 0 );
					done();
				} );
		} );

} );
