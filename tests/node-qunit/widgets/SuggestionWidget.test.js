var pathToWidget = '../../../resources/widgets/SuggestionWidget.js',
	hooks = require( '../support/hooks.js' );

QUnit.module( 'SuggestionWidget', hooks );

QUnit.test( 'Constructor test', function ( assert ) {
	var SuggestionWidget = require( pathToWidget ),
		data = { text: 'Test label', confirmed: false },
		widget = new SuggestionWidget( { suggestionData: data } );
	assert.ok( true );
} );

QUnit.test( 'Unconfirmed widget becomes confirmed when toggled', function ( assert ) {
	var SuggestionWidget = require( pathToWidget ),
		data = { text: 'Test label', confirmed: false },
		widget = new SuggestionWidget( { suggestionData: data } ),
		done = assert.async();

	widget.on( 'toggleSuggestion', function () {
		assert.strictEqual( widget.confirmed, true );
	} );

	widget.toggleSuggestion();

	setTimeout( function () {
		done();
	}, 100 );
} );

QUnit.test( 'Confirmed widget becomes unconfirmed when toggled', function ( assert ) {
	var SuggestionWidget = require( pathToWidget ),
		data = { text: 'Test label', confirmed: true },
		widget = new SuggestionWidget( { suggestionData: data } ),
		done = assert.async();

	widget.on( 'toggleSuggestion', function () {
		assert.strictEqual( widget.confirmed, false );
	} );

	widget.toggleSuggestion();

	setTimeout( function () {
		done();
	}, 100 );
} );

QUnit.test( 'Enter keypress on widget emits toggleSuggestion event', function ( assert ) {
	var SuggestionWidget = require( pathToWidget ),
		data = { text: 'Test label', confirmed: false },
		widget = new SuggestionWidget( { suggestionData: data } ),
		done = assert.async();

	widget.on( 'toggleSuggestion', function () {
		assert.ok( true );
	} );

	widget.onKeypress( { keyCode: 13 } );

	setTimeout( function () {
		done();
	}, 100 );
} );
