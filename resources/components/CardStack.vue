<template>
	<div class="wbmad-suggested-tags-cardstack">
		<wbmad-cardstack-placeholder v-if="isPending" />

		<wbmad-fade-in v-else-if="isError">
			<mw-message class="wbmad-cardstack-message" type="error">
				<p v-i18n-html:machinevision-failure-message />
			</mw-message>
		</wbmad-fade-in>

		<transition v-else-if="shouldDisplayImage"
			name="wbmad-fade"
			appear
		>
			<wbmad-image-card v-bind:key="currentImageId" />
		</transition>

		<wbmad-user-message v-else-if="showUserCta"
			class="wbmad-user-cta"
			v-bind:heading="$i18n( 'machinevision-cta-heading' )"
			v-bind:text="$i18n( 'machinevision-cta-text' )"
			v-bind:cta="$i18n( 'machinevision-cta-cta' )"
			v-on:cta-click="goToPopularTab"
		/>

		<wbmad-user-message v-else-if="showUserCtaNoLabeledUploads"
			class="wbmad-user-cta--no-uploads"
			v-bind:heading="$i18n( 'machinevision-no-uploads-cta-heading' )"
			v-bind:text="$i18n( 'machinevision-no-uploads-cta-text' )"
			v-bind:cta="$i18n( 'machinevision-cta-cta' )"
			v-on:cta-click="goToPopularTab"
		/>

		<wbmad-user-message v-else
			class="wbmad-user-cta--generic-no-images"
			v-bind:heading="$i18n( 'machinevision-generic-no-images-heading' )"
			v-bind:text="$i18n( 'machinevision-generic-no-images-text' )"
		/>
	</div>
</template>

<script>
var mapState = require( 'vuex' ).mapState,
	mapGetters = require( 'vuex' ).mapGetters,
	mapActions = require( 'vuex' ).mapActions,
	CardStackPlaceholder = require( './CardStackPlaceholder.vue' ),
	FadeIn = require( './FadeIn.vue' ),
	ImageCard = require( './ImageCard.vue' ),
	UserImage = require( './UserMessage.vue' ),
	Message = require( './base/Message.vue' );

/**
 * Wrapper component for tab content.
 */
// @vue/component
module.exports = {
	name: 'CardStack',

	components: {
		'wbmad-cardstack-placeholder': CardStackPlaceholder,
		'wbmad-fade-in': FadeIn,
		'wbmad-image-card': ImageCard,
		'wbmad-user-message': UserImage,
		'mw-message': Message
	},

	props: {
		queue: {
			type: String,
			required: true
		}
	},

	computed: $.extend( {}, mapState( [
		'currentTab',
		'fetchPending',
		'fetchError',
		'images',
		'userStats'
	] ), mapGetters( [
		'currentImage'
	] ), {
		/**
		 * @return {Array}
		 */
		imagesInQueue: function () {
			return this.images[ this.queue ];
		},

		/**
		 * Pending state is queue-specific
		 * @return {bool}
		 */
		isPending: function () {
			return this.fetchPending[ this.queue ];
		},

		/**
		 * Fetch error state is queue-specific
		 * @return {bool}
		 */
		isError: function () {
			return this.fetchError[ this.queue ];
		},

		/**
		 * Whether to render the ImageCard.
		 * We need a dedicated computed property for this because
		 * ResourceLoader can't handle "&&" in the template.
		 *
		 * @return {boolean}
		 */
		shouldDisplayImage: function () {
			return this.currentTab === this.queue && this.currentImage && this.imagesInQueue;
		},

		/**
		 * @return {boolean}
		 */
		isUserTab: function () {
			return this.queue === 'user';
		},

		/**
		 * Whether or not the user has labeled uploads, which will determine the
		 * message shown to them when they finish tagging personal uploads.
		 * @return {boolean}
		 */
		userHasLabeledUploads: function () {
			return this.userStats.total ? this.userStats.total > 0 : false;
		},

		/**
		 * Whether or not to show a message and CTA based on the user tagging
		 * all of their images.
		 * @return {boolean}
		 */
		showUserCta: function () {
			return this.isUserTab && this.userHasLabeledUploads;
		},

		/**
		 * Whether or not to show a message and CTA based on the user having no
		 * personal uploads, encouraging them to upload some images.
		 * @return {boolean}
		 */
		showUserCtaNoLabeledUploads: function () {
			return this.isUserTab && !this.userHasLabeledUploads;
		},

		/**
		 * We need a unique ID for each image card so the component isn't
		 * reused. Otherwise, transitions won't work.
		 * @return {number}
		 */
		currentImageId: function () {
			return this.currentImage.pageid;
		}
	} ),

	methods: $.extend( {}, mapActions( [
		'getImages',
		'updateCurrentTab'
	] ), {
		goToPopularTab: function () {
			window.history.replaceState( null, null, '#popular' );
			this.updateCurrentTab( 'popular' );
		}
	} ),

	watch: {
		/**
		 * If a queue reaches zero images, attempt to fetch more
		 *
		 * @param {Array} oldVal
		 * @param {Array} newVal
		 */
		imagesInQueue: function ( oldVal, newVal ) {
			if ( newVal.length === 0 ) {
				this.getImages( {
					queue: this.currentTab
				} );
			}
		}
	}
};
</script>

<style lang="less">
.wbmad-user-cta {
	.wbmad-user-message-icon {
		background-image: url( ../icons/empty-state-icon.svg );
	}
}

.wbmad-user-cta--no-uploads,
.wbmad-user-cta--generic-no-images {
	.wbmad-user-message-icon {
		background-image: url( ../icons/empty-state-icon-no-uploads.svg );
	}
}

.wbmad-cardstack-message {
	// Avoid a major layout jump for items below cardstack.
	margin-bottom: 150px;

	p {
		margin: 0;
	}
}

// Transitions.
// Fade in new image.
.wbmad-fade-enter-active,
.wbmad-fade-appear-active {
	transition: opacity 0.5s;
}

.wbmad-fade-enter,
.wbmad-fade-appear {
	opacity: 0;
}

// To avoid a jump in the layout for an instant between images, we can't use
// the out-in mode. Instead, let's hide the old image and remove it from the
// layout flow while the new one fades in.
.wbmad-fade-leave-active {
	opacity: 0;
	position: absolute;
}
</style>
