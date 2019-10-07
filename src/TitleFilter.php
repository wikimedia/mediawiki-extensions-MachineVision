<?php

namespace MediaWiki\Extension\MachineVision;

use LocalRepo;
use Title;

class TitleFilter {

	public static function instance() {
		return new TitleFilter();
	}

	/**
	 * Filter missing and redirect titles, and apply the exclusion rules specified in the CAT
	 * product doc, to wit:
	 * - No protected titles
	 * - No files that depict famous works of art, as determined by category membership
	 * - No files with width under 150px
	 * @param string[] $filePageTitles
	 * @param LocalRepo $localRepo
	 * @param int $minImageWidth
	 * @param string[] $categoryBlacklist
	 * @param string[] $templateBlacklist
	 * @return string[]
	 */
	public function filterGoodTitles(
		array $filePageTitles,
		LocalRepo $localRepo,
		$minImageWidth,
		array $categoryBlacklist,
		array $templateBlacklist
	) {
		return array_filter( $filePageTitles, function ( $title )
			use ( $localRepo, $minImageWidth, $categoryBlacklist, $templateBlacklist ) {
			$title = Title::newFromText( $title, NS_FILE );
			if ( !$title->exists() ) {
				return false;
			}
			if ( $title->isRedirect() ) {
				return false;
			}
			if ( $title->isProtected() ) {
				return false;
			}
			$file = $localRepo->findFile( $title );
			if ( !$file ) {
				return false;
			}
			if ( !$file->getWidth() || $file->getWidth() < $minImageWidth ) {
				return false;
			}
			if ( count( $categoryBlacklist ) ) {
				$categories = array_keys( $title->getParentCategories() );
				if ( count( array_intersect( $categories, $categoryBlacklist ) ) ) {
					return false;
				}
			}
			if ( count( $templateBlacklist ) ) {
				$templates = $title->getTemplateLinksFrom();
				if ( count( array_intersect( $templates, $templateBlacklist ) ) ) {
					return false;
				}
			}
			return true;
		} );
	}

}
