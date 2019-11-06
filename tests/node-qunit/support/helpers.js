var sinon = require( 'sinon' );

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
		}
	};
};
