<?php

namespace MediaWiki\Extension\MachineVision\Api;

use ApiBase;
use ApiMain;
use IDBAccessObject;
use MediaWiki\Extension\MachineVision\Repository;
use MediaWiki\Extension\MachineVision\Services;
use MediaWiki\Extension\MachineVision\Special\SpecialImageLabeling;
use MediaWiki\MediaWikiServices;
use MediaWiki\Special\SpecialPageFactory;
use MediaWiki\Storage\NameTableStore;
use RepoGroup;
use Title;

class ApiReviewImageLabels extends ApiBase {

	private static $reviewActions = [
		'accept' => Repository::REVIEW_ACCEPTED,
		'reject' => Repository::REVIEW_REJECTED,
		'skip' => Repository::REVIEW_SKIPPED,
	];

	/** @var RepoGroup */
	private $repoGroup;

	/** @var NameTableStore */
	private $nameTableStore;

	/** @var Repository */
	private $repository;

	/** @var SpecialPageFactory */
	private $specialPageFactory;

	/**
	 * @param ApiMain $main
	 * @param string $moduleName
	 * @return self
	 */
	public static function factory( ApiMain $main, $moduleName ) {
		$services = MediaWikiServices::getInstance();
		$extensionServices = new Services( $services );
		return new self(
			$main,
			$moduleName,
			$services->getRepoGroup(),
			$extensionServices->getNameTableStore(),
			$extensionServices->getRepository(),
			$services->getSpecialPageFactory()
		);
	}

	/**
	 * @param ApiMain $main
	 * @param string $moduleName
	 * @param RepoGroup $repoGroup
	 * @param NameTableStore $nameTableStore
	 * @param Repository $repository
	 * @param SpecialPageFactory $specialPageFactory
	 */
	public function __construct(
		ApiMain $main,
		$moduleName,
		RepoGroup $repoGroup,
		NameTableStore $nameTableStore,
		Repository $repository,
		SpecialPageFactory $specialPageFactory
	) {
		parent::__construct( $main, $moduleName );
		$this->repoGroup = $repoGroup;
		$this->nameTableStore = $nameTableStore;
		$this->repository = $repository;
		$this->specialPageFactory = $specialPageFactory;
	}

	/** @inheritDoc */
	public function execute() {
		// TODO move some of this to Handler?
		$params = $this->extractRequestParams();

		$this->checkUserRightsAny( 'imagelabel-review' );

		$title = Title::newFromText( $params['filename'], NS_FILE );
		if ( !$title ) {
			$this->dieWithError( wfMessage( 'apierror-reviewimagelabels-invalidfile',
				$params['filename'] ) );
		}
		$file = $this->repoGroup->getLocalRepo()->findFile( $title );
		if ( !$file ) {
			$this->dieWithError( wfMessage( 'apierror-reviewimagelabels-invalidfile',
				$params['filename'] ) );
		}

		$sha1 = $file->getSha1();
		$oldState = $this->repository->getLabelState( $sha1, $params['label'],
			IDBAccessObject::READ_LOCKING );
		$newState = self::$reviewActions[$params['review']];
		if ( $oldState === false ) {
			$this->dieWithError( wfMessage( 'apierror-reviewimagelabels-invalidlabel',
				$params['filename'], $params['label'] ) );
		} elseif (
			$oldState !== Repository::REVIEW_UNREVIEWED
			// handle double-submits gracefully
			&& $oldState !== $newState
		) {
			$this->dieWithError( wfMessage( 'apierror-reviewimagelabels-invalidstate',
				$params['filename'], $params['label'], $params['review'] ) );
		}

		$success = $this->repository->setLabelState( $sha1, $params['label'], $newState );
		if ( $success ) {
			// Remove the page from the review queue, if there's a persisted queue.
			/** @var SpecialImageLabeling $queryPage */
			$queryPage = $this->specialPageFactory->getPage( 'ImageLabeling' );
			// @phan-suppress-next-line PhanUndeclaredMethod
			$queryPage->delete( $title );
		}

		$this->getResult()->addValue( null, $this->getModuleName(),
			[ 'result' => $success ? 'success' : 'failure' ] );
	}

	/** @inheritDoc */
	public function isWriteMode() {
		return true;
	}

	/** @inheritDoc */
	public function needsToken() {
		return 'csrf';
	}

	/** @inheritDoc */
	protected function getAllowedParams() {
		return [
			'filename' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true,
			],
			'label' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true,
			],
			'review' => [
				ApiBase::PARAM_TYPE => array_keys( self::$reviewActions ),
				ApiBase::PARAM_REQUIRED => true,
				ApiBase::PARAM_HELP_MSG_PER_VALUE => [],
			],
		];
	}

	/** @inheritDoc */
	protected function getExamplesMessages() {
		return [
			'action=reviewimagelabels&filename=Example.png&label=Q123&review=accept'
				=> 'apihelp-reviewimagelabels-example-1',
		];
	}

	/** @inheritDoc */
	public function getHelpUrls() {
		return 'https://www.mediawiki.org/wiki/Special:MyLanguage/Extension:MachineVision#API';
	}
}
