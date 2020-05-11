<template>
	<div
		class="mw-suggestion"
		v-bind:class="classObject"
		role="checkbox"
		tabindex="0"
		v-bind:aria-checked="confirmed ? 'true' : 'false'"
		v-on:click="$emit( 'click' )"
		v-on:keyup.enter="$emit( 'click' )"
		v-on:keydown.space.prevent="$emit( 'click' )"
	>
		<label class="mw-suggestion__label">
			{{ text }}
		</label>
		<icon
			icon="check"
			v-bind:title="iconText"
			v-bind:label="iconText"
		/>
	</div>
</template>

<script>
var Icon = require( './Icon.vue' );

/**
 * Basically a button with an unconfirmed and a confirmed state.
 *
 * See ImageCard for usage example.
 */
// @vue/component
module.exports = {
	name: 'Suggestion',

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
		classObject: function () {
			return {
				'mw-suggestion--confirmed': this.confirmed
			};
		}
	}
};
</script>

<style lang="less">
@import 'mediawiki.mixins';
@import '../../../lib/wikimedia-ui-base.less';

.mw-suggestion {
	.transition( ~'background-color 100ms, color 100ms, border-color 100ms, box-shadow 100ms' );
	background-color: @background-color-framed;
	border: @border-base;
	color: @color-base;
	cursor: pointer;
	// TODO: This is a pretty MachineVision-specific style and should possibly
	// be moved.
	margin: 0 4px 4px 0;
	padding: 4px 1.25em;
	border-radius: 18px;
	white-space: nowrap;

	&:hover,
	&:focus {
		background-color: @background-color-framed--hover;
		color: @color-base--emphasized;
	}

	&:focus {
		border-color: @color-primary--active;
		box-shadow: inset 0 0 0 1px @color-primary--active;
		outline: 0;
	}

	.mw-suggestion__label {
		.transition-transform( 0.2s );
		cursor: pointer;
		display: inline-block;
	}

	// Check icon shown for confirmed suggestions.
	.mw-icon {
		.transition( opacity 0.2s );
		min-height: 0;
		min-width: 0;
		opacity: 0;
		position: absolute;
		right: 0.5em;
		visibility: hidden; // Hide from screen readers.
		width: 0; // Don't take up space yet.
	}

	&--confirmed {
		background-color: @background-color-primary;
		border-color: @color-primary--active;
		color: @color-base--emphasized;
		position: relative;

		&:hover,
		&:focus {
			background-color: @background-color-primary;
		}

		.mw-suggestion__label {
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
