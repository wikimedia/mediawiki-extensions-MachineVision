var pathToWidget = '../../../resources/widgets/SuggestionsWidget.js',
	hooks = require( '../support/hooks.js' );

QUnit.module( 'SuggestionsWidget', hooks );

QUnit.test( 'Constructor test', function ( assert ) {
	var SuggestionsWidget = require( pathToWidget ),
		suggestions = [
			{ text: 'cat', wikidataId: 'Q123' },
			{ text: 'whiskers', wikidataId: 'Q456' }
		],
		widget = new SuggestionsWidget( { suggestions: suggestions } );
	assert.ok( true );
} );
