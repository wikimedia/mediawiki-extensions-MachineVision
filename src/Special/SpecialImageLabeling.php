<?php

namespace MediaWiki\Extension\MachineVision\Special;

use ImageQueryPage;

/**
 * Quick hack for having a task-queue-like API
 */
class SpecialImageLabeling extends ImageQueryPage {

	/** @inheritDoc */
	public function __construct( $name = 'ImageLabeling' ) {
		parent::__construct( $name );
		$this->addHelpLink( 'Help:Image labeling' );
	}

	/** @inheritDoc */
	public function execute( $par ) {
		$this->requireLogin();
		return parent::execute( $par );
	}

	/** @inheritDoc */
	public function getQueryInfo() {
		// FIXME this assumes that mvl is in the main wiki database. Might want to rethink later.
		return [
			'tables' => [ 'machine_vision_label', 'image' ],
			'fields' => [
				'namespace' => NS_FILE,
				'title' => 'img_name',
			],
			'conds' => [
				'mvl_review' => 0,
			],
			'options' => [
				'GROUP BY' => [ 'mvl_image_sha1' ]
			],
			'join_conds' => [ 'image' => [ 'JOIN', 'mvl_image_sha1 = img_sha1' ] ],
		];
	}

	/** @inheritDoc */
	public function usesTimestamps() {
		return true;
	}

	/** @inheritDoc */
	public function getOrderFields() {
		return [ 'mvl_image_sha1' ];
	}

	/** @inheritDoc */
	public function getGroupName() {
		return 'media';
	}

	/** @inheritDoc */
	public function getDescription() {
		return $this->msg( 'machinevision-imagelabeling' )->text();
	}

	/** @inheritDoc */
	public function isSyndicated() {
		return false;
	}

}
