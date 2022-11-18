'use strict';

const { config, mount, shallowMount } = require( '@vue/test-utils' );
const Vuex = require( 'vuex' );
const App = require( '../../resources/components/App.vue' );

describe( 'App', () => {
	let state,
		getters,
		actions,
		store,
		computed;

	// Mock Vuex store and i18n-based computed props for testing
	beforeEach( () => {
		state = {
			currentTab: 'popular',
			images: {
				popular: [],
				user: []
			},
			fetchPending: {
				popular: false,
				user: false
			},
			fetchError: {
				popular: false,
				user: false
			},
			userStats: {
				total: 20,
				unreviewed: 10
			}
		};

		getters = {
			isAuthenticated: jest.fn(),
			isAutoconfirmed: jest.fn(),
			currentImage: jest.fn(), // needed for tests that use deep mounting
			tabs: function ( tabsState ) {
				return Object.keys( tabsState.images );
			}
		};

		actions = {
			getImages: jest.fn(),
			updateCurrentTab: jest.fn()
		};

		store = new Vuex.Store( {
			state,
			getters,
			actions
		} );

		// Title is a required prop; fake a simple string at the computed prop
		// level instead of faking out the i18n plugin here; we just need to
		// provide string values
		computed = {
			popularTabTitle() {
				return 'popular';
			},
			userTabTitle() {
				return 'user';
			}
		};

		config.global.computed = computed;
	} );

	it( 'does not display if user is not logged in', () => {
		getters.isAuthenticated.mockReturnValue( false );
		getters.isAutoconfirmed.mockReturnValue( false );

		const wrapper = shallowMount( App, { global: { plugins: [ store ] } } );
		const tabsHeading = wrapper.find( '.wbmad-suggested-tags-page-tabs-heading' );
		expect( tabsHeading.exists() ).toBe( false );
	} );

	it( 'does not display if user is not autoconfirmed', () => {
		getters.isAuthenticated.mockReturnValue( true );
		getters.isAutoconfirmed.mockReturnValue( false );

		const wrapper = shallowMount( App, { global: { plugins: [ store ] } } );
		const tabsHeading = wrapper.find( '.wbmad-suggested-tags-page-tabs-heading' );
		expect( tabsHeading.exists() ).toBe( false );
	} );

	it.only( 'displays if user is both authenticated and autoconfirmed', () => {
		getters.isAuthenticated.mockReturnValue( true );
		getters.isAutoconfirmed.mockReturnValue( true );

		const wrapper = mount( App, { global: { plugins: [ store ] } } );

		const tabsHeading = wrapper.find( '.wbmad-suggested-tags-page-tabs-heading' );
		expect( tabsHeading.exists() ).toBe( true );
	} );

	it( 'dispatches the updateCurrentTab action when the onTabChange method is called', () => {
		getters.isAuthenticated.mockReturnValue( true );
		getters.isAutoconfirmed.mockReturnValue( true );

		// Use mount to test events emitted from child components
		const wrapper = shallowMount( App, { global: { plugins: [ store ] } } );

		// We emulate the click of a tab
		wrapper.vm.onTabChange( {
			name: 'user'
		} );

		// Expect the updateCurrentTab action to be dispatched
		// (The first time it was called was on mount)
		expect( actions.updateCurrentTab.mock.calls.length ).toBe( 2 );

		// Expect the call to updateCurrentTab to contain the correct payload
		expect( actions.updateCurrentTab.mock.calls[ 1 ][ 1 ] ).toBe( 'user' );
	} );

	it( 'dispatches a getImages action for each queue when mounted', () => {
		getters.isAuthenticated.mockReturnValue( true );
		getters.isAutoconfirmed.mockReturnValue( true );

		// Expect the getImages action to be dispatched when component is mounted
		expect( actions.getImages.mock.calls.length ).toBe( 0 );
		shallowMount( App, { global: { plugins: [ store ] } } );
		expect( actions.getImages.mock.calls.length ).toBe( 2 );
	} );
} );
