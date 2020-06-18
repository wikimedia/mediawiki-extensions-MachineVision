<template>
	<div class="wbmad-cardstack-placeholder">
		<div class="wbmad-cardstack-placeholder__image">
			<wbmad-spinner />
		</div>
		<div class="wbmad-cardstack-placeholder__tags">
			<div class="wbmad-cardstack-placeholder__heading" />
			<div class="wbmad-cardstack-placeholder__tag-list">
				<div class="wbmad-cardstack-placeholder__tag" />
				<div class="wbmad-cardstack-placeholder__tag" />
				<div class="wbmad-cardstack-placeholder__tag" />
				<div class="wbmad-cardstack-placeholder__tag" />
			</div>
		</div>
	</div>
</template>

<script>
var Spinner = require( './Spinner.vue' );

// @vue/component
module.exports = {
	components: {
		'wbmad-spinner': Spinner
	}
};
</script>

<style lang="less">
@import '../style-variables.less';

.wbmad-cardstack-placeholder {
	align-items: center;
	background-color: @background-color-framed;
	border-radius: 8px;
	box-shadow: 0 1px 4px rgba( 0, 0, 0, 0.1 );
	display: flex;
	flex-direction: column;
	height: 512px;
	justify-content: center;
	width: 100%;
}

.wbmad-cardstack-placeholder__image {
	align-items: center;
	border-radius: 8px 8px 0 0;
	display: flex;
	flex-grow: 1;
	justify-content: center;
	width: 100%;
}

.wbmad-cardstack-placeholder__tags {
	background: @background-color-base;
	border-radius: 0 0 8px 8px;
	width: 100%;
}

.wbmad-cardstack-placeholder__heading {
	background-color: @background-color-framed;
	border-radius: 18px;
	height: 28px;
	margin: 24px 16px;
	width: 300px;
}

.wbmad-cardstack-placeholder__tag-list {
	display: flex;
	margin: 0 16px 16px;
}

.wbmad-cardstack-placeholder__tag {
	background-color: @background-color-framed;
	border-radius: 18px;
	height: 28px;
	margin: 0 4px 4px 0;
	width: 100px;
}
</style>
