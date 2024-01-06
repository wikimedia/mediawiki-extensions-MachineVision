<?php

namespace MediaWiki\Extension\MachineVision\Special;

use LocalRepo;
use MediaWiki\Extension\MachineVision\Handler\LabelResolver;
use MediaWiki\Extension\MachineVision\Repository;
use MediaWiki\Extension\MachineVision\Services;
use MediaWiki\MediaWikiServices;
use MediaWiki\SpecialPage\SpecialPage;

class SpecialSuggestedTags extends SpecialPage {

	/** @var LocalRepo */
	private $fileRepo;

	/** @var Repository */
	private $labelRepo;

	/** @var LabelResolver */
	private $labelResolver;

	/** @inheritDoc */
	public function __construct( $name = 'SuggestedTags' ) {
		parent::__construct( $name, $this->testersOnly() ? 'imagelabel-test' : '' );
		$extensionServices = new Services( MediaWikiServices::getInstance() );
		$this->fileRepo = $extensionServices->getRepoGroup()->getLocalRepo();
		$this->labelRepo = $extensionServices->getRepository();
		$this->labelResolver = $extensionServices->getLabelResolver();
	}

	/** @inheritDoc */
	public function execute( $par ) {
		$this->checkPermissions();
		$this->setHeaders();

		$out = $this->getOutput();
		$out->addHTML(
			'<div class="wbmad-intro"><p>' .
			$this->msg( 'machinevision-disabled-notice' )->parse() .
			'</p></div>'
		);
	}

	/** @inheritDoc */
	public function getDescription() {
		return $this->msg( 'machinevision-machineaidedtagging' );
	}

	/**
	 * Return markup for content placeholder to be shown during page load.
	 *
	 * @return string
	 */
	private function getPlaceholderMarkup() {
		return <<<'EOT'
<div class="wbmad-placeholder">
	<div class="wbmad-placeholder__heading"></div>
	<div class="wbmad-placeholder__tabs-wrapper">
		<div class="wbmad-placeholder__tabs"></div>
	</div>
	<div class="wbmad-placeholder__cardstack">
		<div class="wbmad-placeholder__cardstack-image">
			<div class="wbmad-placeholder__spinner">
				<div class="wbmad-placeholder__spinner-bounce"></div>
			</div>
		</div>
		<div class="wbmad-placeholder__cardstack-tags">
			<div class="wbmad-placeholder__cardstack-heading"></div>
			<div class="wbmad-placeholder__cardstack-tag-list">
				<div class="wbmad-placeholder__cardstack-tag"></div>
				<div class="wbmad-placeholder__cardstack-tag"></div>
				<div class="wbmad-placeholder__cardstack-tag"></div>
				<div class="wbmad-placeholder__cardstack-tag"></div>
			</div>
		</div>
	</div>
	<div class="wbmad-placeholder__license"></div>
</div>
EOT;
	}

	private function testersOnly() {
		$extensionServices = new Services( MediaWikiServices::getInstance() );
		return $extensionServices->getExtensionConfig()->get( 'MachineVisionTestersOnly' );
	}
}
