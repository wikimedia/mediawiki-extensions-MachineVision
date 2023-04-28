<template>
	<transition v-if="showNotice" name="wbmad-fade-out">
		<mw-message class="wbmad-image-exclusion-notice__message" type="warning">
			<p>{{ $i18n( 'machinevision-image-exclusion-notification' ) }}</p>
			<mw-button
				class="wbmad-image-exclusion-notice__dismiss-button"
				icon="close"
				:frameless="true"
				:invisibletext="true"
				@click="dismiss"
			>
				{{ $i18n( 'machinevision-image-exclusion-notification-dismiss' ) }}
			</mw-button>
		</mw-message>
	</transition>
</template>

<script>
var Button = require( './base/Button.vue' ),
	Message = require( './base/Message.vue' );

// @vue/component
module.exports = exports = {
	components: {
		'mw-button': Button,
		'mw-message': Message
	},

	data: function () {
		return {
			prefKey: 'wbmad-image-exclusion-notice-dismissed',
			dismissed: false
		};
	},

	computed: {
		previouslyDismissed: function () {
			var numVal = Number( mw.user.options.get( this.prefKey ) );
			return Boolean( numVal );
		},

		showNotice: function () {
			return !this.previouslyDismissed && !this.dismissed;
		}
	},

	methods: {
		dismiss: function () {
			new mw.Api().saveOption( this.prefKey, 1 );
			mw.user.options.set( this.prefKey, 1 );
			this.dismissed = true;
		}
	}
};
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';
@import 'mediawiki.mixins';

.wbmad-image-exclusion-notice__message {
	.flex-display();
	align-items: center;

	.mw-message__content {
		.flex-display();
		margin-left: 3em;
	}
}

.wbmad-image-exclusion-notice__dismiss-button {
	margin-left: 32px;

	&:hover {
		background-color: @background-color-transparent;
	}
}

// Transitions
.wbmad-fade-out-leave-active {
	transition: opacity 0.5s;
}

.wbmad-fade-out-leave-to {
	opacity: 0;
}
</style>
