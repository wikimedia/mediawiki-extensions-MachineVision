'use strict';

var TemplateRenderingDOMLessGroupWidget = require( '../base/TemplateRenderingDOMLessGroupWidget.js' ),
	SuggestedTagsCardstack = require( './SuggestedTagsCardstack.js' ),
	SuggestedTagsPage;

/**
 * Top-level component that houses page UI components.
 *
 * @param {Object} config
 */
SuggestedTagsPage = function ( config ) {
	var setUpTab;

	SuggestedTagsPage.parent.call( this, $.extend( {}, config ) );
	this.$element.addClass( 'wbmad-suggested-tags-page' );

	setUpTab = function ( queryType ) {
		var tab = new OO.ui.TabPanelLayout( queryType, {
			label: mw.message( 'machinevision-machineaidedtagging-' + queryType + '-tab' ).text()
		} );
		tab.$element.append( new SuggestedTagsCardstack( { queryType: queryType } ).$element );
		return tab;
	};

	this.tabs = new OO.ui.IndexLayout( {
		expanded: false,
		framed: false
	} )
		.addTabPanels( [ setUpTab( 'popular' ), setUpTab( 'user' ) ] );

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

module.exports = SuggestedTagsPage;
