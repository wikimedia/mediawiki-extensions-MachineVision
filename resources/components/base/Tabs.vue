<template>
	<div class="mw-tabs">
		<div
			class="mw-tabs__header"
			role="tablist"
			tabindex="0"
			:aria-activedescendant="currentTabId"
			@keydown.left="moveBack"
			@keydown.up.prevent="moveBack"
			@keydown.right="moveForward"
			@keydown.down.prevent="moveForward"
		>
			<div
				v-for="tab in tabs"
				:id="tab.id + '-label'"
				:key="tab.title"
				:class="determineTabLabelClasses( tab )"
				:aria-selected="tab.name === currentTabName"
				:aria-controls="tab.id"
				class="mw-tabs__header__item"
				role="tab"
				@click="selectTab( tab.name )"
				@keyup.enter="selectTab( tab.name )"
			>
				{{ tab.title }}
			</div>
		</div>

		<div class="mw-tabs__content">
			<slot></slot>
		</div>
	</div>
</template>

<script>
/**
 * A group of tabs with a tab menu. See App for usage example.
 *
 * Tab can be changed via user click on a tab menu item or by changing the
 * active prop passed to the Tabs component.
 */
// @vue/component
module.exports = exports = {
	props: {
		active: {
			type: String,
			default: null
		}
	},
	compatConfig: {
		MODE: 2
	},
	data: function () {
		return {
			tabs: {},
			currentTabName: null
		};
	},

	computed: {
		currentTabId: function () {
			return this.tabs[ this.currentTabName ] ?
				this.tabs[ this.currentTabName ].id + '-label' :
				false;
		}
	},

	methods: {
		/**
		 * Change the current tab.
		 *
		 * @param {string} tabName
		 */
		selectTab: function ( tabName ) {
			if ( this.tabs[ tabName ].disabled === true ) {
				return;
			}

			this.currentTabName = tabName;
		},

		/**
		 * Set active attribute on each tab.
		 *
		 * @param {string} currentTabName
		 */
		setTabState: function ( currentTabName ) {
			var tabName;
			for ( tabName in this.tabs ) {
				this.tabs[ tabName ].isActive = ( tabName === currentTabName );
			}
		},

		/**
		 * Set tab label classes.
		 *
		 * @param {VueComponent} tab
		 * @return {Object}
		 */
		determineTabLabelClasses: function ( tab ) {
			return {
				'is-active': tab.name === this.currentTabName,
				'is-disabled': tab.disabled
			};
		},

		/**
		 * Left or up arrow keydown should move to previous tab, if one exists.
		 */
		moveBack: function () {
			var tabNames = Object.keys( this.tabs ),
				previousTabIndex = tabNames.indexOf( this.currentTabName ) - 1;

			if ( previousTabIndex < 0 ) {
				// There is no previous tab, do nothing.
				return;
			}

			this.selectTab( Object.keys( this.tabs )[ previousTabIndex ] );
		},

		/**
		 * Right or down arrow keydown should move to next tab, if one exists.
		 */
		moveForward: function () {
			var tabNames = Object.keys( this.tabs ),
				nextTabIndex = tabNames.indexOf( this.currentTabName ) + 1;

			if ( nextTabIndex >= tabNames.length ) {
				// There is no next tab, do nothing.
				return;
			}

			this.selectTab( tabNames[ nextTabIndex ] );
		},

		/**
		 * Create an object with tabs keyed by their names, then set the
		 * isActive attribute for each tab.
		 */
		initializeTabs: function () {
			// eslint-disable-next-line no-jquery/variable-pattern
			var tabs = this.$children;
			this.tabs = {};

			tabs.forEach( function ( tab ) {
				this.tabs[ tab.name ] = tab;
			}.bind( this ) );

			// If no active tab was passed in as a prop, default to first one.
			this.currentTabName = this.active ? this.active : Object.keys( this.tabs )[ 0 ];
			this.setTabState( this.currentTabName );
		}
	},

	watch: {
		/**
		 * When the tab stored in state changes, select that tab.
		 *
		 * @param {string} newTabName
		 */
		active: function ( newTabName ) {
			this.selectTab( newTabName );
		},

		/**
		 * When the current tab changes, set active states and emit an event.
		 *
		 * @param {string} newTabName
		 */
		currentTabName: function () {
			this.setTabState( this.currentTabName );

			// Don't emit an event if the currentTabName changed as a result of
			// the active prop changing. In that case, the parent already knows.
			if ( this.currentTabName !== this.active ) {
				this.$emit( 'tab-change', this.tabs[ this.currentTabName ] );
			}
		}
	},

	mounted: function () {
		this.initializeTabs();
	}
};
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';
@import 'mediawiki.mixins';

.mw-tabs {
	&__header {
		.flex-display();
		box-shadow: inset 0 -1px 0 0 @border-color-base;

		/* FIXME: Prefix is-active/is-disable classes */
		/* stylelint-disable selector-class-pattern */
		&__item {
			color: @color-subtle;
			font-weight: @font-weight-bold;
			margin: 6px 6px 0 0;
			padding: 6px 13px;
			transition: color 100ms, box-shadow 100ms;

			&:hover,
			&.is-active {
				color: @color-progressive;
				box-shadow: inset 0 -2px 0 0 @color-progressive;
			}

			&:hover {
				color: @color-progressive--hover;
				box-shadow: inset 0 -2px 0 0 @color-progressive--hover;
				cursor: @cursor-base--hover;
			}

			&.is-disabled {
				color: @color-disabled;
				cursor: not-allowed;

				&:hover,
				&.is-active {
					color: @color-disabled;
					box-shadow: unset;
				}
			}
		}

		&:focus {
			outline: @outline-base--focus;

			.mw-tabs__header__item.is-active {
				border-radius: @border-radius-base;
				box-shadow: inset 0 0 0 2px @color-progressive;
			}
		}
		/* stylelint-enable selector-class-pattern */
	}

	&__content {
		padding: 20px;
	}
}
</style>
