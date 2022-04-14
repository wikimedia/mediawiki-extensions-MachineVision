<?php

namespace MediaWiki\Extension\MachineVision;

use InvalidArgumentException;
use LocalRepo;
use MediaWiki\Permissions\RestrictionStore;
use MediaWiki\Revision\RevisionStore;
use Title;
use Wikibase\DataModel\Entity\NumericPropertyId;

class TitleFilter {

	/** @var LocalRepo */
	private $localRepo;

	/** @var RestrictionStore */
	private $restrictionStore;

	/** @var RevisionStore */
	private $revisionStore;

	/** @var int */
	private $minImageWidth;

	/** @var int */
	private $maxExistingDepictsStatements;

	/** @var string[] */
	private $categoryBlocklist;

	/** @var string[] */
	private $templateBlocklist;

	/** @var string */
	private $depictsIdSerialization;

	/** @var bool */
	private $blocklistsActive = true;

	/**
	 * @param LocalRepo $localRepo
	 * @param RestrictionStore $restrictionStore
	 * @param RevisionStore $revisionStore
	 * @param int $minImageWidth min image width to qualify for labeling
	 * @param int $maxExistingDepictsStatements max # of existing depicts statements to qualify for
	 *  labeling
	 * @param array $categoryBlocklist omit images with these categories from labeling
	 * @param array $templateBlocklist omit images with these templates from labeling
	 * @param string $depictsIdSerialization depicts ID defined in WikibaseMediaInfo config
	 */
	public function __construct(
		LocalRepo $localRepo,
		RestrictionStore $restrictionStore,
		RevisionStore $revisionStore,
		$minImageWidth,
		$maxExistingDepictsStatements,
		array $categoryBlocklist,
		array $templateBlocklist,
		$depictsIdSerialization
	) {
		$this->localRepo = $localRepo;
		$this->restrictionStore = $restrictionStore;
		$this->revisionStore = $revisionStore;
		$this->minImageWidth = $minImageWidth;
		$this->maxExistingDepictsStatements = $maxExistingDepictsStatements;
		$this->categoryBlocklist = $categoryBlocklist;
		$this->templateBlocklist = $templateBlocklist;
		$this->depictsIdSerialization = $depictsIdSerialization;
	}

	/**
	 * Filter missing and redirect titles, and apply the exclusion rules specified in the CAT
	 * product doc, to wit:
	 * - No missing or redirect titles
	 * - No protected titles
	 * - No files that depict famous works of art, as determined by category membership or template
	 *    usage
	 * - No files with width under the configured minimum image width
	 * - No files with more than the configured maximum number of existing depicts (P180) statements
	 * @param string[] $filePageTitles
	 * @return string[]
	 */
	public function filterGoodTitles( array $filePageTitles ) {
		return array_filter( $filePageTitles, function ( $title ) {
			return $this->isGoodTitle( $title );
		} );
	}

	/**
	 * Return true if $titleText refers to a good image per the exclusion rules specified in the CAT
	 * product doc, to wit:
	 * - No missing or redirect titles
	 * - No protected titles
	 * - No files that depict famous works of art, as determined by category membership or template
	 *    usage
	 * - No files with width under the configured minimum image width
	 * - No files with more than the configured maximum number of existing depicts (P180) statements
	 * @param string|Title $title
	 * @return bool
	 */
	public function isGoodTitle( $title ) {
		if ( gettype( $title ) === 'string' ) {
			$title = Title::newFromText( $title, NS_FILE );
		}
		if ( !( $title instanceof Title ) ) {
			throw new InvalidArgumentException( '$title param must be a string or Title' );
		}
		return $this->filter( $title );
	}

	private function filter( Title $title ): bool {
		if ( !$title->exists() ) {
			return false;
		}
		if ( $title->isRedirect() ) {
			return false;
		}
		if ( $this->restrictionStore->isProtected( $title ) ) {
			return false;
		}
		$file = $this->localRepo->findFile( $title );
		if ( !$file ) {
			return false;
		}
		if ( !$file->getWidth() || $file->getWidth() < $this->minImageWidth ) {
			return false;
		}
		$revision = $this->revisionStore->getRevisionByTitle( $title );
		if ( $revision !== null && $revision->hasSlot( 'mediainfo' ) ) {
			$mediaInfoContent = $revision->getContent( 'mediainfo' );
			if ( $mediaInfoContent ) {
				// @phan-suppress-next-line PhanUndeclaredMethod
				$mediaInfo = $mediaInfoContent->getEntity();
				$statementList = $mediaInfo->getStatements();
				$propertyId = new NumericPropertyId( $this->depictsIdSerialization );
				$depictsStatements = $statementList->getByPropertyId( $propertyId );
				if ( $depictsStatements->count() > $this->maxExistingDepictsStatements ) {
					return false;
				}
			}
		}
		if ( $this->blocklistsActive && count( $this->categoryBlocklist ) ) {
			$categories = array_keys( $title->getParentCategories() );
			if ( count( array_intersect( $categories, $this->categoryBlocklist ) ) ) {
				return false;
			}
		}
		if ( $this->blocklistsActive && count( $this->templateBlocklist ) ) {
			$templates = array_map( static function ( Title $title ) {
				return $title->getPrefixedDBKey();
			}, $title->getTemplateLinksFrom() );
			if ( count( array_intersect( $templates, $this->templateBlocklist ) ) ) {
				return false;
			}
		}
		return true;
	}

	public function disableBlocklists() {
		$this->blocklistsActive = false;
	}

	public function enableBlocklists() {
		$this->blocklistsActive = true;
	}

}
