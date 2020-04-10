<?php

namespace MediaWiki\Extension\MachineVision\Api;

use ApiBase;
use ApiMain;
use IDBAccessObject;
use MediaWiki\Extension\MachineVision\Handler\LabelResolver;
use MediaWiki\Extension\MachineVision\Handler\Registry;
use MediaWiki\Extension\MachineVision\Repository;
use MediaWiki\Extension\MachineVision\Services;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Storage\NameTableStore;
use Message;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use RepoGroup;
use Title;

class ApiReviewImageLabels extends ApiBase implements LoggerAwareInterface {

	use LoggerAwareTrait;

	private static $reviewActions = [
		'accept' => Repository::REVIEW_ACCEPTED,
		'reject' => Repository::REVIEW_REJECTED,
	];

	/** @var RepoGroup */
	private $repoGroup;

	/** @var NameTableStore */
	private $nameTableStore;

	/** @var Repository */
	private $repository;

	/** @var Registry */
	private $registry;

	/** @var LabelResolver */
	private $labelResolver;

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
			$extensionServices->getHandlerRegistry(),
			$extensionServices->getLabelResolver()
		);
	}

	/**
	 * @param ApiMain $main
	 * @param string $moduleName
	 * @param RepoGroup $repoGroup
	 * @param NameTableStore $nameTableStore
	 * @param Repository $repository
	 * @param Registry $registry
	 * @param LabelResolver $labelResolver
	 */
	public function __construct(
		ApiMain $main,
		$moduleName,
		RepoGroup $repoGroup,
		NameTableStore $nameTableStore,
		Repository $repository,
		Registry $registry,
		LabelResolver $labelResolver
	) {
		parent::__construct( $main, $moduleName );
		$this->repoGroup = $repoGroup;
		$this->nameTableStore = $nameTableStore;
		$this->repository = $repository;
		$this->registry = $registry;
		$this->labelResolver = $labelResolver;

		$this->setLogger( LoggerFactory::getInstance( 'machinevision' ) );
	}

	/** @inheritDoc */
	public function execute() {
		$this->checkUserRightsAny( 'imagelabel-review' );

		// TODO move some of this to Handler?
		$params = $this->extractRequestParams();
		$votes = $this->collectAndValidateVotes( $params );
		$userId = $this->getUser()->getId();
		$ts = (int)( microtime( true ) * 10000 );

		$filename = $params['filename'];
		$file = $this->getFile( $filename );
		$sha1 = $file->getSha1();

		$result = [ 'success' => [] ];

		foreach ( $votes as $vote ) {
			$label = $vote['label'];
			$review = $vote['review'];

			$oldState = $this->repository->getLabelState( $sha1, $label,
				IDBAccessObject::READ_EXCLUSIVE );
			$newState = self::$reviewActions[$review];

			if ( !$this->validateLabelState( $filename, $label, $oldState, $newState ) ) {
				continue;
			}

			$success = $this->repository->setLabelState( $sha1, $label, $newState,
				$userId, $ts );
			if ( $success ) {
				$result['success'][$label] = $review;
			} else {
				$this->addWarning(
					$this->getContext()->msg( 'apiwarn-reviewimagelabels-setlabelstate-failed',
						$review, $label )
				);
				if ( !array_key_exists( 'failure', $result ) ) {
					$result['failure'] = [];
				}
				$result['failure'][$label] = $review;
			}
		}

		$this->getResult()->addValue( null, $this->getModuleName(), [ 'result' => $result ] );
	}

	private function collectAndValidateVotes( $params ) {
		$filename = $params['filename'];
		$requiredVoteParams = [ 'label', 'review' ];

		foreach ( $requiredVoteParams as $param ) {
			$this->requireOnlyOneParameter( $params, $param, 'batch' );
		}

		if ( $params['batch'] ) {
			$votes = $this->getBatchedVotes( $filename, $params['batch'] );
			foreach ( $votes as $vote ) {
				$this->requireAtLeastOneBatchParameter( $vote, 'filename' );
				foreach ( $requiredVoteParams as $param ) {
					$this->requireAtLeastOneBatchParameter( $vote, $param );
				}
			}
		} else {
			foreach ( $requiredVoteParams as $param ) {
				$this->requireAtLeastOneParameter( $params, $param );
			}
			$votes = [];
			$votes[] = [
				'filename' => $filename,
				'label' => $params['label'],
				'review' => $params['review'],
			];
		}
		return $votes;
	}

	private function getFile( $filename ) {
		$title = Title::newFromText( $filename, NS_FILE );
		if ( !$title ) {
			$this->dieWithError(
				wfMessage( 'apierror-reviewimagelabels-invalidfile', $filename )
			);
		}
		// @phan-suppress-next-line PhanTypeMismatchArgumentNullable T240141
		$file = $this->repoGroup->getLocalRepo()->findFile( $title );
		if ( !$file ) {
			$this->dieWithError(
				wfMessage( 'apierror-reviewimagelabels-invalidfile', $filename )
			);
		}
		return $file;
	}

	/**
	 * Check that the review states for the submitted vote are valid. If the label is not in the
	 * DB, we'll throw. If the label isn't in UNREVIEWED state in the DB, we'll log a warning. If
	 * the user isn't attempting to reject a previously approved label, we'll allow the vote to
	 * go forward for further processing.
	 * @param string $filename
	 * @param string $label
	 * @param int $oldState
	 * @param int $newState
	 * @return bool true if the vote should be processed
	 */
	private function validateLabelState( $filename, $label, $oldState, $newState ) {
		if ( $oldState === false ) {
			$this->dieWithError(
				wfMessage( 'apierror-reviewimagelabels-invalidlabel', $filename, $label )
			);
		}
		$validOldStates = [ Repository::REVIEW_UNREVIEWED, Repository::REVIEW_WITHHELD_POPULAR ];
		if (
			!in_array( $oldState, $validOldStates, true ) &&
			// handle double-submits gracefully
			$oldState !== $newState
		) {
			$this->logger->warning(
				"Label $label is already reviewed for file $filename",
				[
					'filename' => $filename,
					'label' => $label,
					'oldState' => $oldState,
					'newState' => $newState,
					'caller' => __METHOD__,
				]
			);
		}
		return !( $oldState === Repository::REVIEW_ACCEPTED &&
			$newState === Repository::REVIEW_REJECTED );
	}

	/**
	 * Decode, validate and normalize the 'batch' parameter.
	 * TODO: This is copied nearly verbatim from ApiTrait in ReadingLists, with only message keys
	 * changed. Have a chat with Gergo about moving it into a library, and/or file a task.
	 * @param string $filename Filename to which the votes apply
	 * @param string $rawBatch The raw value of the 'batch' parameter.
	 * @return array Array of operations, each consisting of a flat associative array.
	 */
	private function getBatchedVotes( $filename, $rawBatch ) {
		$batch = json_decode( $rawBatch, true );

		// Must be a real array, and not empty.
		if ( !is_array( $batch ) || $batch !== array_values( $batch ) || !$batch ) {
			if ( json_last_error() ) {
				$jsonError = json_last_error_msg();
				$this->dieWithError( wfMessage( 'apierror-reviewimagelabels-batch-invalid-json',
					wfEscapeWikiText( $jsonError ) ) );
			}
			$this->dieWithError( 'apierror-reviewimagelabels-batch-invalid-structure' );
		}

		// Limit the batch size to a reasonable maximum.
		// @phan-suppress-next-line PhanTypeMismatchArgumentNullableInternal T240141
		if ( count( $batch ) > ApiBase::LIMIT_BIG1 ) {
			$msg = wfMessage( 'apierror-reviewimagelabels-batch-toomanyvalues',
				ApiBase::LIMIT_BIG1 );
			$this->dieWithError( $msg, 'toomanyvalues' );
		}

		$request = $this->getContext()->getRequest();
		foreach ( $batch as &$op ) {
			$op['filename'] = $filename;
			// Each batch operation must be an associative array with scalar fields.
			if (
				!is_array( $op )
				|| array_values( $op ) === $op
				|| array_filter( $op, 'is_scalar' ) !== $op
			) {
				$this->dieWithError( 'apierror-reviewimagelabels-batch-invalid-structure' );
			}
			// JSON-escaped characters might have skipped WebRequest's normalization, repeat it.
			array_walk_recursive( $op, function ( &$value ) use ( $request ) {
				if ( is_string( $value ) ) {
					$value = $request->normalizeUnicode( $value );
				}
			} );
		}
		// @phan-suppress-next-line PhanTypeMismatchReturnNullable T240141
		return $batch;
	}

	/**
	 * Validate a single operation in the 'batch' parameter of write APIs. Works the same way as
	 * requireAtLeastOneParameter.
	 * TODO: This is copied nearly verbatim from ApiTrait in ReadingLists, with only message keys
	 * changed. Have a chat with Gergo about moving it into a library, and/or file a task.
	 * @param array $op
	 * @param string $param,...
	 */
	// @codingStandardsIgnoreLine MediaWiki.WhiteSpace.SpaceBeforeSingleLineComment.NewLineComment
	protected function requireAtLeastOneBatchParameter( array $op, $param /*...*/ ) {
		$required = func_get_args();
		array_shift( $required );

		$intersection = array_intersect(
			array_keys( array_filter( $op, function ( $val ) {
				return $val !== null && $val !== false;
			} ) ),
			$required
		);

		if ( count( $intersection ) == 0 ) {
			$this->dieWithError( [
				'apierror-reviewimagelabels-batch-missingparam-at-least-one-of',
				Message::listParam( array_map(
					function ( $p ) {
						return '<var>' . $this->encodeParamName( $p ) . '</var>';
					},
					array_values( $required )
				) ),
				count( $required ),
			], 'missingparam' );
		}
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
			],
			'review' => [
				ApiBase::PARAM_TYPE => array_keys( self::$reviewActions ),
				ApiBase::PARAM_HELP_MSG_PER_VALUE => [],
			],
			'batch' => [
				ApiBase::PARAM_TYPE => 'string',
			]
		];
	}

	/** @inheritDoc */
	protected function getExamplesMessages() {
		return [
			'action=reviewimagelabels&filename=Example.png&label=Q123&review=accept'
				=> 'apihelp-reviewimagelabels-example-1',
			'action=reviewimagelabels&filename=Example.png&batch='
				. '[{"label":"Q1","review":"accept"},{"label":"Q2","review":"reject"}]'
				=> 'apihelp-reviewimagelabels-example-2',
		];
	}

	/** @inheritDoc */
	public function getHelpUrls() {
		return 'https://www.mediawiki.org/wiki/Special:MyLanguage/Extension:MachineVision#API';
	}

	/** @inheritDoc */
	public function isInternal() {
		return true;
	}

}
