'use strict';

var DOMLessGroupWidget = require( './DOMLessGroupWidget.js' );

var	TemplateRenderingDOMLessGroupWidget = function WikibaseMachineAssistedDepictsTemplateRenderingDOMLessGroupWidget( config ) {
	config = config || {};
	TemplateRenderingDOMLessGroupWidget.parent.call( this, $.extend( {}, config ) );
	DOMLessGroupWidget.call( this, $.extend( {}, config ) );
};
OO.inheritClass( TemplateRenderingDOMLessGroupWidget, OO.ui.Widget );
OO.mixinClass( TemplateRenderingDOMLessGroupWidget, DOMLessGroupWidget );

TemplateRenderingDOMLessGroupWidget.prototype.renderTemplate = function ( templatePath, data ) {
	this.$element
		.empty()
		.append(
			mw.template
			.get( mw.config.get( 'moduleID' ), templatePath )
			.render( data )
		);
};

module.exports = TemplateRenderingDOMLessGroupWidget;
