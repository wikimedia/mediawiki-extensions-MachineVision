'use strict';
/* eslint-disable no-implicit-globals */

var TemplateRenderingDOMLessGroupWidget = require( './base/TemplateRenderingDOMLessGroupWidget.js' );

/**
 * User-facing content with an icon, text, and CTA button.
 *
 * @param {Object} config
 * @cfg {string} [className] Class name for this element
 * @cfg {string} [cta] Text for the CTA button
 * @cfg {string} [heading] Heading text
 * @cfg {string} [text] Body text
 * @cfg {string} [disclaimer] Optional small text below CTA button
 * @cfg {string} [event] Event to emit on CTA button click
 */
function UserMessage( config ) {
	this.config = config || {};
	UserMessage.parent.call( this, $.extend( {}, config ) );
	// eslint-disable-next-line mediawiki/class-doc
	this.$element.addClass( 'wbmad-user-message ' + this.config.className );

	this.ctaButton = new OO.ui.ButtonWidget( {
		classes: [ 'wbmad-user-message-cta' ],
		title: this.config.cta,
		label: this.config.cta,
		flags: [
			'primary',
			'progressive'
		]
	} ).on( 'click', this.onClick, [], this );

	this.render();
}
OO.inheritClass( UserMessage, TemplateRenderingDOMLessGroupWidget );

UserMessage.prototype.render = function () {
	this.renderTemplate( 'widgets/UserMessage.mustache+dom', {
		heading: this.config.heading,
		text: this.config.text,
		ctaButton: this.ctaButton,
		disclaimer: this.config.disclaimer
	} );
};

/**
 * On CTA button click, emit the configured event to the parent component.
 */
UserMessage.prototype.onClick = function () {
	this.emit( this.config.event );
};

module.exports = UserMessage;
