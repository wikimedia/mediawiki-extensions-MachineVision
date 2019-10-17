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
