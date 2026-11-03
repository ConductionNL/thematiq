<?php

/**
 * Editor colour overrides reach both kinds of dark user.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/dark-mode/spec.md#requirement-editor-overrides-apply-the-same-way-for-every-dark-user
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\CustomOverridesService;
use OCA\Thematiq\Service\DarkPaletteService;
use OCA\Thematiq\Service\OverridesStorage;
use OCA\Thematiq\Service\RuntimeFile\DirectoryRuntimeFileStore;
use OCA\Thematiq\Tests\Unit\Support\FileList;
use OCP\App\IAppManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Thematiq#698: every override sat in one `:root` block. Nextcloud declares an
 * explicitly chosen dark theme's colours on `body`, so a user who picked
 * "Dark theme" never inherited the override, while a user on "System default"
 * with a dark OS did. Uses the real DarkPaletteService, so the dark value
 * written is the one the generated dark stylesheets would derive.
 */
class CustomOverridesServiceDarkScopesTest extends TestCase {

	private const SYSTEM_DARK = 'body:not([data-theme-light]):not([data-theme-dark]):not([data-theme-light-highcontrast]):not([data-theme-dark-highcontrast])';
	private const CHOSEN_DARK = 'body[data-theme-dark],' . "\n" . 'body[data-themes*=dark]';

	/**
	 * The app dir.
	 *
	 * @var string
	 */
	private string $appDir;
	/**
	 * The dark palette.
	 *
	 * @var DarkPaletteService
	 */
	private DarkPaletteService $darkPalette;
	/**
	 * The service.
	 *
	 * @var CustomOverridesService
	 */
	private CustomOverridesService $service;

	/**
	 * Set up each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->appDir = sys_get_temp_dir() . '/thematiq-698-' . bin2hex(random_bytes(4));
		mkdir($this->appDir . '/css', 0777, true);

		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('getAppPath')->willReturn($this->appDir);
		$parser = new CssParserService();
		$this->darkPalette = new DarkPaletteService(
			contrast: new ContrastService(),
			parser: $parser,
			appManager: $appManager,
			logger: $this->createMock(originalClassName: LoggerInterface::class)
		);
		$this->service = new CustomOverridesService(
			storage: new OverridesStorage(
				store: new DirectoryRuntimeFileStore(root: $appManager->getAppPath('thematiq')),
				cssParser: $parser,
				darkPalette: $this->darkPalette
			)
		);
	}//end setUp()

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		foreach (FileList::glob(pattern: $this->appDir . '/css/*') as $file) {
			unlink($file);
		}

		rmdir($this->appDir . '/css');
		rmdir($this->appDir);
		parent::tearDown();
	}//end tearDown()

	/**
	 * The declarations inside the block that follows a selector.
	 *
	 * @param string $css The file content.
	 * @param string $selector The block's selector.
	 *
	 * @return array<string, string> Token => value, `!important` stripped.
	 */
	private function block(string $css, string $selector): array {
		$start = strpos($css, $selector . ' {');
		$this->assertNotFalse(condition: $start, message: 'custom-overrides.css has no block for ' . $selector);
		$open = strpos($css, '{', $start);
		$close = strpos($css, '}', $open);
		preg_match_all('/(--[A-Za-z0-9_-]+)\s*:\s*([^;]+);/', substr($css, $open + 1, $close - $open - 1), $matches, PREG_SET_ORDER);
		$result = [];
		foreach ($matches as $match) {
			$result[$match[1]] = trim(str_replace('!important', '', $match[2]));
		}

		return $result;
	}//end block()

	/**
	 * A colour override gets the same derived dark value in both dark scopes,
	 * so both kinds of dark user see one colour.
	 *
	 * @return void
	 */
	public function testDarkScopesWritten(): void {
		$this->service->write(tokens: ['--color-primary' => '#154273', '--border-radius' => '4px']);
		$css = $this->service->getRawContent();

		$expected = $this->darkPalette->deriveDarkValue(token: '--color-primary', lightValue: '#154273');
		$this->assertNotSame(expected: '#154273', actual: $expected, message: 'a dark primary is derived, not the light value');

		$systemDark = $this->block(css: $css, selector: self::SYSTEM_DARK);
		$chosenDark = $this->block(css: $css, selector: self::CHOSEN_DARK);

		$this->assertSame(expected: ['--color-primary' => $expected], actual: $systemDark);
		$this->assertSame(expected: ['--color-primary' => $expected], actual: $chosenDark);
		$this->assertStringContainsString(needle: '@media (prefers-color-scheme: dark)', haystack: $css);
		$this->assertMatchesRegularExpression(
			pattern: '/--color-primary: [^;]+ !important;/',
			string: substr($css, (int)strpos($css, 'body[data-theme-dark]'))
		);
	}//end testDarkScopesWritten()

	/**
	 * A white label override stays readable on its derived dark fill
	 * (thematiq#953).
	 *
	 * Each override derives its dark value on its own: the primary element
	 * #154273 turns light blue, and white text takes the text clamp to mid-grey
	 * #9e9e9e, about 1.2:1 on it. The generated dark stylesheets repair such
	 * pairs; the editor's dark scopes now run the same repair.
	 *
	 * @return void
	 */
	public function testAWhiteLabelOverrideStaysReadableInDark(): void {
		$this->service->write(tokens: [
			'--color-primary' => '#154273',
			'--color-primary-text' => '#ffffff',
			'--color-primary-element' => '#154273',
			'--color-primary-element-text' => '#ffffff',
		]);
		$css = $this->service->getRawContent();
		$contrast = new ContrastService();

		foreach ([self::SYSTEM_DARK, self::CHOSEN_DARK] as $selector) {
			$dark = $this->block(css: $css, selector: $selector);
			foreach ([['--color-primary-text', '--color-primary'], ['--color-primary-element-text', '--color-primary-element']] as [$fg, $bg]) {
				$this->assertArrayHasKey(key: $fg, array: $dark);
				$ratio = $contrast->measure(foreground: $dark[$fg], background: $dark[$bg]);
				$this->assertGreaterThanOrEqual(
					expected: 4.5,
					actual: $ratio,
					message: sprintf('%s %s on %s in %s', $dark[$fg], $fg, $bg, $selector)
				);
			}
		}

		// The editor shows the same dark value the file carries.
		$this->assertSame(
			expected: $this->block(css: $css, selector: self::CHOSEN_DARK)['--color-primary-element-text'],
			actual: $this->service->derivedDark()['--color-primary-element-text']
		);
	}//end testAWhiteLabelOverrideStaysReadableInDark()

	/**
	 * An administrator's own dark label is never rewritten, even when it fails.
	 *
	 * @return void
	 */
	public function testAnOwnDarkLabelIsNotRepaired(): void {
		$this->service->write(
			tokens: ['--color-primary-element' => '#154273', '--color-primary-element-text' => '#ffffff'],
			darkTokens: ['--color-primary-element-text' => '#aaaaaa']
		);

		$this->assertSame(
			expected: '#aaaaaa',
			actual: $this->block(css: $this->service->getRawContent(), selector: self::CHOSEN_DARK)['--color-primary-element-text']
		);
	}//end testAnOwnDarkLabelIsNotRepaired()

	/**
	 * A component token gets no dark copy: the generated dark stylesheet already
	 * gives both kinds of dark user the same body value, and a body-level copy
	 * here would outrank the primary-lock layer.
	 *
	 * @return void
	 */
	public function testComponentTokensGetNoDarkCopy(): void {
		$this->service->write(tokens: ['--color-primary' => '#154273', '--nldesign-component-header-background-color' => '#112233']);
		$css = $this->service->getRawContent();

		$this->assertArrayNotHasKey(key: '--nldesign-component-header-background-color', array: $this->block(css: $css, selector: self::CHOSEN_DARK));
	}//end testComponentTokensGetNoDarkCopy()

	/**
	 * An exported file imports back to its light values, not the dark copies
	 * that follow them in the file.
	 *
	 * @return void
	 */
	public function testAnExportedFileReadsBackItsLightValues(): void {
		$this->service->write(tokens: ['--color-primary' => '#154273']);

		$this->assertSame(
			expected: ['--color-primary' => '#154273'],
			actual: (new CssParserService())->parseOverridesFile(css: $this->service->getRawContent())
		);
		$this->assertSame(expected: ['--x' => '1px'], actual: (new CssParserService())->parseOverridesFile(css: '--x: 1px;'));
	}//end testAnExportedFileReadsBackItsLightValues()

	/**
	 * The light values still read back from the :root block alone.
	 *
	 * @return void
	 */
	public function testLightValuesReadBack(): void {
		$this->service->write(tokens: ['--color-primary' => '#154273', '--border-radius' => '4px']);

		$this->assertSame(expected: ['--color-primary' => '#154273', '--border-radius' => '4px'], actual: $this->service->read());
	}//end testLightValuesReadBack()

	/**
	 * A colour the palette cannot parse keeps its light value in the dark
	 * scopes, so the two kinds of dark user still agree.
	 *
	 * @return void
	 */
	public function testAnUnparseableColourKeepsItsValueInBothDarkScopes(): void {
		$this->service->write(tokens: ['--color-primary' => 'var(--brand-blue)']);
		$css = $this->service->getRawContent();

		$this->assertSame(expected: ['--color-primary' => 'var(--brand-blue)'], actual: $this->block(css: $css, selector: self::CHOSEN_DARK));
		$this->assertSame(expected: ['--color-primary' => 'var(--brand-blue)'], actual: $this->block(css: $css, selector: self::SYSTEM_DARK));
	}//end testAnUnparseableColourKeepsItsValueInBothDarkScopes()
}//end class
