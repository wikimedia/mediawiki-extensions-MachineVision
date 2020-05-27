'use strict';
/* eslint-disable no-implicit-globals */

var DOMLessGroupWidget = require( './DOMLessGroupWidget.js' );

function TemplateRenderingDOMLessGroupWidget( config ) {
	config = config || {};
	TemplateRenderingDOMLessGroupWidget.parent.call( this, $.extend( {}, config ) );
	DOMLessGroupWidget.call( this, $.extend( {}, config ) );
}

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
