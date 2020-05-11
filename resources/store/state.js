'use strict';

module.exports = {
	/**
	 * Tracks the currently-active tab in the UI
	 */
	currentTab: 'popular',

	/**
	 * These are the queues of image objects; for now "popular" and "user" are
	 * the only possible queues. Queues are always arrays of Image objects;
	 * items are added and removed using array methods like push and shift.
	 */
	images: {
		popular: [],
		user: []
	},

	/**
	 * Tracks the pending state of each queue (set to true if we are waiting
	 * for a getImages action to complete in either queue)
	 */
	fetchPending: {
		popular: false,
		user: false
	},

	/**
	 * Tracks the error state of each queue
	 */
	fetchError: {
		popular: false,
		user: false
	},

	/**
	 * Tracks whether the current image is in the process of being published
	 */
	publishPending: false,

	/**
	 * Count of a user's unreviewed images (personal queue only). Always an
	 * integer.
	 */
	unreviewedCount: 0,

	userStats: {},

	/**
	 * Image-specific messages which will show up as "toast notifications":
	 * these relate to success or errors when publishing image tags
	 */
	imageMessages: []
};
