<template>
	<div
		class="mw-toggle-switch"
		@click="onClick"
		@keyup.enter="onClick"
		@keydown.space.prevent=""
		@keyup.space="onClick"
	>
		<label
			:id="name"
			class="mw-toggle-switch__label"
			:class="labelClasses"
		>
			{{ label }}
		</label>

		<div
			class="mw-toggle-switch__toggle"
			:class="toggleClasses"
			role="checkbox"
			tabindex="0"
			:aria-disabled="disabledState ? 'true' : 'false'"
			:aria-checked="onState ? 'true' : 'false'"
			:aria-labelled-by="name"
		>
			<span class="mw-toggle-switch__toggle__grip"></span>
		</div>
	</div>
</template>

<script>
// @vue/component
module.exports = exports = {
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
@import 'mediawiki.skin.variables.less';
@import 'mediawiki.mixins';

@toggleSize: 16/14em;
@toggleSpacing: 5/14em;

.mw-toggle-switch__label {
	margin-right: 8px;

	&--disabled {
		color: @color-disabled;
	}
}

.mw-toggle-switch__toggle {
	box-sizing: border-box;
	transform: translateZ( 0 );
	transition: background-color 250ms, border-color 250ms;
	background-color: @background-color-interactive-subtle;
	border: @border-base;
	border-radius: @border-radius-pill;
	display: inline-block;
	height: 2em;
	margin-right: 8px;
	min-height: 26px;
	overflow: hidden;
	position: relative;
	vertical-align: middle;
	width: 3.5em;

	&::before {
		transition: border-color 250ms;
		border: @border-width-base @border-style-base @border-color-transparent;
		border-radius: @border-radius-pill;
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
		outline: @outline-base--focus;
	}

	&--enabled {
		&:hover {
			cursor: @cursor-base--hover;
		}

		&:focus {
			box-shadow: @box-shadow-inset-small @box-shadow-color-progressive--focus;

			&::before {
				border-color: @border-color-disabled;
			}
		}
	}

	&:last-child {
		margin-right: 0;
	}

	&__grip {
		box-sizing: border-box;
		transition: background-color 250ms, left 100ms, margin-left 100ms;
		background-color: @background-color-interactive-subtle;
		border: @border-base;
		border-radius: @border-radius-circle;
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
		background-color: @background-color-progressive;
		border-color: @border-color-progressive;

		.mw-toggle-switch__toggle__grip {
			background-color: @background-color-base;
			border-color: @border-color-disabled;
			box-shadow: 0 0 0 1px rgba( 0, 0, 0, 0.1 );
			left: 1.9em;
			margin-left: -2px;
		}
	}

	&--disabled {
		background-color: @background-color-disabled;
		border-color: @background-color-disabled;

		.mw-toggle-switch__toggle__grip {
			background-color: @background-color-transparent;
			border: @border-width-base @border-style-base @background-color-base;
			box-shadow: @box-shadow-inset-small @box-shadow-color-inverted;
		}

		&.mw-toggle-switch__toggle--on {
			.mw-toggle-switch__toggle__grip {
				background-color: @background-color-interactive-subtle;
			}
		}
	}
}
</style>
