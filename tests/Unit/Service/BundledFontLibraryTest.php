<?php

/**
 * Unit tests for BundledFontLibrary.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/custom-fonts/spec.md#requirement-an-administrator-picks-a-font-from-an-approved-library
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\BundledFontLibrary;
use PHPUnit\Framework\TestCase;

/**
 * Which bundled families are approved, read from a stylesheet and a REUSE.toml
 * in a temp app root.
 *
 * @covers \OCA\Thematiq\Service\BundledFontLibrary
 * @uses   \OCA\Thematiq\Service\ReuseLicences
 */
class BundledFontLibraryTest extends TestCase {

	/**
	 * The temp app root.
	 *
	 * @var string
	 */
	private string $appPath;

	/**
	 * Write a temp app root with this stylesheet and REUSE.toml.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->appPath = sys_get_temp_dir() . '/thematiq-font-library-' . uniqid();
		mkdir($this->appPath . '/css/systems/nldesign', 0777, true);

		file_put_contents($this->appPath . '/' . BundledFontLibrary::STYLESHEET, <<<'CSS'
@font-face {
	font-family: 'Open One';
	src: local('Open One'), url('fonts/open-one-400.woff2') format('woff2'), url('fonts/open-one-400.woff') format('woff');
	font-weight: 400;
	font-style: normal;
}

@font-face {
	font-family: "Open One";
	src: url(fonts/open-one-700-italic.woff2) format('woff2');
	font-weight: 700;
	font-style: italic;
}

@font-face {
	font-family: 'Variable Two';
	src: url('fonts/variable-two.woff2') format('woff2');
	font-weight: 100 900;
}

@font-face {
	font-family: 'Unclaimed';
	src: url('fonts/unclaimed.woff2') format('woff2');
}

@font-face {
	font-family: 'Mixed';
	src: url('fonts/mixed-a.woff2') format('woff2');
}

@font-face {
	font-family: 'Mixed';
	src: url('fonts/mixed-b.woff2') format('woff2');
}

@font-face {
	font-family: 'Old Format';
	src: url('fonts/old.woff') format('woff');
}

@font-face {
	src: url('fonts/nameless.woff2') format('woff2');
}
CSS);

		file_put_contents($this->appPath . '/REUSE.toml', <<<'TOML'
[[annotations]]
path = "**"
SPDX-License-Identifier = "EUPL-1.2"

[[annotations]]
path = ["css/systems/nldesign/fonts/open-one-*", "css/systems/nldesign/fonts/mixed-a.woff2", "css/systems/nldesign/fonts/old.woff"]
precedence = "override"
SPDX-License-Identifier = "OFL-1.1"

[[annotations]]
path = ["css/systems/nldesign/fonts/variable-two.woff2", "css/systems/nldesign/fonts/mixed-b.woff2"]
precedence = "override"
SPDX-License-Identifier = "Apache-2.0"
TOML);
	}//end setUp()

	/**
	 * Remove the temp app root.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		unlink($this->appPath . '/' . BundledFontLibrary::STYLESHEET);
		unlink($this->appPath . '/REUSE.toml');
		rmdir($this->appPath . '/css/systems/nldesign');
		rmdir($this->appPath . '/css/systems');
		rmdir($this->appPath . '/css');
		rmdir($this->appPath);
		parent::tearDown();
	}//end tearDown()

	/**
	 * Only families whose WOFF2 files all carry one stated licence are approved.
	 *
	 * @return void
	 */
	public function testOnlyFamiliesWithOneStatedLicenceAreApproved(): void {
		$families = (new BundledFontLibrary(appPath: $this->appPath))->families();

		$this->assertSame(['Open One', 'Variable Two'], array_keys($families));
	}//end testOnlyFamiliesWithOneStatedLicenceAreApproved()

	/**
	 * Each face keeps its WOFF2 file, weight and style; missing ones default.
	 *
	 * @return void
	 */
	public function testFacesKeepTheirFileWeightAndStyle(): void {
		$library = new BundledFontLibrary(appPath: $this->appPath);

		$this->assertSame(
			[
				'name' => 'Open One',
				'licence' => 'OFL-1.1',
				'faces' => [
					['file' => 'css/systems/nldesign/fonts/open-one-400.woff2', 'weight' => '400', 'style' => 'normal'],
					['file' => 'css/systems/nldesign/fonts/open-one-700-italic.woff2', 'weight' => '700', 'style' => 'italic'],
				],
			],
			$library->family(name: 'Open One')
		);
		$this->assertSame(
			[['file' => 'css/systems/nldesign/fonts/variable-two.woff2', 'weight' => '100 900', 'style' => 'normal']],
			$library->family(name: 'Variable Two')['faces']
		);
		$this->assertSame('Apache-2.0', $library->family(name: 'Variable Two')['licence']);
	}//end testFacesKeepTheirFileWeightAndStyle()

	/**
	 * A name the library does not hold, or a left-out family, is null.
	 *
	 * @return void
	 */
	public function testAnUnlistedFamilyIsNull(): void {
		$library = new BundledFontLibrary(appPath: $this->appPath);

		$this->assertNull($library->family(name: 'Unclaimed'));
		$this->assertNull($library->family(name: 'Mixed'));
		$this->assertNull($library->family(name: 'open one'));
	}//end testAnUnlistedFamilyIsNull()

	/**
	 * Without the stylesheet the library is empty.
	 *
	 * @return void
	 */
	public function testAnAppWithoutTheStylesheetHasAnEmptyLibrary(): void {
		$this->assertSame([], (new BundledFontLibrary(appPath: $this->appPath . '/nowhere'))->families());
	}//end testAnAppWithoutTheStylesheetHasAnEmptyLibrary()

	/**
	 * The default app root is this app's own, which bundles Barlow.
	 *
	 * @return void
	 */
	public function testTheDefaultRootIsThisApp(): void {
		$this->assertSame('OFL-1.1', (new BundledFontLibrary())->family(name: 'Barlow')['licence']);
	}//end testTheDefaultRootIsThisApp()
}//end class
