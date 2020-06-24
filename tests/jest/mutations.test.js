'use strict';

const imageFixtures = require( './fixtures/imageData.json' ),
	mutations = require( '../../resources/store/mutations.js' );

describe( 'mutations', () => {
	let state,
		fixtures;

	beforeEach( () => {
		state = {
			images: {
				popular: [],
				user: []
			},

			fetchPending: {
				popular: false,
				user: false
			},

			currentTab: 'popular',
			publishPending: false
		};

		// Create a fresh copy of imageFixtures so any mutations made to the
		// data is reset for each test
		fixtures = [ ...imageFixtures ];
	} );

	describe( 'setTab', () => {
		it( 'sets the current tab state to the value of its payload', () => {
			mutations.setTab( state, 'user' );
			expect( state.currentTab ).toBe( 'user' );
		} );

		it( 'throws an error if desired tab does not exist as a key in state.images', () => {
			expect( () => {
				mutations.setTab( state, 'foo' );
			} ).toThrow();
		} );
	} );

	describe( 'setFetchPending', () => {
		it( 'sets the pending state of the specified queue to the specified value', () => {
			mutations.setFetchPending( state, {
				queue: 'user',
				pending: true
			} );

			expect( state.fetchPending.user ).toBe( true );
		} );

		it( 'sets the pending state of the current tab queue if no queue is provided', () => {
			mutations.setFetchPending( state, { pending: true } );
			expect( state.fetchPending.popular ).toBe( true );
		} );

		it( 'throws an error if desired tab does not exist as a key in state.images', () => {
			expect( () => {
				mutations.setFetchPending( state, {
					queue: 'foo',
					pending: true
				} );
			} ).toThrow();
		} );
	} );

	describe( 'addImage', () => {
		it( 'adds the image object to the specified queue', () => {
			const image = fixtures[ 0 ];
			mutations.addImage( state, { image: image, queue: 'user' } );

			expect( state.images.user.length ).toBe( 1 );
			expect( state.images.user[ 0 ] ).toEqual( image );
		} );

		it( 'defaults to current tab queue if no queue is specified', () => {
			const image = fixtures[ 0 ];
			mutations.addImage( state, { image: image } );

			expect( state.images.popular.length ).toBe( 1 );
			expect( state.images.popular[ 0 ] ).toEqual( image );
		} );

		it( 'throws an error if specified queue does not exist', () => {
			const image = fixtures[ 0 ];

			expect( () => {
				mutations.addImage( state, { image: image, queue: 'foo' } );
			} ).toThrow();
		} );
	} );

	describe( 'removeImage', () => {
		it( 'removes the first image from the queue of the current tab', () => {
			state.images.popular = fixtures;
			expect( state.images.popular.length ).toBe( 4 );

			mutations.removeImage( state );
			expect( state.images.popular.length ).toBe( 3 );

			mutations.removeImage( state );
			expect( state.images.popular.length ).toBe( 2 );
		} );
	} );

	describe( 'clearImages', () => {
		it( 'resets the queue of the current tab to an empty array', () => {
			state.images.popular = fixtures;
			state.images.user = fixtures;

			expect( state.images.popular.length ).toBe( 4 );
			expect( state.images.user.length ).toBe( 4 );

			mutations.clearImages( state );
			expect( state.images.popular ).toEqual( [] );
			expect( state.images.user.length ).toBe( 4 );
		} );

		it( 'resets pending state of the current tab to true', () => {
			state.images.popular = fixtures;
			mutations.clearImages( state );
			expect( state.fetchPending.popular ).toBe( true );
		} );
	} );

	describe( 'toggleSuggestion', () => {
		it( 'finds a suggestion of the first image in the active tab and toggles its state', () => {
			const image = fixtures[ 0 ],
				suggestions = image.suggestions,
				suggestionToToggle = suggestions[ 0 ];

			state.images.popular = [ image ];
			expect( suggestionToToggle.confirmed ).toBe( false );

			mutations.toggleSuggestion( state, suggestionToToggle );
			expect( state.images.popular[ 0 ].suggestions[ 0 ].confirmed ).toBe( true );
			expect( state.images.popular[ 0 ].suggestions[ 0 ].wikidataId ).toEqual( suggestions[ 0 ].wikidataId );
		} );
	} );

	describe( 'setPublishPending', () => {
		it( 'sets the publishPending state to the specified value', () => {
			mutations.setPublishPending( state, true );
			expect( state.publishPending ).toBe( true );
		} );
	} );
} );
