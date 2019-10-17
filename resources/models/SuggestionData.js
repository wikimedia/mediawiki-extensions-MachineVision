'use strict';

var SuggestionData = function WikibaseMachineAssistedDepictsSuggestionData( text, wikidataId ) {
	this.text = text;
	this.wikidataId = wikidataId;
	this.confirmed = false;
};

module.exports = SuggestionData;
