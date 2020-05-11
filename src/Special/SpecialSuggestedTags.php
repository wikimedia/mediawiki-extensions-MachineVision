<?php

namespace MediaWiki\Extension\MachineVision\Special;

use LocalRepo;
use MediaWiki\Extension\MachineVision\Handler\LabelResolver;
use MediaWiki\Extension\MachineVision\Repository;
use MediaWiki\Extension\MachineVision\Services;
use MediaWiki\MediaWikiServices;
use SpecialPage;

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

		// Page intro.
		$out->addHTML(
			'<div class="wbmad-intro"><p>' .
			$this->msg( 'machinevision-machineaidedtagging-intro' )->parse() .
			'</p></div>'
		);

		// Vue.js app root
		$out->addHTML( '<div id="wbmad-app"></div>' );

		// Placeholder element, to be removed once UI finishes loading.
		$user = $this->getUser();
		$placeholder = $user->isAnon() ?
			'<div class="wbmad-placeholder wbmad-placeholder-anonymous"></div>' :
			$this->getPlaceholderMarkup();
		$out->addHTML( $placeholder );

		// no-JS fallback
		$out->addHTML( '<div class="wbmad-client-nojs">' );
		$out->addHTML( '<p class="warningbox">' .
			$this->msg( 'machinevision-javascript-required' )->parse() . '</p>' );
		$out->addHTML( '</div>' );
		$out->addModuleStyles( 'ext.MachineVision.init' );

		// Generate login message with link with returnto URL query parameter.
		// Params aren't supported in the JS version of the messages API so we
		// have parse it here then pass it to the JS.
		$loginMessage = wfMessage( 'machinevision-login-message' )->parse();
		$this->getOutput()->addJsConfigVars( 'wgMVSuggestedTagsLoginMessage', $loginMessage );

		$this->getOutput()->addModules( 'ext.MachineVision' );
	}

	/** @inheritDoc */
	public function getDescription() {
		return $this->msg( 'machinevision-machineaidedtagging' )->parse();
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
