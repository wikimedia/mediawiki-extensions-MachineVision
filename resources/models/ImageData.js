'use strict';

var ImageData = function WikibaseMachineAssistedDepictsImageData(
	title,
	descriptionurl,
	thumburl,
	thumbheight,
	suggestions
) {
	this.title = title;
	this.descriptionurl = descriptionurl;
	this.thumburl = thumburl;
	this.thumbheight = thumbheight;
	this.suggestions = suggestions;
};

module.exports = ImageData;
