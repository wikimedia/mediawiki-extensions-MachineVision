<?php

namespace MediaWiki\Extension\MachineVision;

use LocalRepo;
use MediaWiki\Storage\RevisionStore;
use Title;
use Wikibase\DataModel\Entity\PropertyId;

class TitleFilter {

	/** @var LocalRepo */
	private $localRepo;

	/** @var RevisionStore */
	private $revisionStore;

	/** @var int */
	private $minImageWidth;

	/** @var int */
	private $maxExistingDepictsStatements;

	/** @var string[] */
	private $categoryBlacklist;

	/** @var string[] */
	private $templateBlacklist;

	/** @var string */
	private $depictsIdSerialization;

	/**
	 * TitleFilter constructor.
	 * @param LocalRepo $localRepo
	 * @param RevisionStore $revisionStore
	 * @param int $minImageWidth min image width to qualify for labeling
	 * @param int $maxExistingDepictsStatements max # of existing depicts statements to qualify for
	 *  labeling
	 * @param array $categoryBlacklist omit images with these categories from labeling
	 * @param array $templateBlacklist omit images with these templates from labeling
	 * @param string $depictsIdSerialization depicts ID defined in WikibaseMediaInfo config
	 */
	public function __construct(
		LocalRepo $localRepo,
		RevisionStore $revisionStore,
		$minImageWidth,
		$maxExistingDepictsStatements,
		array $categoryBlacklist,
		array $templateBlacklist,
		$depictsIdSerialization
	) {
		$this->localRepo = $localRepo;
		$this->revisionStore = $revisionStore;
		$this->minImageWidth = $minImageWidth;
		$this->maxExistingDepictsStatements = $maxExistingDepictsStatements;
		$this->categoryBlacklist = $categoryBlacklist;
		$this->templateBlacklist = $templateBlacklist;
		$this->depictsIdSerialization = $depictsIdSerialization;
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
	 * @param string $titleText
	 * @return bool
	 */
	public function isGoodTitle( $titleText ) {
		$title = Title::newFromText( $titleText, NS_FILE );
		if ( !$title->exists() ) {
			return false;
		}
		if ( $title->isRedirect() ) {
			return false;
		}
		if ( $title->isProtected() ) {
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
		if ( $revision->hasSlot( 'mediainfo' ) ) {
			$mediaInfoContent = $revision->getContent( 'mediainfo' );
			if ( $mediaInfoContent ) {
				// @phan-suppress-next-line PhanUndeclaredMethod
				$mediaInfo = $mediaInfoContent->getEntity();
				$statementList = $mediaInfo->getStatements();
				$propertyId = new PropertyId( $this->depictsIdSerialization );
				$depictsStatements = $statementList->getByPropertyId( $propertyId );
				if ( $depictsStatements->count() > $this->maxExistingDepictsStatements ) {
					return false;
				}
			}
		}
		if ( count( $this->categoryBlacklist ) ) {
			$categories = array_keys( $title->getParentCategories() );
			if ( count( array_intersect( $categories, $this->categoryBlacklist ) ) ) {
				return false;
			}
		}
		if ( count( $this->templateBlacklist ) ) {
			$templates = $title->getTemplateLinksFrom();
			if ( count( array_intersect( $templates, $this->templateBlacklist ) ) ) {
				return false;
			}
		}
		return true;
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

}
