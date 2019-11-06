var pathToWidget = '../../../resources/widgets/ConfirmTagsDialog.js',
	hooks = require( '../support/hooks.js' ),
	config = {
		tagsList: 'Cat, domestic shorthair, whiskers',
		imgUrl: 'https://example.com/thumbnails/Cat.jpg',
		imgTitle: 'Domestic shorthair cat with whiskers'
	};

QUnit.module( 'ConfirmTagsDialog', hooks );

QUnit.test( 'Constructor test', function ( assert ) {
	var ConfirmTagsDialog = require( pathToWidget ),
		widget = new ConfirmTagsDialog( config );
	assert.ok( true );
} );

QUnit.test( 'Confirm button click results in confirm event', function ( assert ) {
	var ConfirmTagsDialog = require( pathToWidget ),
		widget = new ConfirmTagsDialog( config ),
		windowManager = new OO.ui.WindowManager();

	widget.on( 'confirm', function () {
		assert.ok( true );
	} );

	// We need a window manager so that widget.close() can run as part of the
	// confirm action, but we don't actually need to open the dialog for this
	// test.
	windowManager = new OO.ui.WindowManager();
	$( document.body ).append( windowManager.$element );
	windowManager.addWindows( [ widget ] );

	widget.executeAction( 'confirm' );
} );
