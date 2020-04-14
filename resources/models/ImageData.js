'use strict';

var ImageData = function WikibaseMachineAssistedDepictsImageData(
	title,
	pageid,
	descriptionurl,
	thumburl,
	thumbheight,
	suggestions,
	categories
) {
	this.title = title;
	this.pageid = pageid;
	this.descriptionurl = descriptionurl;
	this.thumburl = thumburl;
	this.thumbheight = thumbheight;
	this.suggestions = suggestions;
	this.categories = categories;
};

module.exports = ImageData;
