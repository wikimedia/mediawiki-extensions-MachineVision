<?php

namespace MediaWiki\Extension\MachineVision\Handler;

use Html;
use IContextSource;
use LocalFile;
use MediaWiki\Extension\MachineVision\Repository;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

abstract class WikidataIdHandler implements Handler {

	use LoggerAwareTrait;

	/** @var Repository */
	private $repository;

	/**
	 * @param Repository $repository
	 */
	public function __construct( Repository $repository ) {
		$this->repository = $repository;
		$this->setLogger( new NullLogger() );
	}

	/**
	 * @return Repository
	 */
	protected function getRepository() {
		return $this->repository;
	}

	/**
	 * Expose label suggestions in page info for transparency and developer convenience.
	 * @param IContextSource $context
	 * @param LocalFile $file
	 * @param array &$pageInfo
	 */
	public function handleInfoAction( IContextSource $context, LocalFile $file, array &$pageInfo ) {
		$labels = $this->repository->getLabels( $file->getSha1() );
		if ( $labels ) {
			// FIXME there's probably a nice way to build human-readable description of Q-items
			$labels = array_map( function ( $label ) {
				// @phan-suppress-next-line SecurityCheck-DoubleEscaped
				return Html::element( 'a', [
					'href' => 'https://www.wikidata.org/wiki/' . htmlentities( $label ),
				], $label );
			}, $labels );
			// TODO there should probably be a structured-data or similar header but this extension
			// is not the right place for that
			$pageInfo['header-properties'][] = [
				$context->msg( 'machinevision-pageinfo-field-suggested-labels' )->escaped(),
				$context->getLanguage()->commaList( $labels ),
			];
		}
	}

}
