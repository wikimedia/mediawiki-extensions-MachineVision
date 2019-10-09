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
	var userGroups = mw.config.get( 'wgUserGroups' ) || [];

	SuggestedTagsPage.parent.call( this, $.extend( {}, config ) );
	this.$element.addClass( 'wbmad-suggested-tags-page' );

	this.onboardingPrefKey = 'wbmad-onboarding-dialog-dismissed';
	this.userIsAuthenticated = !!mw.config.get( 'wgUserName' );
	this.userIsAutoconfirmed = userGroups.indexOf( 'autoconfirmed' ) !== -1;
	this.tabs = null;

	// Only load tabs if user has permission to see them.
	if ( this.userIsAuthenticated && this.userIsAutoconfirmed ) {
		this.tabs = new OO.ui.IndexLayout( {
			expanded: false,
			framed: false
		} )
			.addTabPanels( [ this.setUpTab( 'popular' ), this.setUpTab( 'user' ) ] );
		this.tabs.getTabPanel( 'user' ).connect( this, { active: 'onUserTabActive' } );

		this.connect( this, {
			goToPopularTab: 'goToPopularTab'
		} );
	}

	this.render();
};

OO.inheritClass(
	SuggestedTagsPage,
	TemplateRenderingDOMLessGroupWidget
);

SuggestedTagsPage.prototype.render = function () {
	this.renderTemplate( 'resources/widgets/SuggestedTagsPage.mustache+dom', {
		userIsAuthenticated: this.userIsAuthenticated,
		userIsAutoconfirmed: this.userIsAutoconfirmed,
		loginMessage: $( '<p>' ).msg( 'machinevision-login-message' ),
		autoconfirmedMessage: $( '<p>' ).msg( 'machinevision-autoconfirm-message' ),
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
	return Boolean( Number( mw.user.options.get( this.onboardingPrefKey ) ) );
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
