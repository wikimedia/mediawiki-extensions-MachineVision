var sinon = require( 'sinon' ),
	utils = require( '@wikimedia/mw-node-qunit' ),
	newMockMediaWiki = require( '@wikimedia/mw-node-qunit/src/mockMediaWiki' ),
	sandbox;

module.exports = {
	beforeEach: function () {
		sandbox = sinon.createSandbox();

		global.mw = newMockMediaWiki();
		global.OO = require( 'oojs' );
		require( 'oojs-ui' );
		require( 'oojs-ui/dist/oojs-ui-wikimediaui.js' );

		// Ensure a user ID is sent to the cardstack for the personal uploads tab.
		global.mw.user.getId = function () {};
		sandbox.stub( global.mw.user, 'getId' ).returns( 123 );

		// Stub for the jQuery msg plugin.
		global.$.fn.msg = sinon.stub();
	},

	afterEach: function () {
		delete require.cache[ require.resolve( 'oojs-ui/dist/oojs-ui-wikimediaui.js' ) ];
		delete require.cache[ require.resolve( 'oojs-ui' ) ];
		delete require.cache[ require.resolve( 'oojs' ) ];
		sandbox.restore();
	}
};
