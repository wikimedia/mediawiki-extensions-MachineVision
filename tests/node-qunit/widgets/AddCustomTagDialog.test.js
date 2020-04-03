var pathToWidget = '../../../resources/widgets/AddCustomTagDialog.js',
	hooks = require( '../support/hooks.js' );

QUnit.module( 'AddCustomTagDialog', hooks );

QUnit.test( 'Constructor test', function ( assert ) {
	var AddCustomTagDialog = require( pathToWidget ),
		widget = new AddCustomTagDialog();

	assert.ok( true );
} );

QUnit.test( 'Add button click results in addCustomTag event', function ( assert ) {
	var AddCustomTagDialog = require( pathToWidget ),
		widget = new AddCustomTagDialog(),
		windowManager = new OO.ui.WindowManager();

	widget.on( 'addCustomTag', function () {
		assert.ok( true );
	} );

	// We need a window manager so that widget.close() can run as part of the
	// 'addCustomTag' action, but we don't actually need to open the dialog for
	// this test.
	windowManager = new OO.ui.WindowManager();
	$( document.body ).append( windowManager.$element );
	windowManager.addWindows( [ widget ] );

	widget.executeAction( 'addCustomTag' );
} );
