var sinon = require( 'sinon' ),
	mockery = require( 'mockery' ),
	newMockMediaWiki = require( '@wikimedia/mw-node-qunit/src/mockMediaWiki' ),
	helpers = require( './helpers.js' ),
	sandbox;

module.exports = {
	beforeEach: function () {
		sandbox = sinon.createSandbox();

		global.OO = require( 'oojs' );
		require( 'oojs-ui' );
		require( 'oojs-ui/dist/oojs-ui-wikimediaui.js' );

		mockery.enable( {
			warnOnReplace: false,
			warnOnUnregistered: false
		} );

		global.mw = newMockMediaWiki();
		helpers.registerMvConfigVars();

		global.dataValues = helpers.createDataValuesEnv();
		global.wikibase = helpers.createWikibaseEnv();
		helpers.registerWbDataModel();
		helpers.registerWbSerialization();

		global.mw.eventLog = helpers.createEventLogger();

		// Ensure a user ID is sent to the cardstack for the personal uploads tab.
		global.mw.user.getId = function () {};
		sandbox.stub( global.mw.user, 'getId' ).returns( 123 );

		// Stub for the jQuery msg plugin.
		global.$.fn.msg = sinon.stub();

		// Stub the history object
		sandbox.stub( global.window.history, 'replaceState' );
	},

	afterEach: function () {
		mockery.disable();
		delete require.cache[ require.resolve( 'oojs-ui/dist/oojs-ui-wikimediaui.js' ) ];
		delete require.cache[ require.resolve( 'oojs-ui' ) ];
		delete require.cache[ require.resolve( 'oojs' ) ];
		sandbox.restore();
	}
};
