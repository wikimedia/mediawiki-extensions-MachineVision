<?php

namespace MediaWiki\Extension\MachineVision\Notifications;

use EchoEventPresentationModel;
use Message;
use MWException;

class SuggestionsReadyPresentationModel extends EchoEventPresentationModel {
	/**
	 * @return string
	 */
	public function getIconType() {
		return 'suggestions-ready';
	}

	/**
	 * @return Message
	 */
	public function getHeaderMessage() {
		$user = $this->getViewingUserForGender();
		$title = $this->event->getTitle()->getText();

		if ( $this->isBundled() ) {
			return $this->msg(
				'echo-machinevision-suggestions-ready-notification-header',
				$user
			);
		} else {
			return $this->msg(
				'echo-machinevision-suggestions-ready-notification-header-compact',
				$user,
				$title
			);
		}
	}

	/**
	 * @return Message
	 */
	public function getCompactHeaderMessage() {
		$user = $this->getViewingUserForGender();
		$title = $this->event->getTitle()->getText();

		return $this->msg(
			'echo-machinevision-suggestions-ready-notification-header-compact',
			$user,
			$title
		);
	}

	/**
	 * @return bool|Message
	 */
	public function getBodyMessage() {
		$user = $this->getViewingUserForGender();

		return $this->msg(
			'echo-machinevision-suggestions-ready-notification-body',
			$user
		);
	}

	/**
	 * @return array|false
	 * @throws MWException
	 */
	public function getPrimaryLink() {
		$url = \SpecialPage::getTitleFor( 'SuggestedTags', false, 'user' );

		return [
			'url' => $url->getLinkURL(),
			'label' => $this->msg( 'machinevision-machineaidedtagging' )
		];
	}
}
