<template>
	<div class="wbmad-image-with-suggestions">
		<wbmad-spinner v-if="publishPending" />

		<div class="wbmad-image-with-suggestions__container"
			v-bind:class="containerClasses">
			<div class="wbmad-image-with-suggestions__image">
				<div class="wbmad-image-with-suggestions__image-wrapper">
					<a v-bind:href="descriptionUrl" target="_blank">
						<img v-bind:src="thumbUrl" alt="">
					</a>
				</div>
			</div>

			<div class="wbmad-image-with-suggestions__content">
				<div class="wbmad-image-with-suggestions__header">
					<div class="wbmad-image-with-suggestions__header__title">
						<label class="wbmad-image-with-suggestions__title-label">
							<a v-bind:href="descriptionUrl" target="_blank">
								{{ title }}
							</a>
						</label>

						<wbmad-categories-list />
					</div>

					<div class="wbmad-image-with-suggestions__header__toggle">
						<mw-toggle-switch
							name="wbmad-toggle-tag-details"
							v-bind:label="$i18n( 'machinevision-detailed-tags-toggle-label' )"
							v-bind:on="tagDetailsExpanded"
							v-on:click="toggleTagDetails"
						/>
					</div>
				</div>

				<wbmad-suggestions-group v-on:custom-tag-button-click="launchCustomTagDialog" />

				<div class="wbmad-action-buttons">
					<mw-button
						class="wbmad-action-buttons__publish"
						v-bind:primary="true"
						v-bind:progressive="true"
						v-bind:disabled="publishDisabled"
						v-bind:aria-label="$i18n( 'machinevision-publish-title' )"
						v-on:click="onPublish"
					>
						<span v-i18n-html:machinevision-publish />
					</mw-button>
					<mw-button
						class="wbmad-action-buttons__skip"
						v-bind:frameless="true"
						v-bind:disabled="skipDisabled"
						v-bind:aria-label="$i18n( 'machinevision-skip-title', title ).parse()"
						v-on:click="onSkip"
					>
						<span v-i18n-html:machinevision-skip />
					</mw-button>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
var mapActions = require( 'vuex' ).mapActions,
	mapGetters = require( 'vuex' ).mapGetters,
	mapState = require( 'vuex' ).mapState,
	Spinner = require( './Spinner.vue' ),
	Button = require( './base/Button.vue' ),
	ToggleSwitch = require( './base/ToggleSwitch.vue' ),
	SuggestionsGroup = require( './SuggestionsGroup.vue' ),
	CategoriesList = require( './CategoriesList.vue' ),
	ConfirmTagsDialog = require( './../widgets/ConfirmTagsDialog.js' ),
	AddCustomTagDialog = require( '../widgets/AddCustomTagDialog.js' );

/**
 * ImageCard component
 *
 * Represents a single image in a queue and the various elements needed to add
 * tags to it: publish/skip buttons, image title and categories, and the
 * toggle-able tags themselves, all wrapped in a "card" style UI.
 *
 * This component plays a central role in the overall application, and many
 * crucial actions (like publishing, adding custom tags, etc) will be initiated
 * from here. Since publish actions are asynchronous API calls, this component
 * also manages a spinner component to indicate pending state to the user.
 *
 * Like the App component, this component is also responsible for managing some
 * modal dialogs which still rely on OOUI ProcessDialog and WindowManager
 * widgets. One modal dialog handles the process by which custom tags are added;
 * the other is a confirmation dialog that must be approved before the tags can
 * actually be published. The WindowManager is set up in the mounted hook.
 *
 * A note about data-flow. Users will be confirming individual suggestions
 * here, which toggles the "confirmed" state of the data in Vuex. Rather than
 * directly mutating some kind of local state in the child Suggestion
 * components (which receive all necessary data as props from ImageCard),
 * Suggestions emit clidk events which are handled here (this dispatches an
 * action which ultimately changes the data in the Vuex store). Passing props
 * down to a child component and manipulating them there is considered an
 * anti-pattern in Vue.js; child components should only communicate with
 * parents via emitting events or using Vuex. See here for more information:
 * https://vuejs.org/v2/style-guide/#Implicit-parent-child-communication-use-with-caution
 */

// @vue/component
module.exports = {
	name: 'ImageCard',

	components: {
		'mw-button': Button,
		'mw-toggle-switch': ToggleSwitch,
		'wbmad-spinner': Spinner,
		'wbmad-suggestions-group': SuggestionsGroup,
		'wbmad-categories-list': CategoriesList
	},

	computed: $.extend( {}, mapState( [
		'tagDetailsExpanded'
	] ), mapGetters( [
		'currentImage',
		'currentImageSuggestions',
		'currentImageTitle'
	] ), mapState( [
		'publishPending'
	] ), {
		/**
		 * @return {string}
		 */
		title: function () {
			return this.currentImageTitle;
		},

		/**
		 * @return {string}
		 */
		thumbUrl: function () {
			return this.currentImage.thumburl;
		},

		/**
		 * @return {string}
		 */
		descriptionUrl: function () {
			return this.currentImage.descriptionurl;
		},

		/**
		 * @return {boolean}
		 */
		hasCategories: function () {
			return this.categories.length > 0;
		},

		/**
		 * @return {Array}
		 */
		categories: function () {
			return this.currentImage.categories;
		},

		/**
		 * Whether or not the publish button should be disabled.
		 * @return {boolean}
		 */
		publishDisabled: function () {
			return this.confirmedSuggestions.length < 1 || this.publishPending;
		},

		/**
		 * Whether or not the skip button should be disabled.
		 * @return {boolean}
		 */
		skipDisabled: function () {
			return this.publishPending;
		},

		/**
		 * @return {Array} Array of suggestion objects
		 */
		confirmedSuggestions: function () {
			return this.currentImageSuggestions.filter( function ( suggestion ) {
				return suggestion.confirmed;
			} );
		},

		/**
		 * Return an array of the wikidata IDs of existing suggestions.
		 * @return {Object}
		 */
		wikidataIds: function () {
			return this.currentImageSuggestions.map( function ( suggestion ) {
				return suggestion.wikidataId;
			} );
		},

		/**
		 * @return {Object}
		 */
		containerClasses: function () {
			return {
				'wbmad-spinner-active': this.publishPending
			};
		}
	} ),

	methods: $.extend( {}, mapActions( [
		'publishTags',
		'skipImage',
		'addCustomTag',
		'toggleTagDetails'
	] ), {
		/**
		 * Launch the confirmation modal (OOUI modal). If confirmed, runs the
		 * onFinalConfirm method.
		 */
		onPublish: function () {
			this.confirmTagsDialog = new ConfirmTagsDialog( {
				tagsList: this.confirmedSuggestions.map( function ( tag ) {
					return tag.text;
				} ).join( ', ' ),
				imgUrl: this.thumbUrl,
				imgTitle: this.imgTitle
			} ).connect( this, { confirm: 'onFinalConfirm' } );

			this.$logEvent( {
				action: 'publish',
				// eslint-disable-next-line camelcase
				approved_count: this.confirmedSuggestions.length
			} );

			this.windowManager.addWindows( [ this.confirmTagsDialog ] );
			this.windowManager.openWindow( this.confirmTagsDialog );
		},

		/**
		 * Log an event and dispatch publishTags action. Vuex handles the rest
		 * (all the necessary data is already in the store).
		 */
		onFinalConfirm: function () {
			this.$logEvent( {
				action: 'confirm',
				// eslint-disable-next-line camelcase
				approved_count: this.confirmedSuggestions.length
			} );
			this.publishTags();
		},

		/**
		 * Skip the image (remove it from the Vuex queue).
		 */
		onSkip: function () {
			this.$logEvent( { action: 'skip' } );
			this.skipImage();
		},

		/**
		 * Launch the "add custom tag" dialog (OOUI modal)
		 */
		launchCustomTagDialog: function () {
			// Set filter on EntityAutocompleteInputWidget to remove existing
			// suggestions from autocomplete results.
			this.addCustomTagDialog.setFilter( this.wikidataIds );
			this.windowManager.openWindow( this.addCustomTagDialog );
		}
	} ),

	/**
	 * We are still relying on OOUI for modals, so we need to set up the
	 * WindowManager and dialog widgets when the component mounts.
	 * Once MediaWiki exposes an appropriate DOM element for vue-based modals
	 * to target, we can rewrite this functionality in Vue. This is an
	 * exception to the general rule of "don't manipulate the DOM directly from
	 * Vue".
	 */
	mounted: function () {
		this.windowManager = new OO.ui.WindowManager();
		this.addCustomTagDialog = new AddCustomTagDialog().connect( this, {
			addCustomTag: 'addCustomTag'
		} );

		$( document.body ).append( this.windowManager.$element );
		this.windowManager.addWindows( [ this.addCustomTagDialog ] );
	}
};
</script>

<style lang="less">
@import 'mediawiki.mixins';
@import '../style-variables.less';

.wbmad-image-with-suggestions {
	position: relative;

	&__container {
		.box-shadow(0 1px 4px rgba( 0, 0, 0, 0.25 ));
		border-radius: @wbmad-border-radius-image-card;

		&.wbmad-spinner-active {
			opacity: 0.5;
		}
	}

	&__image {
		.flex-display();
		background-color: @wbmad-background-color-image-card;
		border-radius: @wbmad-border-radius-image-card @wbmad-border-radius-image-card 0 0;
		justify-content: center;
		// Ensure image doesn't overflow border radius.
		overflow: hidden;

		.wbmad-image-with-suggestions__image-wrapper {
			max-width: 100%;

			@media screen and ( min-width: @width-breakpoint-tablet ) {
				max-width: 800px;
			}
		}

		img {
			display: block;
			height: auto;
			max-height: 786px;
			max-width: 100%;
			width: auto;

			@media screen and ( min-width: @width-breakpoint-tablet ) {
				max-height: 600px;
			}
		}
	}

	&__content {
		padding: 24px;
	}

	&__title-label {
		display: block;
		font-size: 1.125em;
		font-weight: bold;

		a {
			color: @color-base;
		}
	}
}

.wbmad-image-with-suggestions__header {
	.flex-display();
	.flex-wrap();
	align-items: baseline;
	justify-content: space-between;

	@media screen and ( min-width: @width-breakpoint-tablet ) {
		.flex-wrap( @wrap: nowrap );
	}
}

.wbmad-image-with-suggestions__header__toggle {
	flex-shrink: 0;
	margin: 16px 0 0;

	@media screen and ( min-width: @width-breakpoint-tablet ) {
		margin: 0 0 0 16px;
	}

	.mw-toggle-switch__label {
		font-size: 0.928em;
	}

	.wbmad-spinner {
		background-color: rgba( 255, 255, 255, 0.5 );
		border-radius: @wbmad-border-radius-image-card;
		height: 100%;
		padding: 0;
		position: absolute;
		top: 0;
		width: 100%;
		z-index: 1;
	}
}

.wbmad-action-buttons {
	.flex-display();
	font-size: 1.15em;
	font-weight: 600;

	&__publish {
		border-radius: 4px;
		overflow: hidden;
		margin-right: 12px;
		padding-left: 16px;
		padding-right: 16px;
	}

	&__skip {
		margin-left: auto;
	}
}
</style>
