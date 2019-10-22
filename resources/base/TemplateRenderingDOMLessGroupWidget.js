'use strict';

var DOMLessGroupWidget = require( './DOMLessGroupWidget.js' ),
	TemplateRenderingDOMLessGroupWidget;

TemplateRenderingDOMLessGroupWidget = function ( config ) {
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
				.get( 'ext.MachineVision', templatePath )
				.render( data )
		);
};

module.exports = TemplateRenderingDOMLessGroupWidget;
