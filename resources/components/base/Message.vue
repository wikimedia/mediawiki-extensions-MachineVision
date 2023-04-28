<template>
	<div
		class="mw-message"
		:class="builtInClasses"
		:aria-live="type !== 'error' ? 'polite' : false"
		:role="type === 'error' ? 'alert' : false "
	>
		<icon
			:icon="icon"
			:class="iconClass"
		>
		</icon>
		<div class="mw-message__content">
			<slot></slot>
		</div>
	</div>
</template>

<script>
var Icon = require( './Icon.vue' ),
	ICON_MAP = {
		notice: 'infoFilled',
		error: 'error',
		warning: 'alert',
		success: 'check'
	};

/**
 * User-facing message with icon.
 *
 * See CardStack for usage example.
 */
// @vue/component
module.exports = exports = {

	components: {
		icon: Icon
	},

	props: {
		// Should be one of notice, warning, error, or success.
		type: {
			type: String,
			default: 'notice'
		},
		inline: {
			type: Boolean
		}
	},

	computed: {
		typeClass: function () {
			return 'mw-message--' + this.type;
		},
		builtInClasses: function () {
			var classes = { 'mw-message--block': !this.inline };
			classes[ this.typeClass ] = true;
			return classes;
		},
		icon: function () {
			return ICON_MAP[ this.type ];
		},
		iconClass: function () {
			return 'oo-ui-image-' + this.type;
		}
	}
};
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';
@import 'mediawiki.mixins';

.mw-message {
	color: @color-notice;
	font-weight: @font-weight-bold;
	max-width: 50em;
	position: relative;

	&--error {
		color: @color-error;
	}

	&--success {
		color: @color-success;
	}

	&--block {
		color: @color-notice;
		border-width: @border-width-base;
		border-style: @border-style-base;
		padding: 16px 24px;
		font-weight: @font-weight-normal;

		&.mw-message--notice {
			background-color: @background-color-notice-subtle;
			border-color: @border-color-notice;
		}

		&.mw-message--error {
			background-color: @background-color-error-subtle;
			border-color: @border-color-error;
		}

		&.mw-message--warning {
			background-color: @background-color-warning-subtle;
			border-color: @border-color-warning;
		}

		&.mw-message--success {
			background-color: @background-color-success-subtle;
			border-color: @border-color-success;
		}
	}

	.mw-icon {
		position: absolute;
	}
}

.mw-message__content {
	margin-left: 2em;
}
</style>
