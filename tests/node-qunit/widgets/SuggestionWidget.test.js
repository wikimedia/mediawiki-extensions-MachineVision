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
		widget = new SuggestionWidget( { suggestionData: data } );

	widget.on( 'toggleSuggestion', function () {
		assert.strictEqual( widget.confirmed, true );
	} );

	widget.toggleSuggestion();
} );

QUnit.test( 'Confirmed widget becomes unconfirmed when toggled', function ( assert ) {
	var SuggestionWidget = require( pathToWidget ),
		data = { text: 'Test label', confirmed: true },
		widget = new SuggestionWidget( { suggestionData: data } );

	widget.on( 'toggleSuggestion', function () {
		assert.strictEqual( widget.confirmed, false );
	} );

	widget.toggleSuggestion();
} );

QUnit.test( 'Enter keypress on widget toggles the suggestion', function ( assert ) {
	var SuggestionWidget = require( pathToWidget ),
		data = { text: 'Test label', confirmed: false },
		widget = new SuggestionWidget( { suggestionData: data } );

	widget.on( 'toggleSuggestion', function () {
		assert.ok( true );
	} );

	widget.onKeydown( { key: 'Enter' } );
} );
