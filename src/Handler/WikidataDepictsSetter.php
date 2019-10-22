<?php

namespace MediaWiki\Extension\MachineVision\Handler;

use LocalFile;
use MediaWiki\Extension\MachineVision\MachineVisionEntitySaveException;
use MediaWiki\Revision\RevisionStore;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use User;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Snak\SnakList;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\MediaInfo\DataModel\MediaInfo;
use Wikibase\MediaInfo\Services\MediaInfoByLinkedTitleLookup;
use Wikibase\Repo\ChangeOp\ChangeOp;
use Wikibase\Repo\ChangeOp\ChangeOpValidationException;
use Wikibase\Repo\ChangeOp\StatementChangeOpFactory;
use Wikibase\Repo\EditEntity\MediawikiEditEntityFactory;
use Wikibase\Summary;
use Wikibase\SummaryFormatter;

/**
 * Interacts with Wikidata to set Depicts (P180) statements on MediaInfo items.
 * TODO: This should probably be abstracted into a generic, configurable StatementSetter or some
 * such thing in due time.
 * TODO: Test me
 */
class WikidataDepictsSetter implements LoggerAwareInterface {

	use LoggerAwareTrait;

	/** @var RevisionStore */
	private $revisionStore;

	/** @var MediaInfoByLinkedTitleLookup */
	// @phan-suppress-next-line PhanUndeclaredTypeProperty
	private $mediaInfoByLinkedTitleLookup;

	/** @var EntityLookup */
	private $entityLookup;

	/** @var MediawikiEditEntityFactory */
	// @phan-suppress-next-line PhanUndeclaredTypeProperty
	private $editEntityFactory;

	/** @var StatementChangeOpFactory */
	// @phan-suppress-next-line PhanUndeclaredTypeProperty
	private $statementChangeOpFactory;

	/** @var SummaryFormatter */
	// @phan-suppress-next-line PhanUndeclaredTypeProperty
	private $summaryFormatter;

	/** @var string */
	private $depictsIdSerialization;

	/** @var string[] */
	private $tags;

	/**
	 * WikidataDepictsSetter constructor.
	 * @param RevisionStore $revisionStore
	 * @param MediaInfoByLinkedTitleLookup $mediaInfoByLinkedTitleLookup
	 * @param EntityLookup $entityLookup
	 * @param MediawikiEditEntityFactory $mediawikiEditEntityFactory
	 * @param StatementChangeOpFactory $statementChangeOpFactory
	 * @param SummaryFormatter $summaryFormatter
	 * @param string $depictsIdSerialization depicts ID defined in WikibaseMediaInfo config
	 * @param array $tags array of tags to be set on SDC revisions
	 * @suppress PhanUndeclaredTypeParameter
	 */
	public function __construct(
		RevisionStore $revisionStore,
		MediaInfoByLinkedTitleLookup $mediaInfoByLinkedTitleLookup,
		EntityLookup $entityLookup,
		MediawikiEditEntityFactory $mediawikiEditEntityFactory,
		StatementChangeOpFactory $statementChangeOpFactory,
		SummaryFormatter $summaryFormatter,
		$depictsIdSerialization,
		$tags
	) {
		$this->revisionStore = $revisionStore;
		$this->mediaInfoByLinkedTitleLookup = $mediaInfoByLinkedTitleLookup;
		$this->entityLookup = $entityLookup;
		$this->editEntityFactory = $mediawikiEditEntityFactory;
		$this->statementChangeOpFactory = $statementChangeOpFactory;
		$this->summaryFormatter = $summaryFormatter;
		$this->depictsIdSerialization = $depictsIdSerialization;
		$this->tags = $tags;

		$this->logger = new NullLogger();
	}

	/**
	 * Adds a depicts statement to the mediainfo entity associated with $file.
	 * @param User $user user who approved the label
	 * @param LocalFile $file
	 * @param string $label Wikidata ID associated with the label
	 * @param string $token CSRF token for creating the revision
	 * @suppress PhanUndeclaredClassMethod
	 */
	public function addDepicts( User $user, LocalFile $file, $label, $token ) {
		$title = $file->getTitle();
		$revision = $this->revisionStore->getRevisionByTitle( $title );

		$mediaInfoId = $this->mediaInfoByLinkedTitleLookup
			->getEntityIdForLinkedTitle( 'commonswiki', $title->getPrefixedText() );
		$mediaInfo = $this->entityLookup->hasEntity( $mediaInfoId )
			? $this->entityLookup->getEntity( $mediaInfoId )
			: null;

		$rawSummary = new Summary( 'machineaideddepicts', 'approved' );
		$summary = $this->summaryFormatter->formatSummary( $rawSummary );
		$flags = $mediaInfo ? EDIT_UPDATE : EDIT_NEW;

		$mediaInfo = $mediaInfo ?: new MediaInfo( $mediaInfoId );
		$mainSnak = $this->getDepictsSnak( $label );

		if ( !$this->depictExists( $mainSnak, $mediaInfo ) ) {
			$changeOp = $this->getChangeOp( $mainSnak );
			$this->validateChangeOp( $changeOp, $mediaInfo );
			$changeOp->apply( $mediaInfo, $rawSummary );
			$editEntity = $this->editEntityFactory
				->newEditEntity( $user, $mediaInfoId, $revision->getId() );
			$status = $editEntity->attemptSave( $mediaInfo, $summary, $flags, $token, null, $this->tags );
			if ( !$status->isOK() ) {
				throw new MachineVisionEntitySaveException( $status->getMessage() );
			}
		} else {
			$this->logger->info(
				'Depict ' . $label . ' already set to file'
			);
		}
	}

	/**
	 * Check if label/depict exists for given MediaInfo using mainSnak
	 *
	 * @param PropertyValueSnak $mainSnak
	 * @param MediaInfo $mediaInfo
	 * @return bool
	 * @suppress PhanUndeclaredClassMethod,PhanUndeclaredTypeParameter
	 */
	private function depictExists( PropertyValueSnak $mainSnak, MediaInfo $mediaInfo ): bool {
		$snakList = new SnakList( $mediaInfo->getStatements()->getAllSnaks() );
		return $snakList->hasSnak( $mainSnak );
	}

	/**
	 * Get the main snak for the depicts statement to add.
	 * Note: This hard-codes P180, the property ID for "depicts" on Wikidata. It is unlikely that
	 * P180 represents "depicts" in your development environment, and fairly likely that neither
	 * P180 nor the item ID represented by $label exist at all. For testing, consider hard-coding
	 * known good values in their place here, e.g., P1 in place of P180 and Q1 in place of $label.
	 * @param string $label Wikidata item ID associated with the label
	 * @return PropertyValueSnak
	 */
	private function getDepictsSnak( $label ) {
		$depicts = new PropertyId( $this->depictsIdSerialization );
		$itemId = new ItemId( $label );
		$itemIdValue = new EntityIdValue( $itemId );
		return new PropertyValueSnak( $depicts, $itemIdValue );
	}

	/**
	 * Get the statement change operation object.
	 * @param PropertyValueSnak $mainSnak Depict Snak associated with the Wikidata Item ID
	 * @return ChangeOp
	 * @suppress PhanUndeclaredClassMethod,PhanUndeclaredTypeReturnType
	 */
	private function getChangeOp( $mainSnak ) {
		// TODO: Qualifiers go here in the Statement constructor, if we need or want them
		$statement = new Statement( $mainSnak );
		return $this->statementChangeOpFactory->newSetStatementOp( $statement );
	}

	/**
	 * Validate the newly created ChangeOp with respect to the MediaInfo object.
	 * @param ChangeOp $changeOp
	 * @param EntityDocument $mediaInfo
	 * @suppress PhanUndeclaredClassMethod,PhanUndeclaredTypeParameter
	 */
	private function validateChangeOp( ChangeOp $changeOp, EntityDocument $mediaInfo ) {
		$result = $changeOp->validate( $mediaInfo );
		if ( !$result->isValid() ) {
			throw new ChangeOpValidationException( $result );
		}
	}

}
