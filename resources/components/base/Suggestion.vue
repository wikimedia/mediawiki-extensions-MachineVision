<template>
	<div
		class="mw-suggestion"
		:class="builtInClasses"
		role="checkbox"
		tabindex="0"
		:aria-checked="confirmed ? 'true' : 'false'"
		@click="$emit( 'click' )"
		@keyup.enter="$emit( 'click' )"
		@keydown.space.prevent="$emit( 'click' )"
	>
		<div class="mw-suggestion__content">
			<slot v-if="hasSlot"></slot>
			<label v-else class="mw-suggestion__label">
				{{ text }}
			</label>
		</div>
		<icon
			class="mw-suggestion__icon"
			icon="check"
			:title="iconText"
			:label="iconText"
		>
		</icon>
	</div>
</template>

<script>
var Icon = require( './Icon.vue' );

/**
 * Basically a button with an unconfirmed and a confirmed state.
 *
 * Text is required since it's used for the icon title and label. If a slot is
 * present it will be displayed; otherwise the text will be displayed as a
 * label.
 *
 * See ImageCard for usage example.
 */
// @vue/component
module.exports = exports = {
	components: {
		icon: Icon
	},

	props: {
		text: {
			type: String,
			required: true
		},

		confirmed: {
			type: Boolean
		}
	},

	data: function () {
		return {
			iconText: this.$i18n( 'machinevision-suggestion-confirm-undo-title', this.text ).parse()
		};
	},

	computed: {
		/**
		 * Conditional classes.
		 *
		 * @return {Object}
		 */
		builtInClasses: function () {
			return {
				'mw-suggestion--confirmed': this.confirmed
			};
		},

		/**
		 * @return {boolean}
		 */
		hasSlot: function () {
			return !!this.$slots.default;
		}
	}
};
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';
@import 'mediawiki.mixins';

.mw-suggestion {
	box-sizing: border-box;
	transition: background-color 100ms, color 100ms, border-color 100ms, box-shadow 100ms;
	background-color: @background-color-interactive-subtle;
	border: @border-base;
	color: @color-base;
	margin: 0 4px 4px 0;
	padding: 4px 1.25em;
	border-radius: 18px;
	white-space: nowrap;

	@media screen and ( min-width: @width-breakpoint-tablet ) {
		margin: 0 8px 8px 0;
	}

	&:hover,
	label:hover {
		cursor: @cursor-base--hover;
	}

	&:hover,
	&:focus {
		background-color: @background-color-base;
		color: @color-emphasized;
	}

	&:focus {
		border-color: @border-color-progressive--active;
		box-shadow: @box-shadow-inset-small @box-shadow-color-progressive--active;
		outline: @outline-base--focus;
	}

	.mw-suggestion__content {
		transition: transform 0.2s;
		cursor: pointer;
		display: inline-block;
	}

	// Check icon shown for confirmed suggestions.
	.mw-icon {
		transition: opacity 0.2s;
		min-height: 0;
		min-width: 0;
		opacity: 0;
		position: absolute;
		right: 0.5em;
		visibility: hidden; // Hide from screen readers.
		width: 0; // Don't take up space yet.
	}

	&--confirmed {
		background-color: @background-color-progressive-subtle;
		color: @color-emphasized;
		border-color: @border-color-progressive--active;
		position: relative;

		&:hover,
		&:focus {
			background-color: @background-color-progressive-subtle;
		}

		.mw-suggestion__content {
			transform: translateX( -0.5em );
		}

		.mw-icon {
			opacity: 1;
			visibility: visible;
			width: 1em;
		}
	}
}
</style>
