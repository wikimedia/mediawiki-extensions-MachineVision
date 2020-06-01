'use strict';

/**
 * Model for individual suggestions.
 *
 * @param {string} text The label text
 * @param {string} wikidataId The wikidata ID
 * @param {string} alias The item's first alias
 * @param {string} description The item description
 */
module.exports = function MvSuggestion( text, wikidataId, alias, description ) {
	this.text = text;
	this.wikidataId = wikidataId;
	this.confirmed = false;
	this.custom = false;
	this.alias = alias || null;
	this.description = description || null;
};
