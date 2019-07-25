<?php

namespace MediaWiki\Extension\MachineVision;

use LocalFile;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Title;

// Available via AutoloadClasses - T196090

/**
 * Test helper for creating common mock classes.
 */
class MockHelper {

	/**
	 * @param TestCase $testCase
	 * @param string $name File name
	 * @param string|null $sha1 File sha1
	 * @return LocalFile|MockObject
	 */
	public static function getMockFile( TestCase $testCase, $name, $sha1 = null ): LocalFile {
		$title = Title::newFromText( $name, NS_FILE );
		$file = $testCase->getMockBuilder( LocalFile::class )
			->disableOriginalConstructor()
			->setMethods( [ 'getTitle', 'getSha1' ] )
			->getMock();
		$file->expects( $testCase->any() )
			->method( 'getTitle' )
			->willReturn( $title );
		$file->expects( $testCase->any() )
			->method( 'getSha1' )
			->willReturn( $sha1 );
		/** @var $file LocalFile */
		return $file;
	}

}
