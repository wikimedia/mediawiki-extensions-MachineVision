var pathToWidget = '../../../resources/widgets/UserMessage.js',
	hooks = require( '../support/hooks.js' ),
	config = {
		heading: 'Test user message',
		text: 'Test user message text',
		cta: 'Click me',
		disclaimer: 'Or not',
		event: 'close'
	};

QUnit.module( 'UserMessage', hooks );

QUnit.test( 'Constructor test', function ( assert ) {
	var UserMessage = require( pathToWidget ),
		widget = new UserMessage( config );
	assert.ok( true );
} );

QUnit.test( 'CTA button click triggers event passsed as config', function ( assert ) {
	var UserMessage = require( pathToWidget ),
		widget = new UserMessage( config );

	widget.on( 'close', function () {
		assert.ok( true );
	} );

	widget.onClick();
} );
