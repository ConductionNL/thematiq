<?php

/**
 * Unit tests for assigning a font from the approved library.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/custom-fonts/spec.md#requirement-an-administrator-picks-a-font-from-an-approved-library
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\BundledFontLibrary;
use OCA\Thematiq\Service\FontService;
use OCA\Thematiq\Service\FontValidator;
use OCP\Files\IAppData;
use OCP\IConfig;
use OCP\IURLGenerator;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The library half of FontService, over the fonts this app really bundles.
 *
 * @covers \OCA\Thematiq\Service\FontService
 * @uses   \OCA\Thematiq\Service\BundledFontLibrary
 * @uses   \OCA\Thematiq\Service\ReuseLicences
 * @uses   \OCA\Thematiq\Service\AppConfigManifest
 * @uses   \OCA\Thematiq\Service\FontValidator
 */
class FontServiceLibraryTest extends TestCase {

	/**
	 * In-memory appconfig store: key => value.
	 *
	 * @var array<string, string>
	 */
	private array $appConfig = [];

	/**
	 * The service under test.
	 *
	 * @var FontService
	 */
	private FontService $service;

	/**
	 * Build the service over this app's own bundled fonts.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$config = $this->createMock(originalClassName: IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			fn (string $app, string $key, $default = '') => ($this->appConfig[$key] ?? $default)
		);
		$config->method('setAppValue')->willReturnCallback(
			function (string $app, string $key, $value): void {
				$this->appConfig[$key] = $value;
			}
		);

		$urlGenerator = $this->createMock(originalClassName: IURLGenerator::class);
		$urlGenerator->method('linkToRoute')->willReturnCallback(
			fn (string $route, array $args = []) => '/apps/thematiq/fonts/' . ($args['id'] ?? '') . '.woff2'
		);
		$urlGenerator->method('linkTo')->willReturnCallback(
			fn (string $app, string $file) => '/custom_apps/' . $app . '/' . $file
		);

		$this->service = new FontService(
			appData: $this->createMock(originalClassName: IAppData::class),
			config: $config,
			urlGenerator: $urlGenerator,
			validator: new FontValidator(),
			library: new BundledFontLibrary(appPath: dirname(__DIR__, 3)),
		);
	}//end setUp()

	/**
	 * The `:root` rule that sets one CSS variable, from a generated stylesheet.
	 *
	 * @param string $css      The stylesheet.
	 * @param string $variable The variable.
	 *
	 * @return string The rule, or '' when the stylesheet sets no such variable.
	 */
	private function rootRuleFor(string $css, string $variable): string {
		preg_match('/:root \{\s*' . preg_quote($variable, '/') . ':[^}]*\}/', $css, $match);

		return ($match[0] ?? '');
	}//end rootRuleFor()

	/**
	 * Barlow is listed with its licence, and with no role until assigned.
	 *
	 * @return void
	 */
	public function testTheLibraryListsBundledFontsWithTheirLicence(): void {
		$byName = array_column($this->service->library(), null, 'name');

		$this->assertSame(['name' => 'Barlow', 'licence' => 'OFL-1.1', 'roles' => []], $byName['Barlow']);
		$this->assertSame('Apache-2.0', $byName['Clear Sans']['licence']);
	}//end testTheLibraryListsBundledFontsWithTheirLicence()

	/**
	 * Assigning a bundled font writes the same font token an upload with that
	 * name and role would (task 3.3).
	 *
	 * @return void
	 */
	public function testAssigningABundledFontWritesTheSameTokenAnUploadWould(): void {
		$this->appConfig[FontService::MANIFEST_KEY] = json_encode(['custom-barlow' => ['name' => 'Barlow', 'role' => 'heading']]);
		$uploaded = $this->rootRuleFor(css: $this->service->buildCss(), variable: '--nldesign-typography-heading-font-family');
		unset($this->appConfig[FontService::MANIFEST_KEY]);

		$this->service->assignLibraryFont(name: 'Barlow', role: 'heading');
		$assigned = $this->rootRuleFor(css: $this->service->buildCss(), variable: '--nldesign-typography-heading-font-family');

		$this->assertNotSame('', $uploaded);
		$this->assertSame($uploaded, $assigned);
		$this->assertStringContainsString('"Barlow", ' . FontService::DEFAULT_FONT_FAMILY, $assigned);
		$this->assertSame(['heading'], array_column($this->service->library(), 'roles', 'name')['Barlow']);
	}//end testAssigningABundledFontWritesTheSameTokenAnUploadWould()

	/**
	 * The faces of an assigned bundled font are served from the app's own files.
	 *
	 * @return void
	 */
	public function testAnAssignedBundledFontIsServedFromTheInstance(): void {
		$this->service->assignLibraryFont(name: 'Barlow', role: 'body');
		$css = $this->service->buildCss();

		$this->assertSame(4, substr_count($css, 'font-family: "Barlow";'));
		$this->assertStringContainsString(
			"src: url('/custom_apps/thematiq/css/systems/nldesign/fonts/barlow-latin-700-normal.woff2') format('woff2');" . PHP_EOL
			. '	font-weight: 700;' . PHP_EOL . '	font-style: normal;',
			$css
		);
		$this->assertStringContainsString('--nldesign-font-family: "Barlow", ', $this->rootRuleFor(css: $css, variable: '--nldesign-font-family'));
		$this->assertSame('', $this->rootRuleFor(css: $css, variable: '--nldesign-typography-heading-font-family'));
		$this->assertTrue($this->service->hasFonts());
	}//end testAnAssignedBundledFontIsServedFromTheInstance()

	/**
	 * One family on both roles declares its faces once.
	 *
	 * @return void
	 */
	public function testAFamilyOnBothRolesIsDeclaredOnce(): void {
		$this->service->assignLibraryFont(name: 'Lato', role: 'body');
		$this->service->assignLibraryFont(name: 'Lato', role: 'heading');

		$this->assertSame(2, substr_count($this->service->buildCss(), 'font-family: "Lato";'));
	}//end testAFamilyOnBothRolesIsDeclaredOnce()

	/**
	 * A library font takes its role from an upload; the upload keeps the other role.
	 *
	 * @return void
	 */
	public function testALibraryFontTakesItsRoleOverFromAnUpload(): void {
		$this->appConfig[FontService::MANIFEST_KEY] = json_encode([
			'custom-rijks' => ['name' => 'Rijks', 'role' => 'heading'],
			'custom-house' => ['name' => 'House', 'role' => 'body'],
		]);
		$this->service->assignLibraryFont(name: 'Figtree', role: 'heading');
		$css = $this->service->buildCss();

		$this->assertStringContainsString('"Figtree", ', $this->rootRuleFor(css: $css, variable: '--nldesign-typography-heading-font-family'));
		$this->assertStringContainsString('"House", ', $this->rootRuleFor(css: $css, variable: '--nldesign-font-family'));
		$this->assertStringContainsString('font-family: "Rijks";', $css);
	}//end testALibraryFontTakesItsRoleOverFromAnUpload()

	/**
	 * A font the library does not hold, or an unknown role, is refused and stores nothing.
	 *
	 * @return void
	 */
	public function testAnUnknownFontOrRoleIsRefused(): void {
		try {
			$this->service->assignLibraryFont(name: 'Comic Sans', role: 'body');
			$this->fail('Expected a refusal');
		} catch (RuntimeException $e) {
			$this->assertSame(404, $e->getCode());
			$this->assertSame('The font library has no font named "Comic Sans".', $e->getMessage());
		}

		try {
			$this->service->assignLibraryFont(name: 'Barlow', role: 'caption');
			$this->fail('Expected a refusal');
		} catch (RuntimeException $e) {
			$this->assertSame(422, $e->getCode());
		}

		$this->assertArrayNotHasKey(FontService::LIBRARY_ROLES_KEY, $this->appConfig);
		$this->assertFalse($this->service->hasFonts());
		$this->assertSame('', $this->service->buildCss());
	}//end testAnUnknownFontOrRoleIsRefused()

	/**
	 * Every change bumps the revision, so the stylesheet URL changes.
	 *
	 * @return void
	 */
	public function testAssigningAndUnassigningBumpTheRevision(): void {
		$this->service->assignLibraryFont(name: 'Barlow', role: 'body');
		$this->assertSame(1, $this->service->getRevision());

		$this->assertTrue($this->service->unassignLibraryFont(role: 'body'));
		$this->assertSame(2, $this->service->getRevision());
		$this->assertFalse($this->service->hasFonts());
	}//end testAssigningAndUnassigningBumpTheRevision()

	/**
	 * Unassigning a role without a library font changes nothing.
	 *
	 * @return void
	 */
	public function testUnassigningAnEmptyRoleChangesNothing(): void {
		$this->assertFalse($this->service->unassignLibraryFont(role: 'heading'));
		$this->assertSame(0, $this->service->getRevision());

		$this->expectException(RuntimeException::class);
		$this->expectExceptionCode(422);
		$this->service->unassignLibraryFont(role: 'footer');
	}//end testUnassigningAnEmptyRoleChangesNothing()

	/**
	 * A stored family the library no longer holds, or a malformed entry, is ignored.
	 *
	 * @return void
	 */
	public function testAStaleAssignmentIsIgnored(): void {
		$this->appConfig[FontService::LIBRARY_ROLES_KEY] = json_encode(['body' => 'Gone Sans', 'heading' => ['Barlow'], 'caption' => 'Barlow']);

		$this->assertFalse($this->service->hasFonts());
		$this->assertSame('', $this->service->buildCss());
		$this->assertSame([], array_column($this->service->library(), 'roles', 'name')['Barlow']);
	}//end testAStaleAssignmentIsIgnored()
}//end class
