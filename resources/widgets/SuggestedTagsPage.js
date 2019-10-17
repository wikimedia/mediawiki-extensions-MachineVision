'use strict';

var IMAGES_PER_PAGE = 10,
	TemplateRenderingDOMLessGroupWidget = require( '../base/TemplateRenderingDOMLessGroupWidget.js' ),
	SuggestionData = require( '../models/SuggestionData.js' ),
	ImageData = require( '../models/ImageData.js' ),
	OnboardingDialog = require( './OnboardingDialog.js' ),
	SuggestedTagsCardstack = require( './SuggestedTagsCardstack.js' ),
	SuggestedTagsPage,
	getImageDataForQueryResponse;

/**
 * Top-level component that houses page UI components and runs API query.
 * @param {Object} config
 */
SuggestedTagsPage = function ( config ) {
	var self = this,
		userGroups = mw.config.get( 'wgUserGroups' ) || [];

	SuggestedTagsPage.parent.call( this, $.extend( {}, config ) );
	this.$element.addClass( 'wbmad-suggested-tags-page' );

	this.onboardingPrefKey = 'wbmad-onboarding-dialog-dismissed';
	this.userIsAuthenticated = !!mw.config.get( 'wgUserName' );
	this.userIsAutoconfirmed = userGroups.indexOf( 'autoconfirmed' ) !== -1;
	this.currentTab = null;

	// Only load tabs if user has permission to see them.
	if ( this.userIsAuthenticated && this.userIsAutoconfirmed ) {
		this.tabs = new OO.ui.IndexLayout( {
			expanded: false,
			framed: false
		} )
			.addTabPanels( [
				new OO.ui.TabPanelLayout( 'popular', {
					label: mw.message( 'machinevision-machineaidedtagging-popular-tab' ).text()
				} ),
				new OO.ui.TabPanelLayout( 'user', {
					label: mw.message( 'machinevision-machineaidedtagging-user-tab' ).text()
				} )
			] );

		// Run query initially on the active tab so we get results.
		this.onTabSet( this.tabs.getCurrentTabPanelName() );

		// Run query anytime a tab is selected.
		this.tabs.connect( self, { set: 'onTabSet' } );

		this.connect( this, {
			fetchItems: 'fetchItems',
			showSuccessMessage: 'showSuccessMessage',
			showPublishErrorMessage: 'showPublishErrorMessage',
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
		tabs: this.tabs || null,
		licenseInfo: $( '<p>' ).msg( 'machinevision-machineaidedtagging-license-information' )
	} );
};

// TODO: (T233232) Add "failed" state and show content in the template instead.
SuggestedTagsPage.prototype.showFailureMessage = function () {
	var failureMessage = new OO.ui.MessageWidget( {
		label: $( '<p>' ).msg( 'machinevision-failure-message' ),
		classes: [ 'wbmad-status-message' ]
	} );
	this.currentTab.$element.empty();
	this.currentTab.$element.append( failureMessage.$element );
};

// TODO: (T233232) Add "loading" state and show content in the template instead.
SuggestedTagsPage.prototype.showLoadingMessage = function () {
	var spinner = '<div class="wbmad-spinner"><div class="wbmad-spinner-bounce"></div></div>';
	this.currentTab.$element.append( spinner );
};

/**
 * Get a formatted object of image data.
 *
 * @param {Object} item An item from the query response
 * @return {Object}
 */
getImageDataForQueryResponse = function ( item ) {
	if ( item.imageinfo && item.imagelabels && item.imagelabels.length ) {
		return new ImageData(
			item.title,
			item.imageinfo[ 0 ].thumburl,
			item.imagelabels.map( function ( labelData ) {
				return new SuggestionData( labelData.label, labelData.wikidata_id );
			} )
		);
	}
};

/**
 * Add a new cardstack with items from the query response.
 * @param {Object} response
 */
SuggestedTagsPage.prototype.getItemsForQueryResponse = function ( response ) {
	var self = this,
		suggestedTagsCardstack,
		imageDataArray = response.query && response.query.pages &&
			Array.isArray( response.query.pages ) ? response.query.pages.map( function ( page ) {
				return getImageDataForQueryResponse( page );
			} ) : [],
		resultsFound = !!imageDataArray.length;

	suggestedTagsCardstack = new SuggestedTagsCardstack( {
		queryType: this.queryType,
		resultsFound: resultsFound,
		imageDataArray: imageDataArray
	} )
		.connect( self, {
			fetchItems: 'fetchItems',
			showSuccessMessage: 'showSuccessMessage',
			showPublishErrorMessage: 'showPublishErrorMessage',
			goToPopularTab: 'goToPopularTab'
		} );

	// Clear out loading indicator and add new cardstack.
	this.currentTab.$element.empty();
	this.currentTab.$element.append( suggestedTagsCardstack.$element );
};

/**
 * Fetch a batch of items.
 */
SuggestedTagsPage.prototype.fetchItems = function () {
	var api = new mw.Api(),
		query = {
			action: 'query',
			format: 'json',
			formatversion: 2,
			generator: 'unreviewedimagelabels',
			guillimit: IMAGES_PER_PAGE,
			prop: 'imageinfo|imagelabels',
			iiprop: 'url',
			iiurlwidth: 800,
			ilstate: 'unreviewed',
			meta: 'unreviewedimagecount',
			uselang: mw.config.get( 'wgUserLanguage' )
		};

	if ( this.queryType === 'user' ) {
		query.guiluploader = mw.user.getId();
	}

	this.currentTab.$element.empty();
	this.showLoadingMessage();

	api.get( query )
		.done( this.getItemsForQueryResponse.bind( this ) )
		.fail( this.showFailureMessage.bind( this ) );
};

/**
 * Show onboarding dialog if it hasn't been previously dismissed.
 */
SuggestedTagsPage.prototype.showOnboardingDialog = function () {
	var onboardingDialog,
		windowManager;

	// Type coercion is necessary due to limitations of browser localstorage.
	if ( Number( mw.user.options.get( this.onboardingPrefKey ) ) === 1 ) {
		return;
	}

	onboardingDialog = new OnboardingDialog( { onboardingPrefKey: this.onboardingPrefKey } );
	windowManager = new OO.ui.WindowManager();

	$( document.body ).append( windowManager.$element );
	windowManager.addWindows( [ onboardingDialog ] );
	windowManager.openWindow( onboardingDialog );
};

/**
 * When a tab is selected, run a new query and add a cardstack to show results.
 */
SuggestedTagsPage.prototype.onTabSet = function () {
	var newQueryType = this.tabs.getCurrentTabPanelName();

	// Don't bother running a query if the user clicks on the active tab.
	if ( this.queryType === newQueryType ) {
		return;
	}

	// Store new query type and active tab.
	this.queryType = newQueryType;
	this.currentTab = this.tabs.getCurrentTabPanel();

	// Show onboarding dialog for user tab.
	if ( this.queryType === 'user' ) {
		this.showOnboardingDialog();
	}

	this.fetchItems();
};

/**
 * Send user to the popular uploads tab.
 */
SuggestedTagsPage.prototype.goToPopularTab = function () {
	this.tabs.setTabPanel( 'popular' );
};

/**
 * After user publishes tags for an image, show a success message.
 */
SuggestedTagsPage.prototype.showSuccessMessage = function () {
	var successMessage = new OO.ui.MessageWidget( {
		label: mw.message( 'machinevision-success-message' ).text(),
		classes: [ 'wbmad-toast wbmad-success-toast' ]
	} );
	this.$element.append( successMessage.$element );

	setTimeout( function () {
		successMessage.$element.remove();
	}, 4000 );
};

/**
 * After user publishes tags for an image, show a success message.
 */
SuggestedTagsPage.prototype.showPublishErrorMessage = function () {
	var errorMessage = new OO.ui.MessageWidget( {
		label: mw.message( 'machinevision-publish-error-message' ).text(),
		classes: [ 'wbmad-toast wbmad-publish-error-toast' ]
	} );
	this.$element.append( errorMessage.$element );

	setTimeout( function () {
		errorMessage.$element.remove();
	}, 8000 );
};

module.exports = SuggestedTagsPage;
