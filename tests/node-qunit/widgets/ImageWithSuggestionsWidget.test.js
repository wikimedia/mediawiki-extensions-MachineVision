var pathToWidget = '../../../resources/widgets/ImageWithSuggestionsWidget.js',
	hooks = require( '../support/hooks.js' ),
	helpers = require( '../support/helpers.js' ),
	sinon = require( 'sinon' ),
	sandbox,
	suggestions = [
		{ text: 'cat' },
		{ text: 'domestic shorthair' },
		{ text: 'whiskers' }
	],
	imageData = {
		descriptionUrl: 'https://example.com/File:Cat.jpg',
		suggestions: suggestions,
		thumbUrl: 'https://example.com/thumbnails/Cat.jpg',
		title: 'Domestic shorthair cat with whiskers'
	},
	datamodel = require( 'wikibase.datamodel' );

QUnit.module( 'ImageWithSuggestionsWidget', hooks );

QUnit.test( 'Constructor test', function ( assert ) {
	var ImageWithSuggestionsWidget = require( pathToWidget ),
		widget = new ImageWithSuggestionsWidget( imageData );
	assert.ok( true );
} );

QUnit.test( 'Suggestion widgets are created from data passed as config', function ( assert ) {
	var ImageWithSuggestionsWidget = require( pathToWidget ),
		widget = new ImageWithSuggestionsWidget( imageData ),
		SuggestionWidget = require( '../../../resources/widgets/SuggestionWidget.js' );

	assert.strictEqual( widget.suggestionWidgets.length, 3 );
	assert.strictEqual( widget.suggestionWidgets[ 0 ] instanceof SuggestionWidget, true );
} );

QUnit.test( 'Suggestions with no label are skipped', function ( assert ) {
	var ImageWithSuggestionsWidget = require( pathToWidget ),
		suggestionsWithNullTitle = suggestions.concat( [ { text: null } ] ),
		imageDataWithNullTitle = $.extend( {}, imageData ),
		widget;

	imageDataWithNullTitle.suggestions = suggestionsWithNullTitle;
	widget = new ImageWithSuggestionsWidget( imageDataWithNullTitle );

	assert.strictEqual( widget.suggestionWidgets.length, 3 );
} );

QUnit.test( 'Publish and reset buttons are only enabled when at least one suggestion is confirmed', function ( assert ) {
	var ImageWithSuggestionsWidget = require( pathToWidget ),
		widget = new ImageWithSuggestionsWidget( imageData );

	// Disabled by default.
	assert.strictEqual( widget.publishButton.isDisabled(), true );
	assert.strictEqual( widget.resetButton.isDisabled(), true );

	// Enabled after suggestion is confirmed.
	widget.onToggleSuggestion( true );
	assert.strictEqual( widget.publishButton.isDisabled(), false );
	assert.strictEqual( widget.resetButton.isDisabled(), false );

	// Disabled after single confirmed suggestion is unconfirmed.
	widget.onToggleSuggestion( false );
	assert.strictEqual( widget.publishButton.isDisabled(), true );
	assert.strictEqual( widget.resetButton.isDisabled(), true );
} );

QUnit.test( 'Reset button unconfirms all suggestions and disables publish and reset buttons', function ( assert ) {
	var ImageWithSuggestionsWidget = require( pathToWidget ),
		widget = new ImageWithSuggestionsWidget( imageData );

	// Confirm two suggestions.
	widget.onToggleSuggestion( true );
	widget.onToggleSuggestion( true );

	// Run onReset handler.
	widget.onReset();

	// Confirm all suggestion widgets are unconfirmed.
	widget.suggestionWidgets.map( function ( suggestion ) {
		assert.strictEqual( suggestion.confirmed, false );
	} );

	// Confirm buttons are disabled.
	assert.strictEqual( widget.publishButton.isDisabled(), true );
	assert.strictEqual( widget.resetButton.isDisabled(), true );
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
