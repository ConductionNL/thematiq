<?php

/**
 * Editor motion overrides reach a user who chose a theme.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/token-editor-ui/spec.md#requirement-motion-tokens-are-typed-and-reach-every-transition
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\CustomOverridesService;
use OCA\Thematiq\Service\DarkPaletteService;
use OCA\Thematiq\Service\OverridesStorage;
use OCA\Thematiq\Service\RuntimeFile\DirectoryRuntimeFileStore;
use OCP\App\IAppManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Issue #937: a saved "Animation quick" of 400ms reached light and
 * system-dark users, but a user who chose the dark theme got Nextcloud's
 * 100ms. Nextcloud serves a chosen theme's variables as
 * `[data-theme-dark] { --animation-quick: 100ms; ... }`, on body, and a body
 * declaration beats a value body would inherit from `:root`. The override has
 * to be declared on body as well, except for a user who chose reduced motion,
 * whose theme sets the durations to 0 on purpose.
 */
class OverridesCssBuilderMotionThemesTest extends TestCase {

	private const MOTION_SCOPE = 'body:not([data-theme-reduced-motion])';

	private string $appDir;
	private CustomOverridesService $service;

	/**
	 * Set up each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->appDir = sys_get_temp_dir() . '/thematiq-937-' . bin2hex(random_bytes(4));
		mkdir($this->appDir . '/css', 0777, true);

		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('getAppPath')->willReturn($this->appDir);
		$parser = new CssParserService();
		$darkPalette = new DarkPaletteService(
			contrast: new ContrastService(),
			parser: $parser,
			appManager: $appManager,
			logger: $this->createMock(originalClassName: LoggerInterface::class)
		);
		$this->service = new CustomOverridesService(
			storage: new OverridesStorage(
				store: new DirectoryRuntimeFileStore(root: $appManager->getAppPath('thematiq')),
				cssParser: $parser,
				darkPalette: $darkPalette
			)
		);
	}//end setUp()

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		foreach (glob($this->appDir . '/css/*') ?: [] as $file) {
			unlink($file);
		}

		rmdir($this->appDir . '/css');
		rmdir($this->appDir);
		parent::tearDown();
	}//end tearDown()

	/**
	 * The declarations of the block that follows a selector, or null when there is none.
	 *
	 * @param string $css The file content.
	 * @param string $selector The block's selector.
	 *
	 * @return array<string, string>|null Token => value with `!important` kept.
	 */
	private function block(string $css, string $selector): ?array {
		$start = strpos($css, $selector . ' {');
		if ($start === false) {
			return null;
		}

		$open = strpos($css, '{', $start);
		$close = strpos($css, '}', $open);
		preg_match_all('/(--[A-Za-z0-9_-]+)\s*:\s*([^;]+);/', substr($css, $open + 1, $close - $open - 1), $matches, PREG_SET_ORDER);
		$result = [];
		foreach ($matches as $match) {
			$result[$match[1]] = trim($match[2]);
		}

		return $result;
	}//end block()

	/**
	 * Motion overrides are declared on body for every chosen theme but reduced motion.
	 *
	 * @return void
	 */
	public function testMotionOverridesAreDeclaredOnBody(): void {
		$this->service->write(tokens: ['--animation-quick' => '400ms', '--animation-slow' => '800ms', '--color-primary' => '#154273']);
		$css = $this->service->getRawContent();

		$this->assertSame(
			expected: ['--animation-quick' => '400ms !important', '--animation-slow' => '800ms !important'],
			actual: $this->block(css: $css, selector: self::MOTION_SCOPE),
			message: 'a chosen theme declares the durations on body, so the override must too'
		);
	}//end testMotionOverridesAreDeclaredOnBody()

	/**
	 * Only the motion tokens go on body: colours keep their own dark scopes,
	 * and a file without motion overrides gets no body block.
	 *
	 * @return void
	 */
	public function testOnlyMotionGoesOnBody(): void {
		$this->service->write(tokens: ['--color-primary' => '#154273', '--border-radius' => '4px']);

		$this->assertNull(actual: $this->block(css: $this->service->getRawContent(), selector: self::MOTION_SCOPE));
	}//end testOnlyMotionGoesOnBody()

	/**
	 * The body copy is not read back as a value or as a dark value.
	 *
	 * @return void
	 */
	public function testTheBodyCopyDoesNotChangeWhatIsReadBack(): void {
		$tokens = ['--animation-quick' => '400ms', '--color-primary' => '#154273'];
		$this->service->write(tokens: $tokens);

		$this->assertSame(expected: $tokens, actual: $this->service->read());
		$this->assertSame(expected: [], actual: $this->service->readDark());
	}//end testTheBodyCopyDoesNotChangeWhatIsReadBack()
}//end class
