'use strict';

var ImageData = function WikibaseMachineAssistedDepictsImageData(
	title,
	descriptionurl,
	thumburl,
	suggestions
) {
	this.title = title;
	this.descriptionurl = descriptionurl;
	this.thumburl = thumburl;
	this.suggestions = suggestions;
};

module.exports = ImageData;
