/* eslint camelcase: 0 */
'use strict';

var TemplateRenderingDOMLessGroupWidget = require( './../base/TemplateRenderingDOMLessGroupWidget.js' ),
	AddCustomTagDialog = require( './AddCustomTagDialog.js' ),
	CategoryList = require( './CategoryList.js' ),
	ConfirmTagsDialog = require( './ConfirmTagsDialog.js' ),
	SuggestionsWidget = require( './SuggestionsWidget.js' ),
	datamodel = require( 'wikibase.datamodel' ),
	serialization = require( 'wikibase.serialization' ),
	mvConfig = require( 'ext.MachineVision.config' );

/**
 * A card within the cardstack on the Suggested Tags page. Each card contains
 * an image and a group of suggestionsWidget.
 *
 * @param {Object} config
 * @param {string} queryType
 * @cfg {string} descriptionurl Filepage URL
 * @cfg {Array} suggestions Image tag suggestions
 * @cfg {string} thumburl Image thumbnail URL
 * @cfg {string} thumbheight Image thumbnail height
 * @cfg {string} title Image title
 */
function ImageWithSuggestionsWidget( config, queryType ) {
	var $image;

	this.config = config || {};
	ImageWithSuggestionsWidget.parent.call( this, $.extend( {}, config ) );
	this.$element.addClass( 'wbmad-image-with-suggestions' );

	// Remove suggestions that lack a label.
	this.suggestions = this.config.suggestions.filter( function ( suggestion ) {
		return !!suggestion.text;
	} );
	this.suggestionsWidget = new SuggestionsWidget( { suggestions: this.suggestions } )
		.connect( this, { change: 'onChange' } );

	// Make a copy of the SuggestionsWidget so we can keep track of suggestions
	// sent by the provider (versus suggestions added by the user).
	this.originalSuggestions = $.extend( true, {}, this.suggestionsWidget );

	this.imageTitle = this.config.title.split( ':' ).pop();
	this.filePageUrl = this.config.descriptionurl;
	this.tab = queryType === 'user' ? 'personal' : 'popular';
	this.mediaInfoId = 'M' + this.config.pageid;
	this.guidGenerator = new wikibase.utilities.ClaimGuidGenerator( this.mediaInfoId );
	this.imageLoaded = false;
	this.categoryList = new CategoryList();
	this.wikidataIds = this.suggestions.map( function ( suggestion ) {
		return suggestion.wikidataId;
	} );

	// Initialize add custom tag dialog.
	this.addCustomTagDialog = new AddCustomTagDialog()
		.connect( this, { addCustomTag: 'onAddCustomTag' } );
	this.windowManager = new OO.ui.WindowManager();
	$( document.body ).append( this.windowManager.$element );
	this.windowManager.addWindows( [ this.addCustomTagDialog ] );

	this.titleLabel = new OO.ui.LabelWidget( {
		label: $( '<a>' )
			.attr( 'href', this.filePageUrl )
			.attr( 'target', '_blank' )
			.text( this.imageTitle ),
		classes: [ 'wbmad-image-with-suggestions__title-label' ]
	} );

	this.skipButton = new OO.ui.ButtonWidget( {
		classes: [ 'wbmad-action-buttons__skip' ],
		title: mw.message( 'machinevision-skip-title', this.imageTitle ).parse(),
		label: mw.message( 'machinevision-skip' ).parse(),
		framed: false
	} ).on( 'click', this.onSkip, [ true ], this );

	this.addCustomTagButton = new OO.ui.ButtonWidget( {
		classes: [ 'wbmad-custom-tag-button' ],
		title: mw.message( 'machinevision-add-custom-tag-title' ).parse(),
		label: mw.message( 'machinevision-add-custom-tag' ).parse(),
		icon: 'add'
	} ).on( 'click', this.onAddCustomTagClick, [], this );

	this.publishButton = new OO.ui.ButtonWidget( {
		classes: [ 'wbmad-action-buttons__publish' ],
		title: mw.message( 'machinevision-publish-title' ).parse(),
		label: mw.message( 'machinevision-publish' ).parse(),
		disabled: true,
		flags: [
			'primary',
			'progressive'
		]
	} ).on( 'click', this.onPublish, [], this );

	this.api = wikibase.api.getLocationAgnosticMwApi(
		mw.config.get( 'wbmiRepoApiUrl', mw.config.get( 'wbRepoApiUrl' ) )
	);

	this.connect( this, {
		confirm: 'onFinalConfirm',
		change: 'onChange',
		addCustomTag: 'onAddCustomTag'
	} );

	this.render();
	this.$element.find( '.oo-ui-multiselectWidget-group' ).append( this.addCustomTagButton.$element );

	// Set image height to avoid scroll issue when image is loaded.
	$image = this.$element.find( 'img' );
	if ( $image.length > 0 ) {
		$image[ 0 ].style.height = this.config.thumbheight + 'px';
	}
}
OO.inheritClass( ImageWithSuggestionsWidget, TemplateRenderingDOMLessGroupWidget );

ImageWithSuggestionsWidget.prototype.render = function () {
	this.renderTemplate( 'widgets/ImageWithSuggestionsWidget.mustache+dom', {
		skipButton: this.skipButton,
		imageTagTitle: this.imageTitle,
		titleLabel: this.titleLabel,
		suggestions: this.suggestionsWidget,
		imageLoaded: this.imageLoaded,
		thumburl: this.config.thumburl,
		filePageUrl: this.filePageUrl,
		categoryList: this.categoryList,
		publishButton: this.publishButton,
		showSpinner: this.showSpinner,
		spinnerClass: ( this.showSpinner ) ? 'wbmad-spinner-active' : ''
	} );
};

/**
 * Load image and categories.
 */
ImageWithSuggestionsWidget.prototype.displayWidget = function () {
	this.loadImage();
	this.loadCategories();
};

/**
 * Actually load the image.
 */
ImageWithSuggestionsWidget.prototype.loadImage = function () {
	var $image = this.$element.find( '.wbmad-lazy' );

	if ( $image.length > 0 ) {
		$image[ 0 ].src = $image[ 0 ].dataset.src;
		$image.removeClass( 'wbmad-lazy' );
		this.imageLoaded = true;
	}
};

/**
 * Fetch an array of category titles for this image.
 * @return {jQuery.Promise}
 */
ImageWithSuggestionsWidget.prototype.loadCategories = function () {
	var self = this,
		api = new mw.Api(),
		pages,
		keys,
		rawCategories,
		titlePrefix = 'Category:',
		sliceStart = titlePrefix.length;

	return api.get( {
		action: 'query',
		prop: 'categories',
		titles: [ 'File:' + this.imageTitle ]
	} )
		.done( function ( response ) {
			if ( response.query && response.query.pages ) {
				pages = response.query.pages;
				keys = Object.keys( pages );

				// Grab raw category page titles.
				rawCategories = pages[ Number( keys[ 0 ] ) ].categories;

				if ( rawCategories ) {
					self.categoryList.categories = rawCategories.map( function ( category ) {
						// Strip out the "Category:" string.
						return { title: category.title.slice( sliceStart ) };
					} );
					self.categoryList.render();
				}
			}
		} )
		.fail( function () {
			// No need to handle errors here, just don't show anything.
		} );
};

/**
 * When a suggestion is changed, see if buttons should be disabled.
 */
ImageWithSuggestionsWidget.prototype.onChange = function () {
	var hasConfirmed = this.suggestionsWidget.findSelectedItems().length > 0;
	this.publishButton.setDisabled( !hasConfirmed );
};

/**
 * Show a dialog where user can add a custom tag via autocomplete widget.
 */
ImageWithSuggestionsWidget.prototype.onAddCustomTagClick = function () {
	this.addCustomTagDialog.setFilter( this.wikidataIds );
	this.windowManager.openWindow( this.addCustomTagDialog, { $returnFocusTo: null } );
};

/**
 * Add a confirmed suggestion widget for the new custom tag.
 * @param {SuggestionData} suggestionData
 */
ImageWithSuggestionsWidget.prototype.onAddCustomTag = function ( suggestionData ) {
	var option = this.suggestionsWidget.createOption( suggestionData );

	// Add new tag and confirm it (which will enable the Publish button).
	this.suggestionsWidget.addItems( option );
	option.setSelected( true );

	// Add it to our list of existing Wikidata items.
	this.wikidataIds.push( suggestionData.wikidataId );

	// Reset the position of the Add Custom tag button.
	this.addCustomTagButton.$element.remove();
	this.$element.find( '.oo-ui-multiselectWidget-group' ).append( this.addCustomTagButton.$element );

	// Re-add the button click handler, unless the user has met the limit of 5
	// custom tags.
	if ( this.suggestions.length + 5 > this.wikidataIds.length ) {
		this.addCustomTagButton.$element.on( 'click', this.onAddCustomTagClick.bind( this ) );
	} else {
		this.addCustomTagButton.setDisabled( true );
	}
};

/**
 * Show a dialog prmopting user to confirm tags before publishing.
 */
ImageWithSuggestionsWidget.prototype.onPublish = function () {
	var self = this,
		confirmedSuggestions = this.suggestionsWidget.findSelectedItems(),
		tagsList = confirmedSuggestions.map( function ( suggestion ) {
			return suggestion.label;
		} ).join( ', ' ),
		confirmTagsDialog,
		windowManager;

	this.logEvent( {
		action: 'publish',
		approved_count: confirmedSuggestions.length
	} );

	confirmTagsDialog = new ConfirmTagsDialog( {
		tagsList: tagsList,
		imgUrl: this.config.thumburl,
		imgTitle: this.imageTitle
	} )
		.connect( self, { confirm: 'onFinalConfirm' } );

	windowManager = new OO.ui.WindowManager();
	$( document.body ).append( windowManager.$element );

	windowManager.addWindows( [ confirmTagsDialog ] );
	windowManager.openWindow( confirmTagsDialog );
};

/**
 * Publish new tags and move to the next image.
 * @return {jQuery.Promise}
 */
ImageWithSuggestionsWidget.prototype.onFinalConfirm = function () {
	var self = this,
		depictsPropertyId = mvConfig.depictsPropertyId,
		selected = this.suggestionsWidget.findSelectedItems(),
		// Data for all provided suggestions so we can update the database.
		reviewBatch = this.suggestionsWidget.items
			// Remove custom suggestions.
			.filter( function ( suggestion ) {
				return !!self.originalSuggestions.findItemFromData( suggestion.data );
			} )
			.map( function ( suggestion ) {
				return {
					label: suggestion.data,
					review: suggestion.isSelected() ? 'accept' : 'reject'
				};
			} ),
		// Statements for confirmed suggestions, including custom.
		depictsStatements = selected.map( function ( suggestion ) {
			return new datamodel.Statement(
				new datamodel.Claim(
					new datamodel.PropertyValueSnak(
						depictsPropertyId,
						new datamodel.EntityId( suggestion.wikidataId )
					),
					null, // qualifiers
					self.guidGenerator.newGuid()
				)
			);
		} ),
		serializer = new serialization.StatementSerializer(),
		promise;

	this.messages = [];
	this.showSpinner = true;
	this.publishButton.setDisabled( true );
	this.skipButton.setDisabled( true );
	this.render();

	this.logEvent( {
		action: 'confirm',
		approved_count: selected.length
	} );

	promise = this.api.postWithToken( 'csrf', {
		action: 'reviewimagelabels',
		filename: this.imageTitle,
		batch: JSON.stringify( reviewBatch )
	} );

	// Send wbsetclaim calls one at a time to prevent edit conflicts
	depictsStatements.forEach( function ( statement ) {
		promise = promise.then( function () {
			return self.api.postWithToken( 'csrf', {
				action: 'wbsetclaim',
				claim: JSON.stringify( serializer.serialize( statement ) ),
				tags: 'computer-aided-tagging'
			} );
		} );
	} );

	return $.when( promise )
		// eslint-disable-next-line no-unused-vars
		.done( function ( result ) {
			// Show success message.
			self.emit( 'tagsPublished' );
		} )
		// eslint-disable-next-line no-unused-vars
		.fail( function ( errorCode, error ) {
			self.emit( 'publishError' );
		} )
		.always( function () {
			// Move to next image.
			self.onSkip( false );
		} );
};

/**
 * Remove this image. As a result, the next image will display (via CSS).
 * @param {boolean} userExplicitlySkipped set to true if this is called when the user clicks 'skip'
 * @fires itemRemoved
 */
ImageWithSuggestionsWidget.prototype.onSkip = function ( userExplicitlySkipped ) {
	this.$element.remove();

	// Emit an event so parent element can see if we need to fetch more images.
	this.emit( 'itemRemoved' );

	if ( userExplicitlySkipped ) {
		this.logEvent( { action: 'skip' } );
	}
};

/**
 * Log a user interaction event.
 * @param {!Object} eventData
 * @return {jQuery.Promise} jQuery Promise object for the logging call.
 */
ImageWithSuggestionsWidget.prototype.logEvent = function ( eventData ) {
	var event = eventData;
	event.image_title = this.imageTitle;
	event.suggestions_count = this.suggestions.length;
	event.is_mobile = mw.config.get( 'skin' ) === 'minerva';
	event.tab = this.tab;
	event.user_id = mw.user.getId();
	return mw.eventLog.logEvent( 'SuggestedTagsAction', event );
};

module.exports = ImageWithSuggestionsWidget;
