<?php

/**
 * Unit tests for ThemingService.
 *
 * @category  Test
 * @package   OCA\Thematiq\Tests\Unit\Service
 * @author    Conduction Development Team <dev@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://conduction.nl
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\RuntimeFile\DirectoryRuntimeFileStore;
use OCA\Thematiq\Service\RuntimeFile\RuntimeFileLocator;
use OCA\Thematiq\Service\ThemingService;
use OCA\Theming\ImageManager;
use OCA\Theming\Service\BackgroundService;
use OCA\Theming\ThemingDefaults;
use OCP\App\IAppManager;
use OCP\ITempManager;
use OCP\IURLGenerator;
use PHPUnit\Framework\TestCase;

/**
 * `ThemingService` is the only thing in this app that WRITES into Nextcloud
 * core theming, and everything it writes is reached through one of four
 * methods: validate colours, validate image paths, apply colours, apply
 * images — plus the reset that undoes all of it.
 *
 * Two of those are security boundaries rather than conveniences.
 * `validateSinglePath()` is what stops a caller pointing core theming at an
 * arbitrary file on disk, and `applyImages()` is the pairing of
 * `ImageManager::updateImage()` with the `{key}Mime` app value that the
 * service's own docblock records as having been missed once — the file was
 * written, `hasImage()` said true, and every page still rendered the stock
 * logo. Both are asserted here on the observable calls, not on a return value
 * alone, because the defect that shipped was a MISSING call with a correct
 * return.
 *
 * The three `OCA\Theming\*` collaborators are first-party Nextcloud classes,
 * present at runtime but not composer dependencies. The PHPUnit legs install a
 * real server, so they resolve there; in a bare source tree they do not, and
 * the suite says so rather than failing on a mock of a class that is absent
 * for an understood reason.
 *
 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-41
 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-42
 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-43
 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-44
 * @spec openspec/changes/apply-without-reload/specs/theming-sync/spec.md
 */
class ThemingServiceTest extends TestCase {

	/**
	 * The app directory the path validator resolves against.
	 *
	 * @var string
	 */
	private string $appDir;

	/**
	 * The image manager mock.
	 *
	 * @var ImageManager&\PHPUnit\Framework\MockObject\MockObject
	 */
	private $imageManager;

	/**
	 * The theming defaults mock — the thing every write lands on.
	 *
	 * @var ThemingDefaults&\PHPUnit\Framework\MockObject\MockObject
	 */
	private $themingDefaults;

	/**
	 * The service under test.
	 *
	 * @var ThemingService
	 */
	private ThemingService $service;

	/**
	 * Build the service against a temp app dir holding one real logo file.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->appDir = sys_get_temp_dir() . '/thematiq-theming-test-' . uniqid();
		mkdir($this->appDir . '/img/logos', 0777, true);
		mkdir($this->appDir . '/img/backgrounds', 0777, true);
		file_put_contents($this->appDir . '/img/logos/present.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>');

		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('getAppPath')->willReturn($this->appDir);

		$this->imageManager = $this->createMock(originalClassName: ImageManager::class);
		$this->themingDefaults = $this->createMock(originalClassName: ThemingDefaults::class);

		// A real locator over the temp app dir, with an empty store beside it:
		// `present.svg` reads as a release file, as a shipped logo does.
		$urls = $this->createMock(originalClassName: IURLGenerator::class);
		$this->service = new ThemingService(
			imageManager: $this->imageManager,
			themingDefaults: $this->themingDefaults,
			files: new RuntimeFileLocator(
				appManager: $appManager,
				store: new DirectoryRuntimeFileStore(root: $this->appDir . '/.store'),
				urlGenerator: $urls,
				tempManager: $this->createMock(originalClassName: ITempManager::class)
			)
		);
	}//end setUp()

	/**
	 * Remove the temp app dir after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		if (isset($this->appDir) === true && is_dir($this->appDir) === true) {
			$this->rrmdir(dir: $this->appDir);
		}

		parent::tearDown();
	}//end tearDown()

	/**
	 * Recursively remove a directory tree.
	 *
	 * @param string $dir The directory to remove.
	 *
	 * @return void
	 */
	private function rrmdir(string $dir): void {
		foreach (scandir($dir) as $entry) {
			if ($entry === '.' || $entry === '..') {
				continue;
			}

			$path = $dir . '/' . $entry;
			if (is_dir($path) === true) {
				$this->rrmdir(dir: $path);
			} else {
				unlink($path);
			}
		}

		rmdir($dir);
	}//end rrmdir()

	/**
	 * Three- and six-digit hex are accepted; everything else — including the
	 * named colours and `rgb()` forms CSS would take — is not, because the
	 * value is handed to core theming, which stores a hex string.
	 *
	 * @param string $color The candidate colour.
	 * @param bool $expected Whether it is a valid hex colour.
	 *
	 * @dataProvider hexColorProvider
	 *
	 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-41
	 *
	 * @return void
	 */
	public function testIsValidHexColor(string $color, bool $expected): void {
		$this->assertSame(expected: $expected, actual: $this->service->isValidHexColor(color: $color));
	}//end testIsValidHexColor()

	/**
	 * Colour candidates and their verdicts.
	 *
	 * @return array<string, array{0: string, 1: bool}>
	 */
	public static function hexColorProvider(): array {
		return [
			'six digits' => ['#154273', true],
			'three digits' => ['#abc', true],
			'uppercase' => ['#AABBCC', true],
			'no hash' => ['154273', false],
			'four digits' => ['#1542', false],
			'eight digits with alpha' => ['#15427380', false],
			'named colour' => ['rebeccapurple', false],
			'rgb function' => ['rgb(21, 66, 115)', false],
			'empty' => ['', false],
		];
	}//end hexColorProvider()

	/**
	 * An absent or empty colour parameter is not a validation failure: the
	 * sync sends only what the set actually declares.
	 *
	 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-41
	 *
	 * @return void
	 */
	public function testValidateColorsAcceptsAbsentAndEmptyValues(): void {
		$this->assertNull(actual: $this->service->validateColors(params: []));
		$this->assertNull(actual: $this->service->validateColors(params: ['primary_color' => '']));
		$this->assertNull(
			actual: $this->service->validateColors(params: ['primary_color' => '#154273', 'background_color' => '#ffffff'])
		);
	}//end testValidateColorsAcceptsAbsentAndEmptyValues()

	/**
	 * A malformed colour is named in the message, so the admin panel can say
	 * WHICH field it rejected rather than "invalid input".
	 *
	 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-41
	 *
	 * @return void
	 */
	public function testValidateColorsNamesTheOffendingKey(): void {
		$error = $this->service->validateColors(params: ['background_color' => 'not-a-colour']);

		$this->assertIsString(actual: $error);
		$this->assertStringContainsString(needle: 'background_color', haystack: $error);
		$this->assertStringContainsString(needle: 'not-a-colour', haystack: $error);
	}//end testValidateColorsNamesTheOffendingKey()

	/**
	 * Path traversal and absolute paths are refused before anything touches
	 * the filesystem. `logo_dark` is validated by the same rules as `logo`
	 * even though it is never passed to core theming.
	 *
	 * @param string $key The image parameter name.
	 * @param string $path The candidate path.
	 *
	 * @dataProvider traversalPathProvider
	 *
	 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-42
	 * @spec openspec/specs/theming-sync/spec.md
	 *
	 * @return void
	 */
	public function testValidateImagePathsRefusesTraversal(string $key, string $path): void {
		$error = $this->service->validateImagePaths(params: [$key => $path]);

		$this->assertIsString(actual: $error);
		$this->assertStringContainsString(needle: 'path traversal not allowed', haystack: $error);
		$this->assertStringContainsString(needle: $key, haystack: $error);
	}//end testValidateImagePathsRefusesTraversal()

	/**
	 * Traversal candidates, one per validated image key.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function traversalPathProvider(): array {
		return [
			'logo climbs out' => ['logo', 'img/logos/../../../../etc/passwd'],
			'background is absolute' => ['background', '/etc/passwd'],
			'dark logo climbs out' => ['logo_dark', '../config/config.php'],
		];
	}//end traversalPathProvider()

	/**
	 * A path that stays inside the app but outside the two permitted
	 * directories is refused on the prefix rule, before the existence check.
	 *
	 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-42
	 *
	 * @return void
	 */
	public function testValidateImagePathsRefusesDirectoriesOutsideTheAllowedTwo(): void {
		$error = $this->service->validateImagePaths(params: ['logo' => 'css/tokens/utrecht.css']);

		$this->assertIsString(actual: $error);
		$this->assertStringContainsString(needle: 'img/logos/', haystack: $error);
		$this->assertStringContainsString(needle: 'img/backgrounds/', haystack: $error);
	}//end testValidateImagePathsRefusesDirectoriesOutsideTheAllowedTwo()

	/**
	 * A well-formed path to a file that is not there is refused too — the
	 * prefix rule says where a file may live, not that it does.
	 *
	 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-42
	 *
	 * @return void
	 */
	public function testValidateImagePathsRefusesAMissingFile(): void {
		$error = $this->service->validateImagePaths(params: ['logo' => 'img/logos/absent.svg']);

		$this->assertIsString(actual: $error);
		$this->assertStringContainsString(needle: 'Image file not found', haystack: $error);
	}//end testValidateImagePathsRefusesAMissingFile()

	/**
	 * A path inside `img/logos/` that exists passes, and so does a request
	 * that carries no image at all.
	 *
	 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-42
	 *
	 * @return void
	 */
	public function testValidateImagePathsAcceptsAnExistingFile(): void {
		$this->assertNull(actual: $this->service->validateImagePaths(params: ['logo' => 'img/logos/present.svg']));
		$this->assertNull(actual: $this->service->validateImagePaths(params: []));
		$this->assertNull(actual: $this->service->validateImagePaths(params: ['logo' => '']));
	}//end testValidateImagePathsAcceptsAnExistingFile()

	/**
	 * Applying a primary colour writes exactly that setting and reports it.
	 * No background colour means no `backgroundMime` write.
	 *
	 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-43
	 *
	 * @return void
	 */
	public function testApplyColorsWritesOnlyWhatWasGiven(): void {
		$written = [];
		$this->themingDefaults->method('set')->willReturnCallback(
			function (string $setting, string $value) use (&$written): void {
				$written[$setting] = $value;
			}
		);

		$updated = $this->service->applyColors(params: ['primary_color' => '#154273']);

		$this->assertSame(expected: ['primary_color'], actual: $updated);
		$this->assertSame(expected: ['primary_color' => '#154273'], actual: $written);
	}//end testApplyColorsWritesOnlyWhatWasGiven()

	/**
	 * A background COLOUR only shows once core stops painting its default
	 * background IMAGE over it, which is the `backgroundMime` app value. So a
	 * background colour with no accompanying image must also write that.
	 *
	 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-43
	 *
	 * @return void
	 */
	public function testApplyColorsRemovesTheDefaultBackgroundImage(): void {
		$written = [];
		$this->themingDefaults->method('set')->willReturnCallback(
			function (string $setting, string $value) use (&$written): void {
				$written[$setting] = $value;
			}
		);

		$updated = $this->service->applyColors(params: ['background_color' => '#ffffff']);

		$this->assertSame(expected: ['background_color'], actual: $updated);
		$this->assertSame(expected: 'backgroundColor', actual: ($written['backgroundMime'] ?? null));
	}//end testApplyColorsRemovesTheDefaultBackgroundImage()

	/**
	 * When the same request also carries a background IMAGE, the colour must
	 * not claim the mime slot: `applyImages()` writes the real mime right
	 * after, and writing `backgroundColor` here would be overwritten anyway —
	 * or, on a failed image write, would leave the two disagreeing.
	 *
	 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-43
	 *
	 * @return void
	 */
	public function testApplyColorsLeavesTheMimeAloneWhenAnImageIsAlsoGiven(): void {
		$written = [];
		$this->themingDefaults->method('set')->willReturnCallback(
			function (string $setting, string $value) use (&$written): void {
				$written[$setting] = $value;
			}
		);

		$updated = $this->service->applyColors(
			params: ['background_color' => '#ffffff', 'background' => 'img/backgrounds/whatever.jpg']
		);

		$this->assertSame(expected: ['background_color'], actual: $updated);
		$this->assertArrayNotHasKey(key: 'backgroundMime', array: $written);
	}//end testApplyColorsLeavesTheMimeAloneWhenAnImageIsAlsoGiven()

	/**
	 * A theme that captured Nextcloud's default background image gets that
	 * image back — the background is undone to core's own — instead of the
	 * colour-only background a plain background colour would otherwise force.
	 *
	 * @return void
	 */
	public function testACapturedDefaultBackgroundIsRestoredNotRemoved(): void {
		$written = [];
		$this->themingDefaults->method('set')->willReturnCallback(
			function (string $setting, string $value) use (&$written): void {
				$written[$setting] = $value;
			}
		);
		$this->themingDefaults->expects($this->once())->method('undo')->with('background');

		$updated = $this->service->applyColors(
			params: ['background_color' => '#ffffff', 'background_mode' => 'default']
		);

		$this->assertSame(expected: ['background_color', 'background_mode'], actual: $updated);
		$this->assertArrayNotHasKey(key: 'backgroundMime', array: $written);
	}//end testACapturedDefaultBackgroundIsRestoredNotRemoved()

	/**
	 * A theme that captured "Remove background image" gets it back, whether
	 * or not a background colour comes with it.
	 *
	 * @return void
	 */
	public function testACapturedRemovedBackgroundIsRemovedAgain(): void {
		$written = [];
		$this->themingDefaults->method('set')->willReturnCallback(
			function (string $setting, string $value) use (&$written): void {
				$written[$setting] = $value;
			}
		);

		$updated = $this->service->applyColors(params: ['background_mode' => 'color']);

		$this->assertSame(expected: ['background_mode'], actual: $updated);
		$this->assertSame(expected: 'backgroundColor', actual: ($written['backgroundMime'] ?? null));
	}//end testACapturedRemovedBackgroundIsRemovedAgain()

	/**
	 * An unknown background state is refused before anything is written.
	 *
	 * @return void
	 */
	public function testAnUnknownBackgroundModeIsRefused(): void {
		$this->assertSame(
			expected: 'Invalid background_mode: tiled',
			actual: $this->service->validateImagePaths(params: ['background_mode' => 'tiled'])
		);
		$this->assertNull(actual: $this->service->validateImagePaths(params: ['background_mode' => 'default']));
	}//end testAnUnknownBackgroundModeIsRefused()

	/**
	 * The navigation-bar logo and the favicon are synced like the logo: the
	 * stored file paired with its mime.
	 *
	 * @return void
	 */
	public function testApplyImagesSyncsTheNavigationBarLogoAndFavicon(): void {
		$this->imageManager->method('updateImage')->willReturn('image/svg+xml');
		$mimes = [];
		$this->themingDefaults->method('set')->willReturnCallback(
			function (string $setting, string $value) use (&$mimes): void {
				$mimes[$setting] = $value;
			}
		);

		$updated = $this->service->applyImages(
			params: ['logoheader' => 'img/logos/present.svg', 'favicon' => 'img/logos/present.svg']
		);

		$this->assertSame(expected: ['logoheader', 'favicon'], actual: $updated);
		$this->assertSame(expected: ['logoheaderMime' => 'image/svg+xml', 'faviconMime' => 'image/svg+xml'], actual: $mimes);
	}//end testApplyImagesSyncsTheNavigationBarLogoAndFavicon()

	/**
	 * A theme that captured an image of its own never has it removed by the
	 * colour: the image is written by applyImages() right after.
	 *
	 * @return void
	 */
	public function testACapturedImageBackgroundIsNotRemovedByTheColour(): void {
		$written = [];
		$this->themingDefaults->method('set')->willReturnCallback(
			function (string $setting, string $value) use (&$written): void {
				$written[$setting] = $value;
			}
		);
		$this->themingDefaults->expects($this->never())->method('undo');

		$this->service->applyColors(params: ['background_color' => '#ffffff', 'background_mode' => 'image']);

		$this->assertArrayNotHasKey(key: 'backgroundMime', array: $written);
	}//end testACapturedImageBackgroundIsNotRemovedByTheColour()

	/**
	 * The pairing the service exists to keep: `updateImage()` stores the file
	 * and returns the detected mime, and the `{key}Mime` app value is what
	 * `ThemingDefaults::getLogo()` reads to decide a custom image exists at
	 * all. Storing the file without writing the mime is the failure that
	 * reported success while every page kept the stock logo.
	 *
	 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-44
	 * @spec openspec/specs/theming-sync/spec.md
	 *
	 * @return void
	 */
	public function testApplyImagesPairsTheStoredFileWithItsMime(): void {
		$this->imageManager->expects($this->once())
			->method('updateImage')
			->with('logo', $this->appDir . '/img/logos/present.svg')
			->willReturn('image/svg+xml');

		$written = [];
		$this->themingDefaults->method('set')->willReturnCallback(
			function (string $setting, string $value) use (&$written): void {
				$written[$setting] = $value;
			}
		);

		$updated = $this->service->applyImages(params: ['logo' => 'img/logos/present.svg']);

		$this->assertSame(expected: ['logo'], actual: $updated);
		$this->assertSame(expected: ['logoMime' => 'image/svg+xml'], actual: $written);
	}//end testApplyImagesPairsTheStoredFileWithItsMime()

	/**
	 * `logo_dark` is validated like a logo but never applied: core has one
	 * logo slot, and the dark logo is delivered by this app's own generated
	 * dark stylesheet instead.
	 *
	 * @spec openspec/specs/theming-sync/spec.md
	 *
	 * @return void
	 */
	public function testApplyImagesIgnoresTheDarkLogo(): void {
		$this->imageManager->expects($this->never())->method('updateImage');

		$this->assertSame(expected: [], actual: $this->service->applyImages(params: ['logo_dark' => 'img/logos/present.svg']));
		$this->assertSame(expected: [], actual: $this->service->applyImages(params: []));
	}//end testApplyImagesIgnoresTheDarkLogo()

	/**
	 * A reset undoes every setting the sync can write, through the same
	 * `ThemingDefaults::undo()` core's own panel uses — not by writing the
	 * stock set's manifest values, which is what left the previous set's logo
	 * in place after switching back to stock.
	 *
	 * @spec openspec/changes/apply-without-reload/specs/theming-sync/spec.md
	 *
	 * @return void
	 */
	public function testResetToDefaultsUndoesEverySyncedSetting(): void {
		$undone = [];
		$this->themingDefaults->method('undo')->willReturnCallback(
			function (string $setting) use (&$undone): string {
				$undone[] = $setting;

				return '';
			}
		);

		$reset = $this->service->resetToDefaults();

		$this->assertSame(expected: ThemingService::RESETTABLE, actual: $reset);
		$this->assertSame(expected: ThemingService::RESETTABLE, actual: $undone);
		$this->assertContains(needle: 'logo', haystack: $undone);
		$this->assertContains(needle: 'background', haystack: $undone);
	}//end testResetToDefaultsUndoesEverySyncedSetting()

	/**
	 * The colours a reset lands on come from `BackgroundService`'s constants —
	 * core's own fallbacks — not from `getDefaultColorPrimary()`, which
	 * returns the admin-configured primary that a reset is removing.
	 *
	 * @spec openspec/changes/apply-without-reload/specs/theming-sync/spec.md
	 *
	 * @return void
	 */
	public function testGetDefaultColorsReadsCoreFallbacksNotTheConfiguredPrimary(): void {
		$defaults = $this->service->getDefaultColors();

		$this->assertSame(expected: BackgroundService::DEFAULT_COLOR, actual: $defaults['primary_color']);
		$this->assertSame(expected: BackgroundService::DEFAULT_BACKGROUND_COLOR, actual: $defaults['background_color']);
	}//end testGetDefaultColorsReadsCoreFallbacksNotTheConfiguredPrimary()

	/**
	 * The image manager is handed out so callers can ask core what is in each
	 * slot; it must be the same instance the service writes through.
	 *
	 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-44
	 *
	 * @return void
	 */
	public function testGetImageManagerReturnsTheInjectedInstance(): void {
		$this->assertSame(expected: $this->imageManager, actual: $this->service->getImageManager());
	}//end testGetImageManagerReturnsTheInjectedInstance()
}//end class
