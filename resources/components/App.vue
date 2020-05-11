<template>
	<wbmad-fade-in>
		<div class="wbmad-suggested-tags-page">
			<template v-if="showToasts">
				<mw-toast-notification
					v-for="message in imageMessages"
					v-bind:key="message.key"
					v-bind:type="message.type"
					v-bind:duration="message.duration"
					v-on:leave="onToastLeave"
				>
					<p>{{ $i18n( message.messageKey ) }}</p>
				</mw-toast-notification>
			</template>

			<!-- Tabs container -->
			<template v-if="showTabs">
				<h2 v-i18n-html:machinevision-machineaidedtagging-tabs-heading
					class="wbmad-suggested-tags-page-tabs-heading" />

				<tabs v-bind:active="currentTab" v-on:tab-change="onTabChange">
					<!-- Popular tab -->
					<tab name="popular" v-bind:title="popularTabTitle">
						<card-stack v-bind:queue="'popular'" />
					</tab>

					<!-- User tab -->
					<tab name="user" v-bind:title="userTabTitle">
						<personal-uploads-count />
						<card-stack v-bind:queue="'user'" />
					</tab>
				</tabs>

				<p v-i18n-html:machinevision-machineaidedtagging-preferences-link
					class="wbmad-suggested-tags-page-preferences-link" />

				<div v-i18n-html:machinevision-machineaidedtagging-license-information
					class="wbmad-suggested-tags-page-license-info" />
			</template>

			<!-- Login message container -->
			<template v-else-if="!isAuthenticated">
				<!-- eslint-disable-next-line vue/no-v-html -->
				<p v-html="loginMessage" />
			</template>

			<template v-else>
				<p v-i18n-html:machinevision-autoconfirmed-message />
			</template>
		</div>
	</wbmad-fade-in>
</template>

<script>
var mapState = require( 'vuex' ).mapState,
	mapGetters = require( 'vuex' ).mapGetters,
	mapActions = require( 'vuex' ).mapActions,
	Tabs = require( './base/Tabs.vue' ),
	Tab = require( './base/Tab.vue' ),
	ToastNotification = require( './base/ToastNotification.vue' ),
	CardStack = require( './CardStack.vue' ),
	PersonalUploadsCount = require( './PersonalUploadsCount.vue' ),
	OnboardingDialog = require( '../widgets/OnboardingDialog.js' ),
	FadeIn = require( './FadeIn.vue' ),
	url = new mw.Uri();

/**
 * App component
 *
 * The top-level component of the MachineVision Vue application. This is where
 * the tabs for different image queues (popular vs user) can be found. It is
 * also where popup "toast" notifications will appear based on success or
 * failure of publish actions. The contents of this component will only be
 * displayed to users who are both logged-in and auto-confirmed; otherwise only
 * a static message will be shown and no other functionality will be available.
 * This component also manages an OOUI onboarding dialog for new users.
 *
 * Much of the important application-wide state lives in Vuex. The app
 * component pays attention to image-specific messages so it can display them
 * in toasts, and it updates the current tab state in Vuex as the user moves
 * between tabs. It is also responsible for firing off the getImages action
 * when it mounts.
 *
 * Finally, this component is responsible for some very basic hash-based
 * routing; users who arrive on the page with a URL hash #popular or #user
 * will see the appropriate tab when the UI loads. As the user changes tabs,
 * the hash will be kept in sync with the current tab state thanks to an event
 * listener added in the mounted() hook. This listener is removed in the
 * beforeDestroy() hook.
 */
module.exports = {
	name: 'MachineVision',

	/**
	 * All child components must be declared here before they can be used in
	 * templates.
	 */
	components: {
		tabs: Tabs,
		tab: Tab,
		'mw-toast-notification': ToastNotification,
		'card-stack': CardStack,
		'personal-uploads-count': PersonalUploadsCount,
		'wbmad-fade-in': FadeIn
	},

	/**
	 * Computed properties are a good way to get logic out of templates. They
	 * automatically update as their dependencies change. Vuex helper functions
	 * like mapState and mapGetters allow a component to treat state and getter
	 * values like internal computed properties (making it easy to directly
	 * reference those values in templates as well).
	 *
	 * More here: https://vuejs.org/v2/guide/computed.html#Computed-Properties
	 */
	computed: $.extend( {}, mapState( [
		'currentTab',
		'imageMessages'
	] ), mapGetters( [
		'isAuthenticated',
		'isAutoconfirmed',
		'tabs'
	] ), {
		/**
		 * Whether or not to display the full UI
		 *
		 * @return {bool}
		 */
		showTabs: function () {
			return this.isAuthenticated && this.isAutoconfirmed;
		},

		/**
		 * Due to limitations of the JS parser, we're parsing this message in
		 * PHP and exporitng it as config.
		 * @return {string}
		 */
		loginMessage: function () {
			return mw.config.get( 'wgMVSuggestedTagsLoginMessage' );
		},

		/**
		 * @return {string}
		 */
		popularTabTitle: function () {
			return this.$i18n( 'machinevision-machineaidedtagging-popular-tab' ).text();
		},

		/**
		 * @return {string}
		 * */
		userTabTitle: function () {
			return this.$i18n( 'machinevision-machineaidedtagging-user-tab' ).text();
		},

		/**
		 * @return {bool}
		 */
		showToasts: function () {
			return this.imageMessages && this.imageMessages.length > 0;
		}
	} ),

	/**
	 * Methods to be mixed into the Vue instance. You can access these methods
	 * directly on the VM instance, or use them in directive expressions. All
	 * methods will have their this context automatically bound to the Vue
	 * instance.
	 */
	methods: $.extend( {}, mapActions( [
		'updateCurrentTab',
		'getImages',
		'hideImageMessage'
	] ), {
		/**
		 * Watch the tab change events emitted by the <Tabs> component
		 * to ensure that Vuex state is kept in sync
		 *
		 * @param {VueComponent} tab
		 */
		onTabChange: function ( tab ) {
			window.history.replaceState( null, null, '#' + tab.name );
			this.updateCurrentTab( tab.name );
		},

		/**
		 * Remove a toast notification that has passed its display duration.
		 *
		 * @param {string} toastKey
		 */
		onToastLeave: function ( toastKey ) {
			this.hideImageMessage( toastKey );
		},

		/**
		 * @param {HashChangeEvent} e
		 */
		onHashChange: function ( e ) {
			var newHash = new URL( e.newURL ).hash,
				newTabName = newHash.substring( 1 );

			if ( this.tabs.indexOf( newTabName ) !== -1 ) {
				this.updateCurrentTab( newTabName );
			}
		},

		/**
		 * Only new users should see the onboarding dialog. All process
		 * dialog/window manager elements are still handled by OOUI (hence the
		 * need to append an element to the DOM – something you don't normally
		 * need to do in Vue.js)
		 */
		showOnboardingDialog: function () {
			var onboardingDialog,
				prefKey = 'wbmad-onboarding-dialog-dismissed',
				windowManager;

			// Don't show if user has dismissed it or if this isn't the user
			// tab. Type coercion is necessary due to limitations of browser
			// localstorage.
			if ( Number( mw.user.options.get( prefKey ) ) === 1 ) {
				return;
			}

			windowManager = new OO.ui.WindowManager();
			onboardingDialog = new OnboardingDialog( { onboardingPrefKey: prefKey } );

			$( document.body ).append( windowManager.$element );
			windowManager.addWindows( [ onboardingDialog ] );
			windowManager.openWindow( onboardingDialog );
		}
	} ),

	/**
	 * An object where keys are expressions to watch and values are the
	 * corresponding callbacks. The value can also be a string of a method
	 * name, or an Object that contains additional options. Typically the
	 * value will be a function which can take (newValue, oldValue) as
	 * arguments.
	 */
	watch: {
		/**
		 * @param {string} newVal name of newly-active tab
		 */
		currentTab: function ( newVal ) {
			if ( this.isAuthenticated && newVal === 'user' ) {
				this.showOnboardingDialog();
			}
		}
	},

	/**
	 * Vue.js exposes a number of "hooks" that are called at different points
	 * in a component's lifecycle:
	 * https://vuejs.org/v2/guide/instance.html#Instance-Lifecycle-Hooks
	 *
	 * The mounted hook is called once the component has been mounted into the
	 * DOM. It is not called in server-side rendering.
	 *
	 * Once this component is mounted, it parses the URL to determine which tab
	 * to show first and fires off API requests to get images in both queues
	 * (the actual API request code lives in Vuex). Then it sets up an event
	 * listener to listen for further hash changes.
	 */
	mounted: function () {
		// If there's a URL fragment and it's one of the tabs, select that tab.
		// Otherwise, default to "popular" add a fragement to the URL.
		var urlFragment = url.fragment,
			hash = ( urlFragment && this.tabs.indexOf( urlFragment ) !== -1 ) ?
				urlFragment :
				this.tabs[ 0 ];

		this.tabs.forEach( function ( tab ) {
			this.getImages( { queue: tab } );
		}.bind( this ) );

		window.history.replaceState( null, null, '#' + hash );
		this.updateCurrentTab( hash );

		// Listen for hash changes.
		window.addEventListener( 'hashchange', this.onHashChange );
	},

	/**
	 * Another lifecycle hook. Called right before the component is destroyed.
	 * This is a good place to remove event listeners.
	 */
	beforeDestroy: function () {
		window.removeEventListener( 'hashchange', this.onHashChange );
	}
};
</script>

<style lang="less">
@import 'mediawiki.mixins';
@import '../style-variables.less';

.wbmad-suggested-tags-page {
	max-width: @wbmad-size-max-width;

	.wbmad-suggested-tags-page-tabs-heading {
		border: 0;
		font-family: @font-family-sans;
		font-weight: 600;
		margin-top: 20px;
	}

	.mw-tabs__content {
		padding: 24px 4px 16px;
	}

	.wbmad-suggested-tags-page-license-info {
		.box-sizing( border-box );
		background-color: @background-color-framed;
		padding: 16px;

		p {
			margin: 0;
		}
	}

	.wbmad-suggested-tags-page-preferences-link {
		margin: 0 0 16px;
	}
}
</style>
