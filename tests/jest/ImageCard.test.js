'use strict';

const { mount, config } = require( '@vue/test-utils' );
const Vuex = require( 'vuex' );
const ImageCard = require( '../../resources/components/ImageCard.vue' );
const imageFixtures = require( './fixtures/imageData.json' );

describe( 'ImageCard', () => {
	let state,
		getters,
		actions,
		store;

	beforeEach( () => {
		state = {
			images: {
				popular: imageFixtures,
				user: []
			}
		};

		getters = {
			currentImage: jest.fn(),
			currentImageTitle: jest.fn(),
			currentImageSuggestions: jest.fn()
		};

		actions = {
			publishTags: jest.fn(),
			skipImage: jest.fn()
		};

		store = new Vuex.Store( {
			state,
			getters,
			actions
		} );

	} );

	it( 'publish button is disabled when no suggestions are confirmed', () => {
		getters.currentImage.mockReturnValue( imageFixtures[ 0 ] );
		getters.currentImageSuggestions.mockReturnValue( imageFixtures[ 0 ].suggestions );

		const wrapper = mount( ImageCard, { global: { plugins: [ store ] } } );
		const publishButton = wrapper.find( '.wbmad-action-buttons__publish' );

		expect( publishButton.attributes( 'disabled' ) ).toBeDefined();
		expect( actions.publishTags ).not.toHaveBeenCalled();

		publishButton.trigger( 'click' );
		expect( actions.publishTags ).not.toHaveBeenCalled();
	} );

	it( 'publish button is enabled when at least one suggestion is confirmed', () => {
		const unconfirmedSuggestion = imageFixtures[ 0 ].suggestions[ 0 ];
		const confirmedSuggestion = imageFixtures[ 0 ].suggestions[ 1 ];
		confirmedSuggestion.confirmed = true;

		getters.currentImage.mockReturnValue( imageFixtures[ 0 ] );
		getters.currentImageSuggestions.mockReturnValue( [
			unconfirmedSuggestion,
			confirmedSuggestion
		] );

		const wrapper = mount( ImageCard, { global: { plugins: [ store ] } } );
		const publishButton = wrapper.find( '.wbmad-action-buttons__publish' );

		expect( publishButton.attributes( 'disabled' ) ).not.toBeDefined();
	} );

	it( 'dispatches the publish action when the publish button is clicked', () => {
		const unconfirmedSuggestion = imageFixtures[ 0 ].suggestions[ 0 ];
		const confirmedSuggestion = imageFixtures[ 0 ].suggestions[ 1 ];
		confirmedSuggestion.confirmed = true;

		getters.currentImage.mockReturnValue( imageFixtures[ 0 ] );
		getters.currentImageSuggestions.mockReturnValue( [
			unconfirmedSuggestion,
			confirmedSuggestion
		] );

		const wrapper = mount( ImageCard, { global: { plugins: [ store ] } } );
		const publishButton = wrapper.find( '.wbmad-action-buttons__publish' );
		expect( actions.publishTags ).not.toHaveBeenCalled();

		publishButton.trigger( 'click' );
		wrapper.vm.confirmTagsDialog.emit( 'confirm' );
		expect( actions.publishTags ).toHaveBeenCalled();
	} );

	it( 'dispatches the skipImage action when the skip button is clicked', () => {
		getters.currentImage.mockReturnValue( imageFixtures[ 0 ] );
		getters.currentImageSuggestions.mockReturnValue( imageFixtures[ 0 ].suggestions );

		const wrapper = mount( ImageCard, { global: { plugins: [ store ] } } );
		const skipButton = wrapper.find( '.wbmad-action-buttons__skip' );

		expect( actions.skipImage ).not.toHaveBeenCalled();

		skipButton.trigger( 'click' );
		expect( actions.skipImage ).toHaveBeenCalled();
	} );

	it( 'logs a "publish" event when the publish button is clicked', () => {
		const unconfirmedSuggestion = imageFixtures[ 0 ].suggestions[ 0 ];
		const confirmedSuggestion = imageFixtures[ 0 ].suggestions[ 1 ];
		confirmedSuggestion.confirmed = true;

		getters.currentImage.mockReturnValue( imageFixtures[ 0 ] );
		getters.currentImageSuggestions.mockReturnValue( [
			unconfirmedSuggestion,
			confirmedSuggestion
		] );

		const wrapper = mount( ImageCard, { global: { plugins: [ store ] } } );
		const publishButton = wrapper.find( '.wbmad-action-buttons__publish' );

		publishButton.trigger( 'click' );
		expect( config.global.mocks.$logEvent ).toHaveBeenCalledWith(
			expect.objectContaining( { action: 'publish' } )
		);
	} );

	it( 'logs a "skip" event when the  skip button is clicked', () => {
		getters.currentImage.mockReturnValue( imageFixtures[ 0 ] );
		getters.currentImageSuggestions.mockReturnValue( imageFixtures[ 0 ].suggestions );

		const wrapper = mount( ImageCard, { global: { plugins: [ store ] } } );
		const skipButton = wrapper.find( '.wbmad-action-buttons__skip' );

		expect( actions.skipImage ).not.toHaveBeenCalled();

		skipButton.trigger( 'click' );
		expect( config.global.mocks.$logEvent ).toHaveBeenCalledWith(
			expect.objectContaining( { action: 'skip' } )
		);
	} );
} );
