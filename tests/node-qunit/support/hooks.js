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
	},

	afterEach: function () {
		delete require.cache[ require.resolve( 'oojs-ui/dist/oojs-ui-wikimediaui.js' ) ];
		delete require.cache[ require.resolve( 'oojs-ui' ) ];
		delete require.cache[ require.resolve( 'oojs' ) ];
		sandbox.restore();
	}
};
