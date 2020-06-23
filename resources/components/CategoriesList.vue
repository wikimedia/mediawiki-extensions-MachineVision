<template>
	<div v-if="hasCategories" class="wbmad-category-list">
		<span
			v-i18n-html:machinevision-categories-label
			class="wbmad-category-list__label">
		</span>
		<span
			v-for="( category, index ) in categories"
			:key="category + index"
			class="wbmad-category-list__item"
		>
			{{ category }}
		</span>
	</div>
</template>

<script>
var mapGetters = require( 'vuex' ).mapGetters;

// @vue/component
module.exports = {
	name: 'CategoriesList',

	computed: $.extend( {}, mapGetters( [
		'currentImage'
	] ), {
		/**
		 * @return {boolean}
		 */
		hasCategories: function () {
			return this.categories.length > 0;
		},

		/**
		 * @return {Array}
		 */
		categories: function () {
			return this.currentImage.categories;
		}
	} )
};
</script>

<style lang="less">
@import 'mediawiki.mixins';
@import '../style-variables.less';

.wbmad-category-list {
	// TODO: if the FadeIn transition could take in a duration prop we could
	// use that and remove this mixin altogether.
	.fade-in( 0.2s );
	margin: 4px 0 0;

	span {
		color: @color-base--subtle;
		font-size: 0.928em;
	}
}

.wbmad-category-list__label {
	margin: 0 0.4em 0 0;
}

// This isn't _exactly_ ideal, and spacing this pipe-deliminated list would be
// more exact if we used flexbox. However, we need the text to wrap like a
// paragraph, so we'll get as close as we can with a border-right and some magic
// numbers. We'll at least explicitly set the word-spacing to normal to maximize
// the chance that the spacing looks even.
.wbmad-category-list__item {
	border-right: solid 1px @color-base--subtle;
	margin: 0 0.4em 0 0;
	padding-right: 0.4em;
	word-spacing: normal;

	&:last-child {
		border-right: 0;
		margin: 0;
		padding: 0;
	}
}
</style>
