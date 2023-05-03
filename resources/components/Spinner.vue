<template>
	<div class="wbmad-spinner">
		<div class="wbmad-spinner-bounce"></div>
	</div>
</template>

<script>
// @vue/component
module.exports = exports = {
};
</script>

<style lang="less">
@import '../style-variables.less';
@import 'mediawiki.mixins';

.wbmad-spinner {
	@keyframes wbmadSpinnerBouncedelay {
		0%,
		50%,
		100% {
			transform: scale( 0.625 );
		}

		// Equals 320ms.
		20% {
			opacity: 0.87;
			transform: scale( 1 );
		}
	}

	.flex-display();
	align-items: center;
	justify-content: center;
	padding: 48px 16px;
	white-space: nowrap;

	& .wbmad-spinner-bounce,
	&::before,
	&::after {
		animation: wbmadSpinnerBouncedelay 1600ms ease-in-out -160ms infinite both;
		background-color: @wbmad-background-color-spinner;
		border-radius: @border-radius-circle;
		content: '';
		display: block;
		float: left;
		height: @wbmad-size-spinner;
		width: @wbmad-size-spinner;
	}

	&::before {
		animation-delay: -330ms;
		margin-right: 4px;
	}

	&::after {
		animation-delay: 0s;
		margin-left: 4px;
	}
}
</style>
