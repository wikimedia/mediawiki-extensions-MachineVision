var pathToWidget = '../../../resources/widgets/SuggestionBaseWidget.js',
	hooks = require( '../support/hooks.js' );

QUnit.module( 'SuggestionBaseWidget', hooks );

QUnit.test( 'Constructor test', function ( assert ) {
	var SuggestionBaseWidget = require( pathToWidget ),
		data = { text: 'Test label' },
		widget = new SuggestionBaseWidget( { suggestionData: data } );

	assert.ok( true );
} );

QUnit.test( 'emitConfirmSuggestion emits confirmSuggestion event', function ( assert ) {
	var SuggestionBaseWidget = require( pathToWidget ),
		data = { text: 'Test label' },
		widget = new SuggestionBaseWidget( { suggestionData: data } ),
		done = assert.async();

	widget.on( 'confirmSuggestion', function () {
		assert.ok( true );
	} );

	widget.emitConfirmSuggestion();

	setTimeout( function () {
		done();
	}, 100 );
} );

QUnit.test( 'emitUnconfirmSuggestion emits unconfirmSuggestion event', function ( assert ) {
	var SuggestionBaseWidget = require( pathToWidget ),
		data = { text: 'Test label' },
		widget = new SuggestionBaseWidget( { suggestionData: data } ),
		done = assert.async();

	widget.on( 'unconfirmSuggestion', function () {
		assert.ok( true );
	} );

	widget.emitUnconfirmSuggestion();

	setTimeout( function () {
		done();
	}, 100 );
} );

QUnit.test( 'emitRejectSuggestion emits rejectSuggestion event', function ( assert ) {
	var SuggestionBaseWidget = require( pathToWidget ),
		data = { text: 'Test label' },
		widget = new SuggestionBaseWidget( { suggestionData: data } ),
		done = assert.async();

	widget.on( 'rejectSuggestion', function () {
		assert.ok( true );
	} );

	widget.emitRejectSuggestion();

	setTimeout( function () {
		done();
	}, 100 );
} );

QUnit.test( 'emitUnrejectSuggestion emits unrejectSuggestion event', function ( assert ) {
	var SuggestionBaseWidget = require( pathToWidget ),
		data = { text: 'Test label' },
		widget = new SuggestionBaseWidget( { suggestionData: data } ),
		done = assert.async();

	widget.on( 'unrejectSuggestion', function () {
		assert.ok( true );
	} );

	widget.emitUnrejectSuggestion();

	setTimeout( function () {
		done();
	}, 100 );
} );
