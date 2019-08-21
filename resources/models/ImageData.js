'use strict';

var ImageData = function WikibaseMachineAssistedDepictsImageData( title, thumburl, description, suggestions ) {
	this.title = title;
	this.thumburl = thumburl;
	this.description = description;
	this.suggestions = suggestions;
};

module.exports = ImageData;
