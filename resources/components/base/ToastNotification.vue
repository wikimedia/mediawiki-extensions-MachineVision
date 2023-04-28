<template>
	<div v-if="showWrapper" class="mw-toast">
		<transition
			appear
			name="mw-toast"
			appear-class="mw-toast-appear"
			appear-active-class="mw-toast-appear-active"
			appear-to-class="mw-toast-appear-to"
			@appear="onAppear"
			@after-leave="afterLeave"
		>
			<div
				v-if="show"
				class="mw-toast__notification"
				:aria-live="type !== 'error' ? 'polite' : false"
				:role="type === 'error' ? 'alert' : false "
			>
				<div class="mw-toast__notification__content">
					<slot></slot>
				</div>
			</div>
		</transition>
	</div>
</template>

<script>
/**
 * A small pop-up notification. See App for usage example.
 *
 * When specifying duration, ensure the user will have sufficient time to read
 * and process the notification. Message content should be as concise as
 * possible.
 *
 * Specifying notification type provides helpful information to screen readers.
 * Type can be one of "success" or "error".
 */
// @vue/component
module.exports = exports = {
	props: {
		type: {
			type: String,
			default: 'success'
		},
		duration: {
			type: Number, // in seconds. Use v-bind to ensure this is actually a number.
			default: 10
		}
	},

	data: function () {
		return {
			show: true,
			showWrapper: true
		};
	},

	methods: {
		/**
		 * When this component appears, start a countdown based on the provided
		 * duration, then hide the toast, which kicks off leave transitions.
		 */
		onAppear: function () {
			var hideToast = function () {
				this.show = false;
			};
			setTimeout( hideToast.bind( this ), this.duration * 1000 );
		},
		/**
		 * After the leave transitions finish, remove the toast and wrapper.
		 */
		afterLeave: function () {
			this.showWrapper = false;

			// Emit an event that can be used by the parent to act (e.g. change
			// state) once the toast has been removed.
			this.$emit( 'leave', this.$vnode.key );
		}
	}
};
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';
@import 'mediawiki.mixins';

.mw-toast {
	// Center the toast within the parent container.
	.flex-display();
	justify-content: center;
}

.mw-toast__notification {
	background-color: @color-base;
	border-radius: @border-radius-base * 4;
	bottom: 5vh;
	color: @color-inverted;
	display: inline-block;
	margin: 0;
	padding: 8px 32px;
	position: fixed;
	text-align: center;
	width: 95%;
	// This is debatable.
	z-index: 4;

	@media screen and ( min-width: @width-breakpoint-tablet ) {
		width: auto;
	}
}

.mw-toast__notification__content {
	// In case slot content isn't wrapped in a paragraph tag, let's duplicate
	// the margins here (they'll collapse if a p tag is used).
	margin: 0.5em 0;
}

// Transitions.
.mw-toast-appear,
.mw-toast-leave-to {
	bottom: 0;
	opacity: 0;
}

.mw-toast-appear-active,
.mw-toast-leave-active {
	transition: bottom 1s, opacity 1s;
}

.mw-toast-appear-to {
	bottom: 5vh;
	opacity: 1;
}
</style>
