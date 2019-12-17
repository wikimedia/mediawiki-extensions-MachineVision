<?php

namespace MediaWiki\Extension\MachineVision\Handler;

use LocalFile;
use MediaWiki\Extension\MachineVision\MachineVisionEntitySaveException;
use MediaWiki\Extension\Machinevision\Util;
use MediaWiki\Revision\RevisionStore;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use User;
use Wikibase\ClaimSummaryBuilder;
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
use Wikibase\SummaryFormatter;
use WikiMap;

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

	/** @var ClaimSummaryBuilder */
	// @phan-suppress-next-line PhanUndeclaredTypeProperty
	private $claimSummaryBuilder;

	/** @var SummaryFormatter */
	// @phan-suppress-next-line PhanUndeclaredTypeProperty
	private $summaryFormatter;

	/** @var string */
	private $depictsIdSerialization;

	/**
	 * WikidataDepictsSetter constructor.
	 * @param RevisionStore $revisionStore
	 * @param MediaInfoByLinkedTitleLookup $mediaInfoByLinkedTitleLookup
	 * @param EntityLookup $entityLookup
	 * @param MediawikiEditEntityFactory $mediawikiEditEntityFactory
	 * @param StatementChangeOpFactory $statementChangeOpFactory
	 * @param ClaimSummaryBuilder $claimSummaryBuilder
	 * @param SummaryFormatter $summaryFormatter
	 * @param string $depictsIdSerialization depicts ID defined in WikibaseMediaInfo config
	 * @suppress PhanUndeclaredTypeParameter
	 */
	public function __construct(
		RevisionStore $revisionStore,
		MediaInfoByLinkedTitleLookup $mediaInfoByLinkedTitleLookup,
		EntityLookup $entityLookup,
		MediawikiEditEntityFactory $mediawikiEditEntityFactory,
		StatementChangeOpFactory $statementChangeOpFactory,
		ClaimSummaryBuilder $claimSummaryBuilder,
		SummaryFormatter $summaryFormatter,
		$depictsIdSerialization
	) {
		$this->revisionStore = $revisionStore;
		$this->mediaInfoByLinkedTitleLookup = $mediaInfoByLinkedTitleLookup;
		$this->entityLookup = $entityLookup;
		$this->editEntityFactory = $mediawikiEditEntityFactory;
		$this->statementChangeOpFactory = $statementChangeOpFactory;
		$this->claimSummaryBuilder = $claimSummaryBuilder;
		$this->summaryFormatter = $summaryFormatter;
		$this->depictsIdSerialization = $depictsIdSerialization;

		$this->logger = new NullLogger();
	}

	/**
	 * Adds a depicts statement to the mediainfo entity associated with $file.
	 * @param User $user user who approved the label
	 * @param LocalFile $file
	 * @param string $label Wikidata ID associated with the label
	 * @param string $token CSRF token for creating the revision
	 * @throws MachineVisionEntitySaveException
	 * @suppress PhanUndeclaredClassMethod
	 */
	public function addDepicts( User $user, LocalFile $file, $label, $token ) {
		$title = $file->getTitle();
		$wikiId = WikiMap::getWikiIdFromDbDomain( WikiMap::getCurrentWikiDbDomain() );
		$mediaInfoId = $this->mediaInfoByLinkedTitleLookup
			->getEntityIdForLinkedTitle( $wikiId, $title->getPrefixedText() );
		$mediaInfo = $this->entityLookup->hasEntity( $mediaInfoId )
			? $this->entityLookup->getEntity( $mediaInfoId )
			: null;

		$isNew = false;
		if ( !$mediaInfo ) {
			$mediaInfo = new MediaInfo( $mediaInfoId );
			$isNew = true;
		}

		$mainSnak = $this->getDepictsSnak( $label );

		if ( !$this->depictExists( $mainSnak, $mediaInfo ) ) {
			// Qualifiers go here in the Statement constructor, if we need or want them
			$statement = new Statement( $mainSnak );
			$summary = $this->claimSummaryBuilder->buildClaimSummary( null, $statement );
			$formattedSummary = $this->summaryFormatter->formatSummary( $summary );

			$changeOp = $this->statementChangeOpFactory->newSetStatementOp( $statement );
			$this->validateChangeOp( $changeOp, $mediaInfo );
			$changeOp->apply( $mediaInfo, $summary );

			$editEntity = $this->editEntityFactory->newEditEntity( $user, $mediaInfoId );
			$flags = $isNew ? EDIT_NEW : EDIT_UPDATE;
			$status = $editEntity->attemptSave( $mediaInfo, $formattedSummary, $flags, $token,
				null, [ Util::getDepictsTag() ] );
			if ( !$status->isOK() ) {
				throw new MachineVisionEntitySaveException( $status );
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
