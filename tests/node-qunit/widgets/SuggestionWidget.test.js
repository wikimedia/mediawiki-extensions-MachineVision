var pathToWidget = '../../../resources/widgets/SuggestionWidget.js',
	hooks = require( '../support/hooks.js' );

QUnit.module( 'SuggestionWidget', hooks );

QUnit.test( 'Constructor test', function ( assert ) {
	var SuggestionWidget = require( pathToWidget ),
		data = { text: 'Test label' },
		widget = new SuggestionWidget( { suggestionData: data } );
	assert.ok( true );
} );

QUnit.test( 'Unconfirmed widget emits confirmSuggestion event', function ( assert ) {
	var SuggestionWidget = require( pathToWidget ),
		data = { text: 'Test label' },
		widget = new SuggestionWidget( {
			suggestionData: data,
			confirmed: false
		} ),
		done = assert.async();

	widget.on( 'confirmSuggestion', function () {
		assert.ok( true );
	} );

	widget.toggleSuggestion();

	setTimeout( function () {
		done();
	}, 100 );
} );

QUnit.test( 'Confirmed widget emits unconfirmSuggestion event', function ( assert ) {
	var SuggestionWidget = require( pathToWidget ),
		data = { text: 'Test label' },
		widget = new SuggestionWidget( {
			suggestionData: data,
			confirmed: true
		} ),
		done = assert.async();

	widget.on( 'unconfirmSuggestion', function () {
		assert.ok( true );
	} );

	widget.toggleSuggestion();

	setTimeout( function () {
		done();
	}, 100 );
} );

QUnit.test( 'Enter keypress on unconfirmed widget emits confirmSuggestion event', function ( assert ) {
	var SuggestionWidget = require( pathToWidget ),
		data = { text: 'Test label' },
		widget = new SuggestionWidget( {
			suggestionData: data,
			confirmed: false
		} ),
		done = assert.async();

	widget.on( 'confirmSuggestion', function () {
		assert.ok( true );
	} );

	widget.onKeypress( { keyCode: 13 } );

	setTimeout( function () {
		done();
	}, 100 );
} );

QUnit.test( 'Enter keypress on confirmed widget emits unconfirmSuggestion event', function ( assert ) {
	var SuggestionWidget = require( pathToWidget ),
		data = { text: 'Test label' },
		widget = new SuggestionWidget( {
			suggestionData: data,
			confirmed: true
		} ),
		done = assert.async();

	widget.on( 'unconfirmSuggestion', function () {
		assert.ok( true );
	} );

	widget.onKeypress( { keyCode: 13 } );

	setTimeout( function () {
		done();
	}, 100 );
} );
