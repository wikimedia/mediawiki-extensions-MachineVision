var pathToWidget = '../../../resources/widgets/SuggestedTagsPage.js',
	hooks = require( '../support/hooks.js' ),
	sinon = require( 'sinon' ),
	sandbox,
	datamodel = require( 'wikibase.datamodel' );

QUnit.module( 'SuggestedTagsPage', hooks );

QUnit.test( 'Constructor test', function ( assert ) {
	var SuggestedTagsPage = require( pathToWidget ),
		widget = new SuggestedTagsPage();
	assert.ok( true );
} );

QUnit.test( 'Tabs do not load for anonymous user', function ( assert ) {
	var SuggestedTagsPage = require( pathToWidget ),
		widget,
		sandbox = sinon.createSandbox();

	sandbox.stub( global.mw.config, 'get' )
		.withArgs( 'wgUserName' ).returns( null )
		.withArgs( 'wgUserGroups' ).returns( [ null ] );

	widget = new SuggestedTagsPage();
	assert.strictEqual( widget.tabs, undefined );
} );

QUnit.test( 'Tabs do not load for un-autoconfirmed user', function ( assert ) {
	var SuggestedTagsPage = require( pathToWidget ),
		widget,
		sandbox = sinon.createSandbox();

	sandbox.stub( global.mw.config, 'get' )
		.withArgs( 'wgUserName' ).returns( 'UserName' )
		.withArgs( 'wgUserGroups' ).returns( null );

	widget = new SuggestedTagsPage();
	assert.strictEqual( widget.tabs, undefined );
} );

QUnit.module( 'SuggestedTagsPage with valid user', {
	beforeEach: function () {
		sandbox = sinon.createSandbox();
		hooks.beforeEach();

		// Configure a logged in, autoconfirmed user.
		sandbox.stub( global.mw.config, 'get' )
			.withArgs( 'wgUserName' ).returns( 'UserName' )
			.withArgs( 'wgUserGroups' ).returns( [ '*', 'user', 'autoconfirmed' ] );

		// Stub out mw.Api.get method so we can test fetching items.
		global.mw.Api = function () {};
		global.mw.Api.prototype = {
			get: sandbox.stub().returns( $.Deferred().resolve( {} ).promise() )
		};

		global.OO.ui.WindowManager.prototype.openWindow = sandbox.stub();
	},
	afterEach: function () {
		hooks.afterEach();
		sandbox.restore();
	}
} );

QUnit.test( 'Tabs load for authenticated and autoconfirmed user', function ( assert ) {
	var SuggestedTagsPage = require( pathToWidget ),
		widget;

	widget = new SuggestedTagsPage( { startTab: 'popular' } );
	assert.strictEqual( widget.tabs instanceof OO.ui.IndexLayout, true );
} );

QUnit.test( '"#popular" fragment in URL results in active popular tab on page load', function ( assert ) {
	var SuggestedTagsPage = require( pathToWidget ),
		widget;

	widget = new SuggestedTagsPage( { startTab: 'popular' } );
	assert.strictEqual( widget.tabs.getCurrentTabPanelName(), 'popular' );
} );

QUnit.test( '"#user" fragment in URL results in active user tab on page load', function ( assert ) {
	var SuggestedTagsPage = require( pathToWidget ),
		widget;

	widget = new SuggestedTagsPage( { startTab: 'user' } );
	assert.strictEqual( widget.tabs.getCurrentTabPanelName(), 'user' );
} );

QUnit.test( 'Hash change to existing tab sets that tab as active', function ( assert ) {
	var SuggestedTagsPage = require( pathToWidget ),
		widget;

	widget = new SuggestedTagsPage( { startTab: 'user' } );
	widget.onHashChange( { newURL: 'https://example.com/Special:SuggestedTags#popular' } );
	assert.strictEqual( widget.tabs.getCurrentTabPanelName(), 'popular' );

	widget.onHashChange( { newURL: 'https://example.com/Special:SuggestedTags#user' } );
	assert.strictEqual( widget.tabs.getCurrentTabPanelName(), 'user' );
} );

QUnit.test( 'Hash change to nonexistant tab sends user to popular tab', function ( assert ) {
	var SuggestedTagsPage = require( pathToWidget ),
		widget;

	widget = new SuggestedTagsPage( { startTab: 'user' } );
	widget.onHashChange( { newURL: 'https://example.com/Special:SuggestedTags#asdf' } );
	assert.strictEqual( widget.tabs.getCurrentTabPanelName(), 'popular' );
} );
