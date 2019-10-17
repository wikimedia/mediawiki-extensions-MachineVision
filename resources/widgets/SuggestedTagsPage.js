'use strict';

var TemplateRenderingDOMLessGroupWidget = require( '../base/TemplateRenderingDOMLessGroupWidget.js' ),
	OnboardingDialog = require( './OnboardingDialog.js' ),
	SuggestedTagsCardstack = require( './SuggestedTagsCardstack.js' ),
	SuggestedTagsPage;

/**
 * Top-level component that houses page UI components.
 *
 * @param {Object} config
 */
SuggestedTagsPage = function ( config ) {
	SuggestedTagsPage.parent.call( this, $.extend( {}, config ) );
	this.$element.addClass( 'wbmad-suggested-tags-page' );
	this.onboardingPrefKey = 'wbmad-onboarding-dialog-dismissed';

	this.tabs = new OO.ui.IndexLayout( {
		expanded: false,
		framed: false
	} )
		.addTabPanels( [ this.setUpTab( 'popular' ), this.setUpTab( 'user' ) ] );
	this.tabs.getTabPanel( 'user' ).connect( this, { active: 'onUserTabActive' } );

	this.connect( this, {
		goToPopularTab: 'goToPopularTab'
	} );

	this.render();
};

OO.inheritClass(
	SuggestedTagsPage,
	TemplateRenderingDOMLessGroupWidget
);

SuggestedTagsPage.prototype.render = function () {
	this.renderTemplate( 'resources/widgets/SuggestedTagsPage.mustache+dom', {
		pageDescription: $( '<p>' ).msg( 'machinevision-machineaidedtagging-intro' ),
		tabsHeading: mw.message( 'machinevision-machineaidedtagging-tabs-heading' ).text(),
		tabs: this.tabs,
		licenseInfo: $( '<p>' ).msg( 'machinevision-machineaidedtagging-license-information' )
	} );
};

/**
 * Generate a tab panel layout for the page.
 * @param {string} queryType
 * @return {Object}
 */
SuggestedTagsPage.prototype.setUpTab = function ( queryType ) {
	var self = this,
		tab = new OO.ui.TabPanelLayout( queryType, {
			label: mw.message( 'machinevision-machineaidedtagging-' + queryType + '-tab' ).text()
		} ),
		suggestedTagsCardstack = new SuggestedTagsCardstack( { queryType: queryType } )
			.connect( self, {
				goToPopularTab: 'goToPopularTab'
			} );

	tab.$element.append( suggestedTagsCardstack.$element );
	return tab;
};

/**
 * Determines whether the onboarding dialog should be shown to the user.
 * Defaults to true. Type coercion is necessary due to the limitations of
 * browser localstorage.
 * @return {boolean}
 */
SuggestedTagsPage.prototype.onboardingDismissed = function () {
	var numVal;

	if ( mw.user.isAnon() ) {
		numVal = Number( mw.storage.get( this.onboardingPrefKey ) ) || 0;
	} else {
		numVal = Number( mw.user.options.get( this.onboardingPrefKey ) );
	}

	return Boolean( numVal );
};

/**
 * When the user tab is active, show the onboarding dialog if necessary.
 */
SuggestedTagsPage.prototype.onUserTabActive = function () {
	var onboardingDialog,
		windowManager;

	if ( this.onboardingDismissed() ) {
		return;
	}

	onboardingDialog = new OnboardingDialog( { onboardingPrefKey: this.onboardingPrefKey } );
	windowManager = new OO.ui.WindowManager();

	$( document.body ).append( windowManager.$element );
	windowManager.addWindows( [ onboardingDialog ] );
	windowManager.openWindow( onboardingDialog );
};

/**
 * Send user to the popular uploads tab.
 */
SuggestedTagsPage.prototype.goToPopularTab = function () {
	this.tabs.setTabPanel( 'popular' );
};

module.exports = SuggestedTagsPage;
