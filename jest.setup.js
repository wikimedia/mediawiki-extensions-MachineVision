/* global jest:false */
const Vue = require( 'vue' );
const { config } = require( '@vue/test-utils' );

// Mock Vue plugins in test suites
config.global.mocks = {
	$logEvent: jest.fn().mockResolvedValue( {} ),
	$i18n: ( str ) => {
		return {
			text: () => str,
			parse: () => str
		};
	}
};

config.global.directives = {
	'i18n-html': ( el, binding ) => {
		el.innerHTML = `${binding.arg} (${binding.value})`;
	}
};

Vue.configureCompat( {
	MODE: 3
} );

// Mock API (instances created ggwith new mw.Api() )
function Api() { }
Api.prototype.get = jest.fn().mockResolvedValue( {} );
Api.prototype.post = jest.fn().mockResolvedValue( {} );
Api.prototype.getToken = jest.fn().mockResolvedValue( {} );
Api.prototype.postWithToken = jest.fn().mockResolvedValue( {} );

// Fake mw.Title instance
const fakeTitle = {
	getRelativeText: jest.fn()
};

// Mock MW object
const mw = {
	Api: Api,
	RegExp: {
		escape: jest.fn()
	},
	Title: {
		newFromText: jest.fn().mockReturnValue( fakeTitle ),
		makeTitle: jest.fn( fakeTitle )
	},
	Uri: jest.fn(),
	config: {
		get: jest.fn()
	},
	confirmCloseWindow: jest.fn(),
	hook: jest.fn().mockReturnValue( {
		fire: jest.fn()
	} ),
	experiments: {
		getBucket: jest.fn()
	},
	html: {
		escape: function ( str ) {
			return str.replace( /['"<>&]/g, function ( char ) {
				switch ( char ) {
					case '\'': return '&#039;';
					case '"': return '&quot;';
					case '<': return '&lt;';
					case '>': return '&gt;';
					case '&': return '&amp;';
				}
			} );
		}
	},
	jqueryMsg: {
		parser: jest.fn()
	},
	language: {
		convertNumber: jest.fn(),
		getData: jest.fn().mockReturnValue( {} )
	},
	log: {
		deprecate: jest.fn(),
		warn: jest.fn()
	},
	message: jest.fn().mockReturnValue( {
		text: jest.fn(),
		parse: jest.fn()
	} ),
	msg: jest.fn(),
	now: Date.now.bind( Date ),
	template: {
		get: jest.fn().mockReturnValue( {
			render: jest.fn()
		} )
	},
	user: {
		tokens: {
			get: jest.fn()
		},
		options: {
			get: jest.fn()
		},
		isAnon: jest.fn(),
		generateRandomSessionId: function () {
			return Math.random().toString();
		},
		getId: function () {
			return Math.round( Math.random() * 1000 );
		}
	},
	trackSubscribe: jest.fn(),
	track: jest.fn(),
	util: {
		getParamValue: jest.fn(),
		getUrl: jest.fn()
	},
	loader: {
		load: jest.fn(),
		using: jest.fn(),
		require: jest.fn()
	},
	requestIdleCallback: jest.fn(),
	storage: {
		get: jest.fn(),
		set: jest.fn(),
		remove: jest.fn()
	},
	notify: jest.fn()
};

global.mw = mw;

global.$ = require( 'jquery' );
global.OO = require( 'oojs' );
require( 'oojs-ui' );
require( 'oojs-ui/dist/oojs-ui-wikimediaui.js' );

const mockWbApi = {
	get: jest.fn().mockResolvedValue( {} ),
	post: jest.fn().mockResolvedValue( {} ),
	postWithToken: jest.fn().mockResolvedValue( {} )
};

global.wikibase = {
	api: {
		getLocationAgnosticMwApi: function () {
			return mockWbApi;
		}
	},
	utilities: {
		ClaimGuidGenerator: function () {
			return {
				newGuid: jest.fn()
			};
		}
	}
};
