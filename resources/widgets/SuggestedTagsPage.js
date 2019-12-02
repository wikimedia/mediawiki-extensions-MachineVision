'use strict';

var IMAGES_PER_PAGE = 10,
	TemplateRenderingDOMLessGroupWidget = require( '../base/TemplateRenderingDOMLessGroupWidget.js' ),
	SuggestionData = require( '../models/SuggestionData.js' ),
	ImageData = require( '../models/ImageData.js' ),
	OnboardingDialog = require( './OnboardingDialog.js' ),
	SuggestedTagsCardstack = require( './SuggestedTagsCardstack.js' );

/**
 * Top-level component that houses page UI components and runs API query.
 * @param {Object} config
 * @param {string} [config.startTag] which tab to set as active when page loads
 */
function SuggestedTagsPage( config ) {
	var userGroups = mw.config.get( 'wgUserGroups' ) || [],
		showTabs,
		defaults = {
			onboardingPrefKey: 'wbmad-onboarding-dialog-dismissed',
			startTab: 'popular'
		};

	this.config = $.extend( defaults, config );
	SuggestedTagsPage.parent.call( this, this.config );

	this.$element.addClass( 'wbmad-suggested-tags-page' );
	this.userIsAuthenticated = !!mw.config.get( 'wgUserName' );
	this.userIsAutoconfirmed = userGroups.indexOf( 'autoconfirmed' ) !== -1;
	this.initialData = mw.config.get( 'wgMVSuggestedTagsInitialData' );

	showTabs = this.userIsAuthenticated && this.userIsAutoconfirmed;

	// Only load tabs if user has permission to see them.
	if ( showTabs ) {
		this.tabs = new OO.ui.IndexLayout( {
			expanded: false,
			framed: false,
			classes: [ 'wbmad-suggested-tags-page-tabs' ]
		} ).addTabPanels( [
			new OO.ui.TabPanelLayout( 'popular', {
				label: mw.message( 'machinevision-machineaidedtagging-popular-tab' ).parse()
			} ),
			new OO.ui.TabPanelLayout( 'user', {
				label: mw.message( 'machinevision-machineaidedtagging-user-tab' ).parse()
			} )
		] );

		// Run query initially on the active tab so we get results.
		this.goToTab( this.config.startTab );

		if ( this.config.startTab === 'popular' && this.initialData ) {
			this.setUpCardstack( this.initialData.map( function ( item ) {
				var height = item.height,
					width = item.width;

				// Find thumbheight for images wider than 800px.
				if ( width > 800 ) {
					height = height * 800 / width;
				}

				return new ImageData(
					item.title,
					item.description_url,
					item.thumb_url,
					height,
					item.suggested_labels.map( function ( labelData ) {
						return new SuggestionData( labelData.label, labelData.wikidata_id );
					} )
				);
			} ) );
		} else {
			this.fetchItems();
		}

		this.connect( this, {
			fetchItems: 'fetchItems',
			showSuccessMessage: 'showSuccessMessage',
			showPublishErrorMessage: 'showPublishErrorMessage',
			goToPopularTab: [ 'goToTab', 'popular' ]
		} );

		// Run query anytime a tab is selected.
		this.tabs.connect( this, { set: 'onSetTab' } );
		window.addEventListener( 'hashchange', this.onHashChange.bind( this ), false );
	}

	this.render();
}

OO.inheritClass(
	SuggestedTagsPage,
	TemplateRenderingDOMLessGroupWidget
);

SuggestedTagsPage.prototype.render = function () {
	this.renderTemplate( 'widgets/SuggestedTagsPage.mustache+dom', {
		userIsAuthenticated: this.userIsAuthenticated,
		userIsAutoconfirmed: this.userIsAutoconfirmed,
		loginMessage: $( '<p>' ).append( mw.config.get( 'wgMVSuggestedTagsLoginMessage' ) ),
		autoconfirmedMessage: $( '<p>' ).msg( 'machinevision-autoconfirmed-message' ),
		pageDescription: $( '<p>' ).msg( 'machinevision-machineaidedtagging-intro' ),
		tabsHeading: mw.message( 'machinevision-machineaidedtagging-tabs-heading' ).parse(),
		tabs: this.tabs || null,
		licenseInfo: $( '<p>' ).msg( 'machinevision-machineaidedtagging-license-information' )
	} );
};

/**
 * @param {OO.ui.TabPanelLayout} tabPanel
 */
SuggestedTagsPage.prototype.onSetTab = function ( tabPanel ) {
	// Bail early if tab has not changed.
	if ( this.queryType === tabPanel.name ) {
		return;
	}

	// Show onboarding dialog for user tab.
	if ( tabPanel.name === 'user' ) {
		this.showOnboardingDialog();
	}

	window.history.replaceState( null, null, '#' + tabPanel.name );
	this.fetchItems();
};

/**
 * @param {string} tabName
 */
SuggestedTagsPage.prototype.goToTab = function ( tabName ) {
	if ( tabName in this.tabs.tabPanels ) {
		this.tabs.setTabPanel( tabName );
		window.history.replaceState( null, null, '#' + tabName );
	} else {
		this.goToTab( 'popular' );
	}
};

/**
 * If the user manually changes the hash, attempt to navigate to a new tab of
 * the same name.
 * @param {HashChangeEvent} hashChange
 */
SuggestedTagsPage.prototype.onHashChange = function ( hashChange ) {
	var newHash = new URL( hashChange.newURL ).hash,
		name = newHash.substring( 1 );
	this.goToTab( name );
};

/**
 * Add a new cardstack with items from the query response.
 * @param {Object} response
 */
SuggestedTagsPage.prototype.getItemsForQueryResponse = function ( response ) {
	var imageDataArray = [],
		validItems,
		userUnreviewedImageCount = response.query && response.query.unreviewedimagecount ?
			response.query.unreviewedimagecount.user.unreviewed :
			0,
		userTotalImageCount = response.query && response.query.unreviewedimagecount ?
			response.query.unreviewedimagecount.user.total :
			0;

	// Helper function to process query results
	function getImageDataForQueryResponse( item ) {
		return new ImageData(
			item.title,
			item.imageinfo[ 0 ].descriptionurl,
			item.imageinfo[ 0 ].thumburl,
			item.imageinfo[ 0 ].thumbheight,
			item.imagelabels.map( function ( labelData ) {
				return new SuggestionData( labelData.label, labelData.wikidata_id );
			} )
		);
	}

	// Process query response, if we have one
	if ( response.query && response.query.pages && Array.isArray( response.query.pages ) ) {
		// Filter out any results without the data we need.
		validItems = response.query.pages.filter( function ( item ) {
			return item.imageinfo && item.imagelabels && item.imagelabels.length;
		} );

		imageDataArray = validItems.map( function ( item ) {
			return getImageDataForQueryResponse( item );
		} );
	}

	this.setUpCardstack( imageDataArray, userUnreviewedImageCount,
		userTotalImageCount );
};

/**
 * Set up a new SuggestedTagsCardstack of the appropriate type.
 * @param {Array} imageDataArray
 * @param {?number} userUnreviewedImageCount
 * @param {?number} userTotalImageCount
 */
SuggestedTagsPage.prototype.setUpCardstack = function (
	imageDataArray,
	userUnreviewedImageCount,
	userTotalImageCount
) {
	var suggestedTagsCardstack = new SuggestedTagsCardstack( {
		queryType: this.tabs.getCurrentTabPanelName(),
		imageDataArray: imageDataArray,
		userUnreviewedImageCount: userUnreviewedImageCount || 0,
		userTotalImageCount: userTotalImageCount || 0
	} ).connect( this, {
		fetchItems: 'fetchItems',
		showSuccessMessage: 'showSuccessMessage',
		showPublishErrorMessage: 'showPublishErrorMessage',
		goToPopularTab: [ 'goToTab', 'popular' ]
	} );

	// Clear out loading indicator and add new cardstack.
	this.tabs.getCurrentTabPanel().$element.empty();
	this.tabs.getCurrentTabPanel().$element.append( suggestedTagsCardstack.$element );
};

/**
 * Fetch a batch of items.
 */
SuggestedTagsPage.prototype.fetchItems = function () {
	var api = new mw.Api(),
		queryType = this.tabs.getCurrentTabPanelName(),
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

	if ( queryType === 'user' ) {
		query.guiluploader = mw.user.getId();
		query.ilstate = 'unreviewed|withheld';
	}

	this.tabs.getCurrentTabPanel().$element.empty();
	this.showLoadingMessage();

	// Stash query type to avoid redundant requests later
	this.queryType = queryType;

	api.get( query )
		.done( this.getItemsForQueryResponse.bind( this ) )
		.fail( this.showFailureMessage.bind( this ) );
};

/**
 * When fetch fails, show a message explaining the situation.
 */
SuggestedTagsPage.prototype.showFailureMessage = function () {
	var failureMessage = new OO.ui.MessageWidget( {
		label: $( '<p>' ).msg( 'machinevision-failure-message' ),
		classes: [ 'wbmad-status-message' ]
	} );

	this.tabs.getCurrentTabPanel().$element.empty();
	this.tabs.getCurrentTabPanel().$element.append( failureMessage.$element );
};

/**
 * Show pulsating dots to indicate loading state.
 */
SuggestedTagsPage.prototype.showLoadingMessage = function () {
	var spinner = '<div class="wbmad-spinner"><div class="wbmad-spinner-bounce"></div></div>';
	this.tabs.getCurrentTabPanel().$element.append( spinner );
};

/**
 * After user publishes tags for an image, show a success message.
 */
SuggestedTagsPage.prototype.showSuccessMessage = function () {
	var successMessage = new OO.ui.MessageWidget( {
		label: mw.message( 'machinevision-success-message' ).parse(),
		classes: [ 'wbmad-toast wbmad-success-toast' ]
	} );
	this.$element.append( successMessage.$element );

	setTimeout( function () {
		successMessage.$element.remove();
	}, 4000 );
};

/**
 * When publish fails, show a message explaining the situation.
 */
SuggestedTagsPage.prototype.showPublishErrorMessage = function () {
	var errorMessage = new OO.ui.MessageWidget( {
		label: mw.message( 'machinevision-publish-error-message' ).parse(),
		classes: [ 'wbmad-toast wbmad-publish-error-toast' ]
	} );
	this.$element.append( errorMessage.$element );

	setTimeout( function () {
		errorMessage.$element.remove();
	}, 8000 );
};

/**
 * Show onboarding dialog if it hasn't been previously dismissed.
 */
SuggestedTagsPage.prototype.showOnboardingDialog = function () {
	var onboardingDialog,
		windowManager;

	// Type coercion is necessary due to limitations of browser localstorage.
	if ( Number( mw.user.options.get( this.config.onboardingPrefKey ) ) === 1 ) {
		return;
	}

	onboardingDialog = new OnboardingDialog( { onboardingPrefKey: this.config.onboardingPrefKey } );
	windowManager = new OO.ui.WindowManager();

	$( document.body ).append( windowManager.$element );
	windowManager.addWindows( [ onboardingDialog ] );
	windowManager.openWindow( onboardingDialog );
};

module.exports = SuggestedTagsPage;
