'use strict';

const actions = require( '../../resources/store/actions.js' ),
	imageFixtures = require( './fixtures/imageData.json' ),
	apiResponse = require( './fixtures/apiResponse.json' ),
	mockApi = global.wikibase.api.getLocationAgnosticMwApi();

describe( 'getters', () => {
	let fixtures,
		context;

	beforeEach( () => {
		jest.clearAllMocks();
		// Create a fresh copy of imageFixtures so any mutations made to the
		// data is reset for each test
		fixtures = [ ...imageFixtures ];

		// Context objects are jest mock functions whose calls can be
		// investigated in tests
		context = {
			state: {
				currentTab: 'popular',
				images: {
					popular: [],
					user: []
				}
			},
			commit: jest.fn(),
			getters: {},
			dispatch: jest.fn()
		};

		// Reset the mock API before each test
		mockApi.get = jest.fn().mockResolvedValue( {} );
		mockApi.post = jest.fn().mockResolvedValue( {} );
		mockApi.postWithToken = jest.fn().mockResolvedValue( {} );
	} );

	describe( 'updateCurrentTab', () => {
		it( 'it calls the setTab mutation', () => {
			actions.updateCurrentTab( context, 'user' );
			expect( context.commit.mock.calls[ 0 ][ 0 ] ).toBe( 'setTab' );
		} );

		it( 'setTab mutation is called with the correct tab argument', () => {
			actions.updateCurrentTab( context, 'user' );
			expect( context.commit.mock.calls[ 0 ][ 1 ] ).toBe( 'user' );
		} );
	} );

	describe( 'getImages', () => {
		it( 'makes a GET request to the API with the correct parameters', () => {
			var deferred = $.Deferred(),
				promise = deferred.promise();

			mockApi.get.mockReturnValue( promise );

			actions.getImages( context );
			expect( mockApi.get ).toHaveBeenCalled();
			expect( mockApi.get ).toHaveBeenCalledWith(
				expect.objectContaining( {
					action: 'query',
					format: 'json',
					formatversion: 2,
					generator: 'unreviewedimagelabels',
					guillimit: 10,
					prop: 'imageinfo|imagelabels|categories',
					iiprop: 'url',
					iiurlwidth: 800,
					ilstate: 'unreviewed',
					meta: 'unreviewedimagecount',
					uselang: mw.config.get( 'wgUserLanguage' ),
					cllimit: 500,
					clshow: '!hidden'
				} )
			);
		} );

		it( 'defaults to fetching images for the current tab queue if no "queue" option is provided', () => {
			var deferred = $.Deferred(),
				promise = deferred.promise();

			mockApi.get.mockReturnValue( promise );

			context.state.currentTab = 'popular';
			actions.getImages( context );
			expect( context.commit ).toHaveBeenCalledWith( 'setFetchPending', {
				queue: 'popular',
				pending: true
			} );

			context.state.currentTab = 'user';
			actions.getImages( context );
			expect( context.commit ).toHaveBeenCalledWith( 'setFetchPending', {
				queue: 'user',
				pending: true
			} );
		} );

		it( 'fetches user images if a "user" queue option is provided', () => {
			var deferred = $.Deferred(),
				promise = deferred.promise();

			mockApi.get.mockReturnValue( promise );

			context.state.currentTab = 'popular';
			actions.getImages( context, { queue: 'user' } );
			expect( context.commit ).toHaveBeenCalledWith( 'setFetchPending', {
				queue: 'user',
				pending: true
			} );
		} );

		it( 'Commits an addImage mutation for each image in the response', done => {
			var apiImages = apiResponse.query.pages,
				deferred = $.Deferred(),
				promise = deferred.resolve( apiResponse ).promise();

			mockApi.get.mockReturnValue( promise );

			actions.getImages( context ).then( () => {
				var mutations = context.commit.mock.calls,
					addImageMutations = mutations.filter( mutation => {
						return mutation[ 0 ] === 'addImage';
					} );

				expect( addImageMutations.length ).toBe( apiImages.length );
				done();
			} );
		} );

		it( 'Removes the pending state on the appropriate queue when request completes', done => {
			var deferred = $.Deferred(),
				promise = deferred.resolve( apiResponse ).promise();

			context.state.currentTab = 'popular';
			mockApi.get.mockReturnValue( promise );

			actions.getImages( context ).then( () => {
				expect( context.commit ).toHaveBeenCalledWith( 'setFetchPending', {
					queue: 'popular',
					pending: false
				} );
				done();
			} );
		} );

		it( 'Handles fetch errors successfully', done => {
			var deferred = $.Deferred(),
				promise = deferred.promise();

			context.state.currentTab = 'popular';
			mockApi.get.mockReturnValue( promise );

			actions.getImages( context );

			promise.then( () => {
				// We only care about errors here, so do nothing
			} ).catch( () => {
				expect( context.commit ).toHaveBeenCalledWith( 'setFetchError', {
					queue: 'popular',
					error: true
				} );
			} ).always( () => {
				expect( context.commit ).toHaveBeenCalledWith( 'setFetchPending', {
					queue: 'popular',
					pending: false
				} );
				done();
			} );

			deferred.reject( {} );
		} );

		it( 'commits a setUnreviewedCount action when request completes', done => {
			var deferred = $.Deferred(),
				promise = deferred.resolve( apiResponse ).promise();

			mockApi.get.mockReturnValue( promise );
			context.state.currentTab = 'popular';

			actions.getImages( context ).then( () => {
				expect( context.commit ).toHaveBeenCalledWith(
					'setUnreviewedCount',
					apiResponse.query.unreviewedimagecount.user.unreviewed
				);
				done();
			} );
		} );
	} );

	describe( 'toggleTagConfirmation', () => {
		it( 'Commits a toggleSuggestion mutation with the tag as an argument', () => {
			var suggestions = fixtures[ 0 ].suggestions,
				tagIndex = 1,
				tag = suggestions[ tagIndex ];

			Object.defineProperty( context.getters, 'currentImageSuggestions', {
				get: jest.fn().mockReturnValue( suggestions )
			} );

			actions.toggleTagConfirmation( context, tag );
			expect( context.commit ).toHaveBeenCalledWith( 'toggleSuggestion', tag );
		} );
	} );

	describe( 'publishTags', () => {
		it( 'dispatches the setDepictsStatements action with a payload of all currently confirmed tags', () => {
			var suggestions = fixtures[ 0 ].suggestions,
				confirmed;

			suggestions[ 0 ].confirmed = true;

			confirmed = suggestions.filter( function ( suggestion ) {
				return suggestion.confirmed;
			} );

			Object.defineProperty( context.getters, 'currentImageSuggestions', {
				get: jest.fn().mockReturnValue( suggestions )
			} );

			Object.defineProperty( context.getters, 'currentImageTitle', {
				get: jest.fn().mockReturnValue( suggestions )
			} );

			Object.defineProperty( context.getters, 'currentImageNonDisplayableSuggestions', {
				get: jest.fn().mockReturnValue( [] )
			} );

			actions.publishTags( context );
			expect( context.dispatch ).toHaveBeenCalledWith( 'setDepictsStatements', confirmed );
		} );

		it( 'makes a reviewimagelabels POST request with a reviewbatch including both confirmed and unconfirmed tags', () => {
			var suggestions = fixtures[ 0 ].suggestions,
				reviewBatch,
				json;

			suggestions[ 0 ].confirmed = true;
			reviewBatch = suggestions.map( suggestion => {
				return {
					label: suggestion.wikidataId,
					review: suggestion.confirmed ? 'accept' : 'reject'
				};
			} );

			json = JSON.stringify( reviewBatch );

			Object.defineProperty( context.getters, 'currentImageSuggestions', {
				get: jest.fn().mockReturnValue( suggestions )
			} );

			Object.defineProperty( context.getters, 'currentImageTitle', {
				get: jest.fn().mockReturnValue( 'Test' )
			} );

			Object.defineProperty( context.getters, 'currentImageNonDisplayableSuggestions', {
				get: jest.fn().mockReturnValue( [] )
			} );

			actions.publishTags( context );
			expect( mockApi.postWithToken ).toHaveBeenCalledWith( 'csrf', {
				action: 'reviewimagelabels',
				filename: 'Test',
				batch: json
			} );
		} );

		// For these tests, mockApi needs to return jQuery deferred objects
		// rather than vanilla promises

		it( 'shows success toast notification if requests are successful', done => {
			var suggestions = fixtures[ 0 ].suggestions,
				deferred = $.Deferred(),
				promise = deferred.promise(),
				successToast = {
					messageKey: 'machinevision-success-message',
					type: 'success',
					duration: 4
				};

			suggestions[ 0 ].confirmed = true;

			mockApi.postWithToken.mockReturnValue( promise );

			Object.defineProperty( context.getters, 'currentImageSuggestions', {
				get: jest.fn().mockReturnValue( suggestions )
			} );

			Object.defineProperty( context.getters, 'currentImageTitle', {
				get: jest.fn().mockReturnValue( 'Test' )
			} );

			Object.defineProperty( context.getters, 'currentImageNonDisplayableSuggestions', {
				get: jest.fn().mockReturnValue( [] )
			} );

			actions.publishTags( context );

			promise.always( () => {
				expect( context.dispatch ).toHaveBeenCalledWith( 'showImageMessage', successToast );
				done();
			} );

			deferred.resolve( {} );
		} );

		it( 'sets publishPending to false after successful request completes', done => {
			var suggestions = fixtures[ 0 ].suggestions,
				deferred = $.Deferred(),
				promise = deferred.promise();

			suggestions[ 0 ].confirmed = true;

			mockApi.postWithToken.mockReturnValue( promise );

			Object.defineProperty( context.getters, 'currentImageSuggestions', {
				get: jest.fn().mockReturnValue( suggestions )
			} );

			Object.defineProperty( context.getters, 'currentImageTitle', {
				get: jest.fn().mockReturnValue( 'Test' )
			} );

			Object.defineProperty( context.getters, 'currentImageNonDisplayableSuggestions', {
				get: jest.fn().mockReturnValue( [] )
			} );

			actions.publishTags( context );

			promise.always( () => {
				expect( context.dispatch ).toHaveBeenCalledWith( 'updatePublishPending', false );
				done();
			} );

			deferred.resolve( {} );
		} );

		it( 'shows error toast notification if requests fail', done => {
			var suggestions = fixtures[ 0 ].suggestions,
				deferred = $.Deferred(),
				promise = deferred.promise(),
				toast = {
					messageKey: 'machinevision-publish-error-message',
					type: 'error',
					duration: 8
				};

			suggestions[ 0 ].confirmed = true;

			mockApi.postWithToken.mockReturnValue( promise );

			Object.defineProperty( context.getters, 'currentImageSuggestions', {
				get: jest.fn().mockReturnValue( suggestions )
			} );

			Object.defineProperty( context.getters, 'currentImageTitle', {
				get: jest.fn().mockReturnValue( 'Test' )
			} );

			Object.defineProperty( context.getters, 'currentImageNonDisplayableSuggestions', {
				get: jest.fn().mockReturnValue( [] )
			} );

			actions.publishTags( context );

			promise.always( () => {
				expect( context.dispatch ).toHaveBeenCalledWith( 'showImageMessage', toast );
				done();
			} );

			deferred.reject( {} );
		} );

		it( 'sets publishPending to false after failed request completes', done => {
			var suggestions = fixtures[ 0 ].suggestions,
				deferred = $.Deferred(),
				promise = deferred.promise();

			suggestions[ 0 ].confirmed = true;

			mockApi.postWithToken.mockReturnValue( promise );

			Object.defineProperty( context.getters, 'currentImageSuggestions', {
				get: jest.fn().mockReturnValue( suggestions )
			} );

			Object.defineProperty( context.getters, 'currentImageTitle', {
				get: jest.fn().mockReturnValue( 'Test' )
			} );

			Object.defineProperty( context.getters, 'currentImageNonDisplayableSuggestions', {
				get: jest.fn().mockReturnValue( [] )
			} );

			actions.publishTags( context );

			promise.always( () => {
				expect( context.dispatch ).toHaveBeenCalledWith( 'updatePublishPending', false );
				done();
			} );

			deferred.reject( {} );
		} );

		it( 'dispatches a skipImage action on success', done => {
			var suggestions = fixtures[ 0 ].suggestions,
				deferred = $.Deferred(),
				promise = deferred.promise();

			suggestions[ 0 ].confirmed = true;

			mockApi.postWithToken.mockReturnValue( promise );

			Object.defineProperty( context.getters, 'currentImageSuggestions', {
				get: jest.fn().mockReturnValue( suggestions )
			} );

			Object.defineProperty( context.getters, 'currentImageTitle', {
				get: jest.fn().mockReturnValue( 'Test' )
			} );

			Object.defineProperty( context.getters, 'currentImageNonDisplayableSuggestions', {
				get: jest.fn().mockReturnValue( [] )
			} );

			actions.publishTags( context );

			promise.always( () => {
				expect( context.dispatch ).toHaveBeenCalledWith( 'skipImage' );
				done();
			} );

			deferred.resolve( {} );
		} );

		it( 'dispatches a skipImage action on failure', done => {
			var suggestions = fixtures[ 0 ].suggestions,
				deferred = $.Deferred(),
				promise = deferred.promise();

			suggestions[ 0 ].confirmed = true;

			mockApi.postWithToken.mockReturnValue( promise );

			Object.defineProperty( context.getters, 'currentImageSuggestions', {
				get: jest.fn().mockReturnValue( suggestions )
			} );

			Object.defineProperty( context.getters, 'currentImageTitle', {
				get: jest.fn().mockReturnValue( 'Test' )
			} );

			Object.defineProperty( context.getters, 'currentImageNonDisplayableSuggestions', {
				get: jest.fn().mockReturnValue( [] )
			} );

			actions.publishTags( context );

			promise.always( () => {
				expect( context.dispatch ).toHaveBeenCalledWith( 'skipImage' );
				done();
			} );

			deferred.reject( {} );
		} );

		it( 'commits a decrementUnreviewedCount mutation on success if current tab is user', done => {
			var suggestions = fixtures[ 0 ].suggestions,
				deferred = $.Deferred(),
				promise = deferred.promise();

			context.state.currentTab = 'user';
			suggestions[ 0 ].confirmed = true;

			mockApi.postWithToken.mockReturnValue( promise );

			Object.defineProperty( context.getters, 'currentImageSuggestions', {
				get: jest.fn().mockReturnValue( suggestions )
			} );

			Object.defineProperty( context.getters, 'currentImageTitle', {
				get: jest.fn().mockReturnValue( 'Test' )
			} );

			Object.defineProperty( context.getters, 'currentImageNonDisplayableSuggestions', {
				get: jest.fn().mockReturnValue( [] )
			} );

			actions.publishTags( context );

			promise.always( () => {
				expect( context.commit ).toHaveBeenCalledWith( 'decrementUnreviewedCount' );
				done();
			} );

			deferred.resolve( {} );
		} );

		it( 'does not commit a decrementUnreviewedCount mutation on success if current tab is NOT user', done => {
			var suggestions = fixtures[ 0 ].suggestions,
				deferred = $.Deferred(),
				promise = deferred.promise();

			suggestions[ 0 ].confirmed = true;

			mockApi.postWithToken.mockReturnValue( promise );

			Object.defineProperty( context.getters, 'currentImageSuggestions', {
				get: jest.fn().mockReturnValue( suggestions )
			} );

			Object.defineProperty( context.getters, 'currentImageTitle', {
				get: jest.fn().mockReturnValue( 'Test' )
			} );

			Object.defineProperty( context.getters, 'currentImageNonDisplayableSuggestions', {
				get: jest.fn().mockReturnValue( [] )
			} );

			actions.publishTags( context );

			promise.always( () => {
				expect( context.commit ).not.toHaveBeenCalledWith( 'decrementUnreviewedCount' );
				done();
			} );

			deferred.resolve( {} );
		} );
	} );

	describe( 'setDepictsStatements', () => {
		it( 'makes a wbsetclaim POST request for each confirmed tag provided in the payload', done => {
			var tags = fixtures[ 0 ].suggestions,
				deferred = $.Deferred(),
				promise = deferred.promise();

			mockApi.postWithToken.mockReturnValue( promise );

			actions.setDepictsStatements( context, tags ).then( () => {
				expect( mockApi.postWithToken ).toHaveBeenCalledTimes( tags.length );
				done();
			} );

			deferred.resolve( {} );
		} );

		it( 'Assigns the correct edit tag for machine-suggested labels', done => {
			var tags = fixtures[ 0 ].suggestions,
				deferred = $.Deferred(),
				promise = deferred.promise();

			mockApi.postWithToken.mockReturnValue( promise );

			actions.setDepictsStatements( context, tags ).then( () => {
				expect( mockApi.postWithToken.mock.calls[ 0 ][ 1 ].tags ).toBe( 'computer-aided-tagging' );
				done();
			} );

			deferred.resolve( {} );
		} );

		it( 'Assigns the correct edit tag for user-provided labels', done => {
			var tags = fixtures[ 0 ].suggestions,
				deferred = $.Deferred(),
				promise = deferred.promise();

			tags[ 0 ].custom = true;
			mockApi.postWithToken.mockReturnValue( promise );

			actions.setDepictsStatements( context, tags ).then( () => {
				expect( mockApi.postWithToken.mock.calls[ 0 ][ 1 ].tags ).toBe( 'computer-aided-tagging-manual' );
				done();
			} );

			deferred.resolve( {} );
		} );
	} );

	describe( 'skipImage', () => {
		it( 'commits the removeImage mutation', () => {
			actions.skipImage( context );
			expect( context.commit ).toHaveBeenCalledWith( 'removeImage' );
		} );
	} );

	describe( 'updatePublishPending', () => {
		it( 'commits the setPublishPending mutation with the payload as an argument', () => {
			actions.updatePublishPending( context, true );
			expect( context.commit ).toHaveBeenCalledWith( 'setPublishPending', true );

			actions.updatePublishPending( context, false );
			expect( context.commit ).toHaveBeenCalledWith( 'setPublishPending', false );
		} );
	} );
} );
