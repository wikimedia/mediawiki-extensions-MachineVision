<template>
	<wbmad-fade-in>
		<div class="wbmad-user-message">
			<div class="wbmad-user-message-icon"></div>
			<p class="wbmad-user-message-heading">
				{{ heading }}
			</p>
			<div class="wbmad-user-message-text">
				{{ text }}
			</div>
			<mw-button v-if="cta"
				class="wbmad-user-message-cta"
				:primary="true"
				:progressive="true"
				:title="cta"
				@click="$emit( 'cta-click' )"
			>
				{{ cta }}
			</mw-button>
			<p v-if="disclaimer" class="wbmad-user-message-disclaimer">
				{{ disclaimer }}
			</p>
		</div>
	</wbmad-fade-in>
</template>

<script>
var Button = require( './base/Button.vue' ),
	FadeIn = require( './FadeIn.vue' );

// @vue/component
module.exports = exports = {
	components: {
		'mw-button': Button,
		'wbmad-fade-in': FadeIn
	},

	props: {
		heading: {
			type: [ String, Object ],
			required: true
		},

		text: {
			type: [ String, Object ],
			required: true
		},

		cta: {
			type: [ String, Object ],
			default: null
		},

		disclaimer: {
			type: [ String, Object ],
			default: null
		}
	}
};
</script>

<style lang="less">
@import '../style-variables.less';

.wbmad-user-message {
	padding: 32px 16px;
	text-align: center;

	p {
		margin-bottom: 16px;
	}

	.wbmad-user-message-icon {
		background-position: center center;
		background-repeat: no-repeat;
		background-size: 100% auto;
		height: 7.5em;
		margin: 0 auto;
		width: 7.5em;
	}

	.wbmad-user-message-heading {
		font-size: 1.2em;
		font-weight: 600;
	}

	.wbmad-user-message-cta {
		margin: 32px 0 8px;
		padding: 8px 48px;
		width: 100%;

		@media screen and ( min-width: @width-breakpoint-tablet ) {
			width: auto;
		}
	}

	.wbmad-user-message-disclaimer {
		color: @color-base--subtle;
		font-size: 0.9em;
	}
}
</style>
