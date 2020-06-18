<template>
	<transition
		appear
		name="wbmad-fade-in"
		appear-class="wbmad-fade-in-appear"
		appear-active-class="wbmad-fade-in-appear-active"
	>
		<slot></slot>
	</transition>
</template>

<script>
/**
 * Reusable fade-in transition.
 */
// @vue/component
module.exports = {
};
</script>

<style lang="less">
.wbmad-fade-in-appear,
.wbmad-fade-in-enter {
	opacity: 0;
}

.wbmad-fade-in-appear-active,
.wbmad-fade-in-enter-to {
	transition: opacity 0.5s;
}
</style>
