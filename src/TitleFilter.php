<?php

namespace MediaWiki\Extension\MachineVision;

use LocalRepo;
use Title;

class TitleFilter {

	/** @var LocalRepo */
	private $localRepo;

	/** @var int */
	private $minImageWidth;

	/** @var string[] */
	private $categoryBlacklist;

	/** @var string[] */
	private $templateBlacklist;

	/**
	 * TitleFilter constructor.
	 * @param LocalRepo $localRepo
	 * @param int $minImageWidth
	 * @param array $categoryBlacklist
	 * @param array $templateBlacklist
	 */
	public function __construct(
		LocalRepo $localRepo,
		$minImageWidth,
		array $categoryBlacklist,
		array $templateBlacklist
	) {
		$this->localRepo = $localRepo;
		$this->minImageWidth = $minImageWidth;
		$this->categoryBlacklist = $categoryBlacklist;
		$this->templateBlacklist = $templateBlacklist;
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
