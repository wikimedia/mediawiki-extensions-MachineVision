<template>
	<wbmad-fade-in>
		<div
			v-if="unreviewedCount > 0"
			class="wbmad-personal-uploads-count">
			<p>{{ $i18n( 'machinevision-personal-uploads-count', unreviewedCount ) }}</p>
		</div>
	</wbmad-fade-in>
</template>

<script>
var mapState = require( 'vuex' ).mapState,
	FadeIn = require( './FadeIn.vue' );

// @vue/component
module.exports = exports = {
	components: {
		'wbmad-fade-in': FadeIn
	},

	computed: $.extend( {}, mapState( [
		'unreviewedCount'
	] ) )
};
</script>

<style lang="less">
.wbmad-personal-uploads-count {
	font-weight: bold;
	margin-bottom: 16px;

	p {
		margin: 0;
	}
}
</style>
