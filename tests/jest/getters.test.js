'use strict';

const getters = require( '../../resources/store/getters.js' ),
	imageFixtures = require( './fixtures/imageData.json' );

describe( 'getters', () => {
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

	describe( 'tabs', () => {
		it( 'returns the keys of the state.images object', () => {
			expect( getters.tabs( state ) ).toEqual( Object.keys( state.images ) );
		} );
	} );

	describe( 'currentImage', () => {
		it( 'returns the first image of queue corresponding to the current tab', () => {
			state.images.popular = fixtures;
			expect( getters.currentImage( state ) ).toEqual( fixtures[ 0 ] );
		} );
	} );

	describe( 'currentImageTitle', () => {
		it( 'generates a title by calling mw.Title.newFromText', () => {
			// the currentImageTitle getter makes use of the global mw object's
			// Title and config methods, so we need to mock them here:
			const mockGetters = { currentImage: { title: 'File:Test.jpg' } },
				mockMwTitle = global.mw.Title,
				mockMwConfig = global.mw.config;

			mockMwConfig.get.mockReturnValue( { file: '' } );

			getters.currentImageTitle( {}, mockGetters );
			expect( mockMwTitle.newFromText ).toHaveBeenCalledWith( 'File:Test.jpg' );
		} );
	} );

	describe( 'currentImageMediaInfoId', () => {
		it( 'returns the MediaInfoId of the current image by prefixing pageId with "M"', () => {
			const mockGetters = {
				currentImage: {
					pageid: 123
				}
			};

			expect( getters.currentImageMediaInfoId( {}, mockGetters ) ).toBe( 'M123' );
		} );
	} );

	describe( 'currentImageSuggestions', () => {
		it( 'returns the suggestions array of the current image', () => {
			const suggestions = fixtures[ 0 ].suggestions,
				mockGetters = {
					currentImage: {
						suggestions: fixtures[ 0 ].suggestions
					}
				};

			expect( getters.currentImageSuggestions( {}, mockGetters ) ).toEqual( suggestions );
		} );

		it( 'filters out any suggestions that do not contain text', () => {
			const goodSuggestions = fixtures[ 0 ].suggestions,
				badSuggestion = { wikidataId: 'Q123', confirmed: false, foo: 'bar' },
				allSuggestions = [ ...goodSuggestions, badSuggestion ],
				mockGetters = {
					currentImage: {
						suggestions: allSuggestions
					}
				};

			expect( getters.currentImageSuggestions( {}, mockGetters ) ).toEqual( goodSuggestions );
		} );
	} );
} );
