var pathToWidget = '../../../resources/widgets/EntityAutocompleteInputWidget.js',
	hooks = require( '../support/hooks.js' ),
	config = {
		placeholder: 'Search to add items (house cat, mountain, Taj Mahal, etc.)',
		icon: 'search'
	};

QUnit.module( 'EntityAutocompleteInputWidget', hooks );

QUnit.test( 'Constructor test', function ( assert ) {
	var EntityAutocompleteInputWidget = require( pathToWidget ),
		widget = new EntityAutocompleteInputWidget( config );

	assert.ok( true );
} );

QUnit.test( 'Enter keypress triggers add event if value is present', function ( assert ) {
	var EntityAutocompleteInputWidget = require( pathToWidget ),
		widget = new EntityAutocompleteInputWidget( config );

	widget.on( 'enter', function () {
		assert.ok( true );
	} );

	widget.setValue( 'cat' );
	widget.onKeydown( { key: 'Enter' } );
} );
