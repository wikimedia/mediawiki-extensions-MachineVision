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

	/** @var LabelResolver */
	private $labelResolver;

	/**
	 * @param Repository $repository
	 * @param LabelResolver $labelResolver
	 */
	public function __construct( Repository $repository, LabelResolver $labelResolver ) {
		$this->repository = $repository;
		$this->labelResolver = $labelResolver;
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
	 * TODO: We shouldn't need to instantiate a Handler for this. Handle it directly in the
	 * InfoAction hook handler.
	 * @param IContextSource $context
	 * @param LocalFile $file
	 * @param array &$pageInfo
	 */
	public function handleInfoAction( IContextSource $context, LocalFile $file, array &$pageInfo ) {
		$labelIds = $this->repository->getLabels( $file->getSha1() );
		if ( $labelIds ) {
			// TODO: Merge with EntityLookup/i18n patch
			$labels = $this->labelResolver->resolve( $labelIds, 'en' );
			$wdItemLinks = array_map( function ( $id ) use ( $labels ) {
				// @phan-suppress-next-line SecurityCheck-DoubleEscaped
				return Html::element( 'a', [
					'href' => 'https://www.wikidata.org/wiki/' . htmlentities( $id ),
				], $labels[$id] );
			}, $labelIds );
			// TODO there should probably be a structured-data or similar header but this extension
			// is not the right place for that
			$pageInfo['header-properties'][] = [
				$context->msg( 'machinevision-pageinfo-field-suggested-labels' )->escaped(),
				$context->getLanguage()->commaList( $wdItemLinks ),
			];
		}
	}

}
