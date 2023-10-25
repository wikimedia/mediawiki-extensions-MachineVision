<template>
	<transition-group
		name="wbmad-suggestion-fade"
		tag="div"
		class="wbmad-suggestions-group"
		:class="builtInClasses"
	>
		<mw-suggestion
			v-for="suggestion in currentImageSuggestions"
			:key="getSuggestionKey( suggestion.wikidataId )"
			:text="suggestion.text"
			:confirmed="suggestion.confirmed"
			@click="toggleTagConfirmation( suggestion )"
		>
			<template v-if="tagDetailsExpanded">
				<label class="wbmad-suggestion__label">
					<span class="wbmad-suggestion__label__text">
						{{ suggestion.text }}
					</span>
					<template v-if="suggestion.alias">
						<span class="wbmad-suggestion__label__separator">–</span>
						<span class="wbmad-suggestion__label__alias">
							{{ suggestion.alias }}
						</span>
					</template>
				</label>
				<p v-if="suggestion.description" class="wbmad-suggestion__description">
					{{ suggestion.description }}
				</p>
			</template>

			<template v-else>
				<label class="wbmad-suggestion__label">{{ suggestion.text }}</label>
			</template>
		</mw-suggestion><!-- The elements cannot have a breaking space due to a bug in code that will be fixed with this upstream PR: https://github.com/vuejs/vue-next/pull/4647 --><div :key="buttonKey" class="wbmad-custom-tag-button-wrapper">
			<mw-button
				icon="add"
				class="wbmad-custom-tag-button"
				:title="$i18n( 'machinevision-add-custom-tag-title' )"
				@click="$emit( 'custom-tag-button-click' )"
			>
				{{ $i18n( 'machinevision-add-custom-tag' ) }}
			</mw-button>
		</div>
	</transition-group>
</template>

<script>
var mapActions = require( 'vuex' ).mapActions,
	mapGetters = require( 'vuex' ).mapGetters,
	mapState = require( 'vuex' ).mapState,
	Button = require( './base/Button.vue' ),
	Suggestion = require( './base/Suggestion.vue' );

// @vue/component
module.exports = exports = {

	components: {
		'mw-button': Button,
		'mw-suggestion': Suggestion
	},

	computed: $.extend( {}, mapState( [
		'tagDetailsExpanded'
	] ), mapGetters( [
		'currentImageSuggestions'
	] ), {
		/**
		 * Conditional classes.
		 *
		 * @return {Object}
		 */
		builtInClasses: function () {
			return {
				'wbmad-suggestions-group--expanded': this.tagDetailsExpanded
			};
		},

		buttonKey: function () {
			var modifier = this.tagDetailsExpanded ? '--expanded' : '';
			return 'add-custom-tag-button' + modifier;
		}
	} ),

	methods: $.extend( {}, mapActions( [
		'toggleTagConfirmation'
	] ), {
		getSuggestionKey: function ( wikidataId ) {
			var modifier = this.tagDetailsExpanded ? '--expanded' : '';
			return wikidataId + modifier;
		}
	} )
};
</script>

<style lang="less">
@import '../style-variables.less';
@import 'mediawiki.mixins';

.wbmad-suggestions-group {
	.flex-display();
	.flex-wrap( wrap );
	margin: 18px 0;

	p {
		margin: 0;
	}
}

// Suggestion styles when "detailed tags" toggle is on. Most browsers will
// support the column layout, and there's a flex layout fallback.
.wbmad-suggestions-group--expanded {
	justify-content: space-between;

	.mw-suggestion,
	.wbmad-custom-tag-button-wrapper {
		.flex( 0, 0, 100% );
		max-width: 100%;

		@media screen and ( min-width: @min-width-breakpoint-tablet ) {
			.flex( 0, 0, 49.4% );
		}
	}

	.mw-suggestion {
		margin: 0 0 12px 0;
		padding: 12px 24/14em;
		// We want long descriptions to wrap, even if it makes the
		// suggestion taller.
		white-space: normal;

		// We need some special styles to keep the absolutely positioned
		// checkmark icon in line with our increased padding.
		.mw-icon {
			// This is 0.25em less than the left padding of the transformed
			// suggestion content, which matches the ratio for non-
			// expanded tags.
			right: 29/28em;
			top: 12px;
		}
	}

	.wbmad-suggestion__label__text {
		font-weight: @font-weight-bold;
	}
}

// Use column layout if possible.
@supports ( columns: 2 auto ) {
	.wbmad-suggestions-group--expanded {
		@media screen and ( min-width: @min-width-breakpoint-tablet ) {
			columns: 2 auto;
			column-gap: 12px;
			display: block;

			.mw-suggestion {
				// Needed to prevent column from breaking mid-suggestion.
				display: inline-block;
				width: 100%;
			}
		}
	}
}

.wbmad-suggestion__label__separator {
	margin: 0 0.4em;
}

.wbmad-custom-tag-button.mw-button {
	border-radius: 18px;
	color: @color-base;
	line-height: 1.6;
	margin: 0 4px 4px 0;
	padding: 4px 1.25em 4px 30/14em;
	white-space: nowrap;

	&:hover,
	&:focus {
		color: @color-emphasized;
	}

	&:focus {
		// TODO: Deriving from standard focus styles seems like a design consistency issue
		border-color: @border-color-progressive--active;
		box-shadow: @box-shadow-inset-small @box-shadow-color-progressive--active;
		outline: @outline-base--focus;
	}

	.mw-icon {
		width: 1em;
	}
}

// Transitions.
.wbmad-suggestion-fade-enter {
	opacity: 0;
}

// This is necessary to remove the existing suggestion transitions,
// which interfere with these transitions (by delaying leaving for the duration
// of those transitions), and to instantly hide the suggestions that are leaving.
.wbmad-suggestion-fade-leave,
.wbmad-suggestion-fade-leave-active,
.wbmad-suggestion-fade-leave-to {
	transition: none;
	opacity: 0;
}

.wbmad-suggestion-fade-enter-active {
	transition: opacity 0.5s;
}
</style>
