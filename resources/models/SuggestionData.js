'use strict';

/**
 * Model for individual suggestions.
 *
 * @param {string} text The label text
 * @param {string} wikidataId The wikidata ID
 */
function SuggestionData( text, wikidataId ) {
	this.text = text;
	this.wikidataId = wikidataId;
}

module.exports = SuggestionData;
