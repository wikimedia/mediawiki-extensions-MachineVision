const Vue = require( 'vue' );
const VueTestUtils = require( '@vue/test-utils' );
const Vuex = require( 'vuex' );
const CardStack = require( '../../resources/components/CardStack.vue' );
const ImageCard = require( '../../resources/components/ImageCard.vue' );
const i18n = require( './plugins/i18n' );
const imageData = require( './fixtures/imageData.json' );

const localVue = VueTestUtils.createLocalVue();
localVue.use( i18n );
localVue.use( Vuex );

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
			removeImage: function ( state ) {
				state.images[ state.currentTab ].shift();
			}
		};

		getters = {
			currentImage: jest.fn()
		};

		actions = {
			getImages: jest.fn()
		};

		store = new Vuex.Store( {
			state,
			mutations,
			getters,
			actions
		} );
	} );

	it( 'does not render the ImageCard component when there are no images in the queue', () => {
		const wrapper = VueTestUtils.shallowMount( CardStack, {
			propsData: {
				queue: 'user'
			},
			store,
			localVue
		} );

		const imageCard = wrapper.findComponent( ImageCard );
		expect( imageCard.exists() ).toBe( false );
	} );

	it( 'renders the ImageCard component when there are images in the queue', () => {
		getters.currentImage.mockReturnValue( imageData[ 0 ] );

		const wrapper = VueTestUtils.shallowMount( CardStack, {
			propsData: {
				queue: 'popular'
			},
			store,
			localVue
		} );

		const imageCard = wrapper.findComponent( ImageCard );
		expect( imageCard.exists() ).toBe( true );
	} );

	it( 'dispatches the getImages action when the count of the image queue reaches zero', done => {
		VueTestUtils.shallowMount( CardStack, {
			propsData: {
				queue: 'popular'
			},
			store,
			localVue
		} );

		expect( actions.getImages ).not.toHaveBeenCalled();
		store.commit( 'removeImage' );
		store.commit( 'removeImage' );
		store.commit( 'removeImage' );
		store.commit( 'removeImage' );

		Vue.nextTick( () => {
			expect( actions.getImages ).toHaveBeenCalled();
			done();
		} );
	} );
} );
