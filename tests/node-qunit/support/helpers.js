var sinon = require( 'sinon' ),
	mockery = require( 'mockery' ),
	config = require( './../fixtures/config.js' ),
	mockCache = {};

/**
 * Allows requiring a module more than once.
 * Useful for e.g. wikibase files, which aren't really modules,
 * but code that is executed immediately, which we'll want to
 * run before every test.
 *
 * @param {string} module
 * @return {*}
 */
function requireAgain( module ) {
	try {
		delete require.cache[ require.resolve( module ) ];
	} catch ( e ) {
		// couldn't resolve module, so there'll be no cache for sure
	}
	return require( module );
}
module.exports.requireAgain = requireAgain;

/**
 * Stubs out a basic stand-in for the mw.user object.
 *
 * @param {boolean} loggedIn Whether to simulate a logged-in user
 * @param {boolean} autoconfirmed Whether to simulate an autoconfirmed user
 * @return {Object} user
 */
module.exports.createMediaWikiUser = function ( loggedIn, autoconfirmed ) {
	var user = {
		isAnon: sinon.stub(),
		options: {
			get: sinon.stub(),
			set: sinon.stub()
		}
	};

	if ( loggedIn ) {
		user.isAnon.returns( false );
	} else {
		user.isAnon.returns( true );
	}

	return user;
};

/**
 * Stubs out and/or loads a basic "wikibase" object for use in testing.
 *
 * @return {Object}
 */
module.exports.createWikibaseEnv = function () {
	return {
		api: {
			getLocationAgnosticMwApi: sinon.stub().returns( {
				get: sinon.stub().returns(
					$.Deferred().resolve( {} ).promise( { abort: function () {} } )
				),
				post: sinon.stub().returns(
					$.Deferred().resolve( {} ).promise( { abort: function () {} } )
				),
				postWithToken: sinon.stub().returns(
					$.Deferred().resolve( {} ).promise( { abort: function () {} } )
				)
			} )
		},
		utilities: {
			ClaimGuidGenerator: sinon.stub().returns( {
				newGuid: function () { return Math.random().toString( 36 ).slice( 2 ); }
			} )
		}
	};
};

/**
 * Stubs out and/or loads a basic "dataValues" object for use in testing.
 *
 * @return {Object}
 */
module.exports.createDataValuesEnv = function () {
	var oldDataValues = global.dataValues,
		oldUtil = global.util,
		oldJQuery = global.jQuery,
		old$ = global.$;

	// `require` caches the exports and reuses them the next require
	// the files required below have no exports, though - they just
	// execute and are assigned as properties of an object
	// `requireAgain` would make sure they keep doing that over and
	// over, but then they'll end up creating the same functions/objects
	// more than once, but different instances...
	// other modules, with actual exports, that use these functions
	// might encounter side-effects though, because the instances of
	// those objects are different when loaded at different times,
	// so to be safe, we'll try to emulate regular `require` behavior
	// by running these files once, grabbing the result, caching it,
	// and re-using the result from cache
	if ( mockCache.dataValues ) {
		return mockCache.dataValues;
	}

	// wikibase-data-values needs jquery...
	global.jQuery = global.$ = requireAgain( 'jquery' );

	global.dataValues = requireAgain( 'wikibase-data-values/src/dataValues.js' ).dataValues;
	global.util = {};

	requireAgain( 'wikibase-data-values/lib/util/util.inherit.js' );
	requireAgain( 'wikibase-data-values/src/DataValue.js' );

	mockCache.dataValues = global.dataValues;

	// restore global scope before returning
	global.dataValues = oldDataValues;
	global.util = oldUtil;
	global.jQuery = oldJQuery;
	global.$ = old$;

	return mockCache.dataValues;
};

/**
 * Loads a "wikibase.datamodel" object for use in testing.
 */
module.exports.registerWbDataModel = function () {
	global.dataValues = this.createDataValuesEnv();
	global.util = {};

	requireAgain( 'wikibase-data-values/lib/util/util.inherit.js' );

	mockery.registerSubstitute( 'wikibase.datamodel', 'wikibase-data-model/src/index.js' );
};

/**
 * Loads a "wikibase.serialization" object for use in testing.
 */
module.exports.registerWbSerialization = function () {
	global.util = {};

	requireAgain( 'wikibase-data-values/lib/util/util.inherit.js' );

	mockery.registerSubstitute( 'wikibase.serialization', 'wikibase-serialization/src/index.js' );
};

/**
 * Loads a "ext.MachineVision.config" object for use in testing.
 */
module.exports.registerMvConfigVars = function () {
	mockery.registerMock( 'ext.MachineVision.config', config );
};

/**
 * Stub event logger.
 * @return {Object}
 */
module.exports.createEventLogger = function () {
	return { logEvent: sinon.stub() };
};
