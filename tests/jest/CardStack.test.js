'use strict';

const { shallowMount } = require( '@vue/test-utils' );
const Vuex = require( 'vuex' );
const CardStack = require( '../../resources/components/CardStack.vue' );
const ImageCard = require( '../../resources/components/ImageCard.vue' );
const imageData = require( './fixtures/imageData.json' );

describe( 'CardStack', () => {
	let state,
		mutations,
		getters,
		actions,
		store;

	beforeEach( () => {
		state = {
			currentTab: 'popular',
			images: {
				popular: imageData, // contains 4 images
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

		mutations = {
			removeImage: function ( tabState ) {
				tabState.images[ tabState.currentTab ].shift();
			}
		};

		getters = {
			currentImage: jest.fn()
		};

		actions = {
			getImages: jest.fn()
		};

		store = Vuex.createStore( {
			state,
			mutations,
			getters,
			actions
		} );
	} );

	it( 'does not render the ImageCard component when there are no images in the queue', () => {
		const wrapper = shallowMount( CardStack, {
			props: {
				queue: 'user'
			},
			global: {
				plugins: [ store ]
			}
		} );

		const imageCard = wrapper.findComponent( ImageCard );
		expect( imageCard.exists() ).toBe( false );
	} );

	it( 'renders the ImageCard component when there are images in the queue', () => {
		getters.currentImage.mockReturnValue( imageData[ 0 ] );

		const wrapper = shallowMount( CardStack, {
			props: {
				queue: 'popular'
			},
			global: {
				plugins: [ store ]
			}
		} );

		const imageCard = wrapper.findComponent( ImageCard );
		expect( imageCard.exists() ).toBe( true );
	} );

	it( 'dispatches the getImages action when the count of the image queue reaches zero', () => {
		const wrapper = shallowMount( CardStack, {
			props: {
				queue: 'popular'
			},
			global: {
				plugins: [ store ]
			}
		} );

		expect( actions.getImages ).not.toHaveBeenCalled();

		wrapper.vm.$options.watch.imagesInQueue.call( wrapper.vm, [ 'old' ], [] );

		expect( actions.getImages ).toHaveBeenCalled();
	} );
} );
