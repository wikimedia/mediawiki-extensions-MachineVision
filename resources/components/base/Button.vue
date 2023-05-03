<template>
	<button
		class="mw-button"
		:class="builtInClasses"
		:disabled="disabled"
		@click="$emit( 'click' )"
	>
		<icon
			v-if="icon"
			:icon="icon"
			:invert="invert"
		>
		</icon>
		<div class="mw-button__content">
			<slot></slot>
		</div>
	</button>
</template>

<script>
var Icon = require( './Icon.vue' );

/**
 * Button with optional icon.
 *
 * See ImageCard.vue for usage examples.
 */
// @vue/component
module.exports = exports = {
	components: {
		icon: Icon
	},

	props: {
		disabled: {
			type: Boolean
		},

		frameless: {
			type: Boolean
		},

		icon: {
			type: String,
			default: null
		},

		// Set to true to hide text node.
		invisibletext: {
			type: Boolean
		},

		// In OOUI, flags are passed in as an array (or a string or an object)
		// and are handled by a separate mixin. Passing them in individually is
		// a bit more readable and intuitive, plus it makes the code in this
		// component simpler.
		progressive: {
			type: Boolean
		},

		destructive: {
			type: Boolean
		},

		primary: {
			type: Boolean
		}
	},

	computed: {
		builtInClasses: function () {
			return {
				'mw-button--framed': !this.frameless,
				'mw-button--icon': this.icon,
				'mw-button--invisible-text': this.invisibletext,
				'mw-button--progressive': this.progressive,
				'mw-button--destructive': this.destructive,
				'mw-button--primary': this.primary
			};
		},

		invert: function () {
			return ( this.primary || this.disabled ) && !this.frameless;
		}
	}
};
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';
@import 'mediawiki.mixins';

.mw-button {
	transition: background-color 100ms, color 100ms, border-color 100ms, box-shadow 100ms;
	background-color: @background-color-transparent;
	border: @border-width-base @border-style-base @border-color-transparent;
	border-radius: @border-radius-base;
	color: @color-base;
	font-size: inherit;
	font-weight: @font-weight-bold;
	padding: 6px;
	user-select: none;

	&:hover {
		background-color: rgba( 0, 24, 73, 7/255 );
		color: @color-emphasized;
		cursor: @cursor-base--hover;
	}

	&:focus {
		border-color: @border-color-progressive--focus;
		box-shadow: @box-shadow-inset-small @box-shadow-color-progressive--focus;
		outline: @outline-base--focus;
	}

	.mw-icon {
		height: 100%;
		left: 5/14em;
		position: absolute;
		top: 0;
		transition: opacity 100ms;

		/* stylelint-disable-next-line selector-class-pattern */
		&:not( .oo-ui-icon-invert ) {
			opacity: @opacity-icon-base;
		}
	}

	// Variants.
	&--icon {
		padding-left: 30/14em;
		position: relative;
	}

	&--framed {
		background-color: @background-color-interactive-subtle;
		border-color: @border-color-base;
		padding: 6px 12px;

		&:hover {
			background-color: @background-color-interactive;
			color: @color-base--hover;
		}

		&.mw-button--icon {
			padding-left: 38/14em;
			position: relative;
		}

		/* stylelint-disable-next-line no-descending-specificity */
		.mw-icon {
			left: 11/14em;
		}
	}

	&--progressive {
		color: @color-progressive;

		&:hover {
			color: @color-progressive--hover;
		}

		&.mw-button--framed {
			&:hover {
				border-color: @border-color-progressive--hover;
			}
		}
	}

	&--destructive {
		color: @color-destructive;

		&:hover {
			color: @color-destructive--hover;
		}

		&:focus {
			border-color: @border-color-destructive;
			box-shadow: @box-shadow-inset-small @box-shadow-color-destructive--focus;
		}

		&.mw-button--framed {
			&:hover {
				border-color: @border-color-destructive--hover;
			}

			&:focus {
				box-shadow: @box-shadow-inset-small @box-shadow-color-destructive--focus;
			}
		}
	}

	&--primary {
		&.mw-button--framed {
			// Default to progressive.
			background-color: @background-color-progressive;
			border-color: @border-color-progressive;
			color: @color-inverted;

			&:hover {
				background-color: @color-progressive--hover;
				border-color: @border-color-progressive--hover;
			}

			&:focus {
				box-shadow: @box-shadow-inset-small @box-shadow-color-progressive--focus, @box-shadow-inset-medium @box-shadow-color-inverted;
			}

			&.mw-button--destructive {
				background-color: @background-color-destructive;
				border-color: @border-color-destructive;

				&:hover {
					background-color: @background-color-destructive--hover;
					border-color: @border-color-destructive--hover;
				}

				&:focus {
					box-shadow: @box-shadow-inset-small @box-shadow-color-destructive--focus, @box-shadow-inset-medium @box-shadow-color-inverted;
				}
			}
		}
	}

	&:disabled {
		color: @color-disabled;
		cursor: @cursor-base--disabled;

		&:hover,
		&:focus {
			background-color: @background-color-base;
		}

		&.mw-button--framed {
			background-color: @background-color-disabled;
			border-color: @border-color-disabled;
			color: @color-inverted;

			&:hover,
			&:focus {
				background-color: @background-color-disabled;
				border-color: @border-color-disabled;
				box-shadow: none;
			}
		}

		&:not( .mw-button--framed ) .mw-icon {
			opacity: @opacity-icon-base--disabled;
		}
	}

	&--invisible-text {
		padding-right: 0;

		.mw-button__content {
			border: 0;
			clip: rect( 1px, 1px, 1px, 1px );
			display: block;
			height: 1px;
			margin: -1px;
			overflow: hidden;
			padding: 0;
			position: absolute;
			width: 1px;
		}
	}
}
</style>
