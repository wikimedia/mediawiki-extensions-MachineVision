var pathToWidget = '../../../resources/widgets/ImageWithSuggestionsWidget.js',
	hooks = require( '../support/hooks.js' ),
	sinon = require( 'sinon' ),
	sandbox,
	suggestions = [
		{ text: 'cat', wikidataId: 'Q123' },
		{ text: 'domestic shorthair', wikidataId: 'Q456' },
		{ text: 'whiskers', wikidataId: 'Q789' }
	],
	imageData = {
		descriptionUrl: 'https://example.com/File:Cat.jpg',
		suggestions: suggestions,
		thumbUrl: 'https://example.com/thumbnails/Cat.jpg',
		title: 'Domestic shorthair cat with whiskers'
	};

QUnit.module( 'ImageWithSuggestionsWidget', hooks );

QUnit.test( 'Constructor test', function ( assert ) {
	var ImageWithSuggestionsWidget = require( pathToWidget ),
		widget = new ImageWithSuggestionsWidget( imageData );
	assert.ok( true );
} );

QUnit.test( 'Suggestions widget is created from data passed as config', function ( assert ) {
	var ImageWithSuggestionsWidget = require( pathToWidget ),
		widget = new ImageWithSuggestionsWidget( imageData );

	assert.strictEqual( widget.suggestionsWidget.items.length, 3 );
} );

QUnit.test( 'Suggestions with no label are skipped', function ( assert ) {
	var ImageWithSuggestionsWidget = require( pathToWidget ),
		suggestionsWithNullTitle = suggestions.concat( [ { text: null, wikidataId: 'Q321' } ] ),
		imageDataWithNullTitle = $.extend( {}, imageData ),
		widget;

	imageDataWithNullTitle.suggestions = suggestionsWithNullTitle;
	widget = new ImageWithSuggestionsWidget( imageDataWithNullTitle );

	assert.strictEqual( widget.suggestionsWidget.items.length, 3 );
} );

QUnit.test( 'Publish button is only enabled when at least one suggestion is confirmed', function ( assert ) {
	var ImageWithSuggestionsWidget = require( pathToWidget ),
		widget = new ImageWithSuggestionsWidget( imageData );

	// Disabled by default.
	assert.strictEqual( widget.publishButton.isDisabled(), true );

	// Enabled after suggestion is confirmed.
	widget.suggestionsWidget.findItemFromData( 'Q123' ).setSelected( true );
	assert.strictEqual( widget.publishButton.isDisabled(), false );

	// Disabled after single confirmed suggestion is unconfirmed.
	widget.suggestionsWidget.findItemFromData( 'Q123' ).setSelected( false );
	assert.strictEqual( widget.publishButton.isDisabled(), true );
} );

QUnit.test( 'Successful publish results in tagPublished event', function ( assert ) {
	var ImageWithSuggestionsWidget = require( pathToWidget ),
		widget = new ImageWithSuggestionsWidget( imageData ),
		done = assert.async();

	// Add a function that gets called when event is emitted.
	widget.onTagsPublished = sinon.stub();
	widget.connect( widget, { tagsPublished: 'onTagsPublished' } );

	widget.onFinalConfirm()
		.then( function () {
			assert.strictEqual( widget.onTagsPublished.called, true );
			done();
		} );
} );

QUnit.test( 'Successful publish removes element and emits itemRemoved event', function ( assert ) {
	var ImageWithSuggestionsWidget = require( pathToWidget ),
		widget = new ImageWithSuggestionsWidget( imageData ),
		done = assert.async();

	// Add a function that gets called when event is emitted.
	widget.onItemRemoved = sinon.stub();
	widget.connect( widget, { itemRemoved: 'onItemRemoved' } );

	// Stub out the element's remove function.
	widget.$element.remove = sinon.stub();

	widget.onFinalConfirm()
		.then( function () {
			assert.strictEqual( widget.$element.remove.called, true );
			assert.strictEqual( widget.onItemRemoved.called, true );
			done();
		} );
} );

QUnit.module( 'ImageWithSuggestionsWidget API call fails', {
	beforeEach: function () {
		hooks.beforeEach();
		sandbox = sinon.sandbox.create();

		// Set api.postWithToken to return a rejected promise.
		global.wikibase.api.getLocationAgnosticMwApi.returns( {
			postWithToken: sandbox.stub().returns(
				$.Deferred().reject( {} ).promise( { abort: function () {} } )
			)
		} );
	},
	afterEach: function () {
		hooks.afterEach();
		sandbox.restore();
	}
} );

QUnit.test( 'Failed publish results in publishError event', function ( assert ) {
	var ImageWithSuggestionsWidget = require( pathToWidget ),
		widget = new ImageWithSuggestionsWidget( imageData ),
		done = assert.async();

	// Add a function that gets called when event is emitted.
	widget.onPublishError = sinon.stub();
	widget.connect( widget, { publishError: 'onPublishError' } );

	widget.onFinalConfirm()
		.fail( function () {
			assert.strictEqual( widget.onPublishError.called, true );
			done();
		} );
} );

QUnit.test( 'Failed publish removes element and emits itemRemoved event', function ( assert ) {
	var ImageWithSuggestionsWidget = require( pathToWidget ),
		widget = new ImageWithSuggestionsWidget( imageData ),
		done = assert.async();

	// Add a function that gets called when event is emitted.
	widget.onItemRemoved = sinon.stub();
	widget.connect( widget, { itemRemoved: 'onItemRemoved' } );

	// Stub out the element's remove function.
	widget.$element.remove = sinon.stub();

	widget.onFinalConfirm()
		.fail( function () {
			assert.strictEqual( widget.$element.remove.called, true );
			assert.strictEqual( widget.onItemRemoved.called, true );
			done();
		} );
} );
