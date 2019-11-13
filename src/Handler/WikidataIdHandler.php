<?php

namespace MediaWiki\Extension\MachineVision\Handler;

use Html;
use IContextSource;
use LocalFile;
use MediaWiki\Extension\MachineVision\Repository;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Throwable;
use User;

abstract class WikidataIdHandler implements Handler {

	use LoggerAwareTrait;

	/** @var Repository */
	private $repository;

	/** @var WikidataDepictsSetter */
	private $depictsSetter;

	/** @var LabelResolver */
	private $labelResolver;

	/**
	 * @param Repository $repository
	 * @param WikidataDepictsSetter $depictsSetter
	 * @param LabelResolver $labelResolver
	 */
	public function __construct(
		Repository $repository,
		WikidataDepictsSetter $depictsSetter,
		LabelResolver $labelResolver
	) {
		$this->repository = $repository;
		$this->depictsSetter = $depictsSetter;
		$this->labelResolver = $labelResolver;

		$this->setLogger( new NullLogger() );
	}

	/** @inheritDoc */
	abstract public function getMaxRequestsPerMinute(): int;

	/** @inheritDoc */
	abstract public function isTooManyRequestsError( Throwable $t ): bool;

	/** @return Repository */
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
		$ids = array_column( $this->repository->getLabels( $file->getSha1() ), 'wikidata_id' );
		if ( !$ids ) {
			return;
		}
		$labels = $this->labelResolver->resolve( $context, $ids );
		if ( !$labels ) {
			return;
		}
		$links = [];
		foreach ( $labels as $id => $label ) {
			$links[] = Html::element( 'a', [
				'href' => 'https://www.wikidata.org/wiki/' . $id,
			], $label );
		}
		// TODO there should probably be a structured-data or similar header but this extension
		// is not the right place for that
		$pageInfo['header-properties'][] = [
			$context->msg( 'machinevision-pageinfo-field-suggested-labels' )->escaped(),
			$context->getLanguage()->commaList( $links ),
		];
	}

	/** @inheritDoc */
	public function handleLabelReview( User $user, LocalFile $file, $label, $token, $reviewState ) {
		if ( $reviewState === Repository::REVIEW_ACCEPTED ) {
			$this->handleLabelAccepted( $user, $file, $label, $token );
		}
	}

	private function handleLabelAccepted( User $user, LocalFile $file, $label, $token ) {
		$this->depictsSetter->addDepicts( $user, $file, $label, $token );
	}

}
