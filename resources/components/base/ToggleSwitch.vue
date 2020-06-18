<template>
	<div
		class="mw-toggle-switch"
		v-on:click="onClick"
		v-on:keyup.enter="onClick"
		v-on:keydown.space.prevent=""
		v-on:keyup.space="onClick"
	>
		<label
			v-bind:id="name"
			class="mw-toggle-switch__label"
			v-bind:class="labelClasses"
		>
			{{ label }}
		</label>

		<div
			class="mw-toggle-switch__toggle"
			v-bind:class="toggleClasses"
			role="checkbox"
			tabindex="0"
			v-bind:aria-disabled="disabledState ? 'true' : 'false'"
			v-bind:aria-checked="onState ? 'true' : 'false'"
			v-bind:aria-labelled-by="name"
		>
			<span class="mw-toggle-switch__toggle__grip"></span>
		</div>
	</div>
</template>

<script>
// @vue/component
module.exports = {
	props: {
		/**
		 * Initial state of the toggle switch.
		 */
		on: {
			type: Boolean
		},

		/**
		 * Initial disabled state.
		 */
		disabled: {
			type: Boolean
		},

		/**
		 * Human-readable label for the switch.
		 */
		label: {
			type: [ String, Object ],
			required: true
		},

		/**
		 * Machine name, used for aria-labelled-by attribute.
		 */
		name: {
			type: String,
			required: true
		}
	},

	data: function () {
		return {
			onState: this.on,
			disabledState: this.disabled
		};
	},

	computed: {
		labelClasses: function () {
			return {
				'mw-toggle-switch__label--disabled': this.disabledState
			};
		},

		toggleClasses: function () {
			return {
				'mw-toggle-switch__toggle--on': this.onState,
				'mw-toggle-switch__toggle--enabled': !this.disabledState,
				'mw-toggle-switch__toggle--disabled': this.disabledState
			};
		}
	},

	methods: {
		onClick: function () {
			if ( this.disabledState ) {
				return;
			}

			this.onState = !this.onState;
			this.$emit( 'click' );
		}
	}
};
</script>

<style lang="less">
@import 'mediawiki.mixins';
@import '../../../lib/wikimedia-ui-base.less';

@toggleSize: 16/14em;
@toggleSpacing: 5/14em;

.mw-toggle-switch__label {
	margin-right: 8px;

	&--disabled {
		color: @color-base--disabled;
	}
}

.mw-toggle-switch__toggle {
	.box-sizing( border-box );
	.transform( translateZ( 0 ) );
	.transition( ~'background-color 250ms, border-color 250ms' );
	background-color: @background-color-framed;
	border: @border-width-base @border-style-base @border-color-base;
	border-radius: 1em;
	display: inline-block;
	height: 2em;
	margin-right: 8px;
	min-height: 26px;
	overflow: hidden;
	position: relative;
	vertical-align: middle;
	width: 3.5em;

	&:before {
		.transition( border-color 250ms );
		border: 1px solid transparent;
		border-radius: 1em;
		bottom: 1px;
		content: '';
		display: block;
		left: 1px;
		position: absolute;
		right: 1px;
		top: 1px;
		z-index: 1;
	}

	&:focus {
		outline: 0;
	}

	&--enabled {
		cursor: pointer;

		&:focus {
			box-shadow: @box-shadow-base--focus;

			&:before {
				border-color: @background-color-base;
			}
		}
	}

	&:last-child {
		margin-right: 0;
	}

	&__grip {
		.box-sizing( border-box );
		.transition( ~'background-color 250ms, left 100ms, margin-left 100ms' );
		background-color: @background-color-framed;
		border: @border-base;
		border-radius: @toggleSize;
		display: block;
		height: @toggleSize;
		left: @toggleSpacing;
		min-height: 16px;
		min-width: 16px;
		position: absolute;
		top: @toggleSpacing;
		width: @toggleSize;
	}

	&--on {
		background-color: @color-primary;
		border-color: @color-primary;

		.mw-toggle-switch__toggle__grip {
			background-color: @background-color-base;
			border-color: @background-color-base;
			box-shadow: 0 0 0 1px rgba( 0, 0, 0, 0.1 );
			left: 1.9em;
			margin-left: -2px;
		}
	}

	&--disabled {
		background-color: @background-color-filled--disabled;
		border-color: @background-color-filled--disabled;

		.mw-toggle-switch__toggle__grip {
			background-color: transparent;
			border: @border-width-base @border-style-base @background-color-base;
			box-shadow: @box-shadow-inset--inverted;
		}

		&.mw-toggle-switch__toggle--on {
			.mw-toggle-switch__toggle__grip {
				background-color: @background-color-framed;
			}
		}
	}
}
</style>
