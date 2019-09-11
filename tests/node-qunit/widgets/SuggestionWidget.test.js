var pathToWidget = '../../../resources/widgets/SuggestionWidget.js',
	hooks = require( '../support/hooks.js' );

QUnit.module( 'SuggestionWidget', hooks );

QUnit.test( 'Constructor test', function ( assert ) {
	var SuggestionWidget = require( pathToWidget ),
		data = { text: 'Test label' },
		widget = new SuggestionWidget( { suggestionData: data } );
	assert.ok( true );
} );
