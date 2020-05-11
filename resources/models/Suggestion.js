'use strict';

/**
 * Model for individual suggestions.
 *
 * @param {string} text The label text
 * @param {string} wikidataId The wikidata ID
 */
module.exports = function MvSuggestion( text, wikidataId ) {
	this.text = text;
	this.wikidataId = wikidataId;
	this.confirmed = false;
	this.custom = false;
};
