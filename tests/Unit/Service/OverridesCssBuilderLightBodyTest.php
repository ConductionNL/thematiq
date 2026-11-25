<?php

/**
 * Editor light overrides reach a user who chose the light theme.
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
use OCA\Thematiq\Service\DarkPaletteService;
use OCA\Thematiq\Service\OverridesCssBuilder;
use OCA\Thematiq\Service\TokenValueValidator;
use OCP\App\IAppManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Found live (e2e dark-mode-overrides, 25 Nov): with "Primary color" #154273 saved,
 * a user who chose the light theme on a dark OS saw the token set's primary, not
 * #154273. The design-system theme.css declares `--color-primary` on
 * `body[data-themes], body`, and that beats the value body inherits from the
 * file's :root block. The brand-layer overrides are declared on body as well.
 */
class OverridesCssBuilderLightBodyTest extends TestCase {

	private const LIGHT_BODY = "body[data-themes],\nbody {";

	/**
	 * The builder.
	 *
	 * @var OverridesCssBuilder
	 */
	private OverridesCssBuilder $builder;

	/**
	 * Set up each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('getAppPath')->willReturn(sys_get_temp_dir());
		$darkPalette = new DarkPaletteService(
			contrast: new ContrastService(),
			parser: new CssParserService(),
			appManager: $appManager,
			logger: $this->createMock(originalClassName: LoggerInterface::class)
		);
		$this->builder = new OverridesCssBuilder(darkPalette: $darkPalette, values: new TokenValueValidator());
	}//end setUp()

	/**
	 * The declarations of the light body block, or null when the file has none.
	 *
	 * @param string $css The file content.
	 *
	 * @return array<string, string>|null Token => value with `!important` kept.
	 */
	private function lightBody(string $css): ?array {
		$start = strpos($css, self::LIGHT_BODY);
		if ($start === false) {
			return null;
		}

		$open = strpos($css, '{', $start);
		$close = strpos($css, '}', $open);
		preg_match_all('/(--[A-Za-z0-9_-]+)\s*:\s*([^;]+);/', substr($css, ($open + 1), ($close - $open - 1)), $matches, PREG_SET_ORDER);
		$result = [];
		foreach ($matches as $match) {
			$result[$match[1]] = trim($match[2]);
		}

		return $result;
	}//end lightBody()

	/**
	 * A Nextcloud variable is declared on body with the light value, before the dark scopes.
	 *
	 * @return void
	 */
	public function testABrandLayerOverrideIsDeclaredOnBodyBeforeTheDarkScopes(): void {
		$css = $this->builder->build(
			tokens: ['--color-primary' => '#154273'],
			darkTokens: ['--color-primary' => '#8fb8e6']
		);

		$this->assertSame(expected: ['--color-primary' => '#154273 !important'], actual: $this->lightBody(css: $css));
		$body = (int)strpos($css, self::LIGHT_BODY);
		$this->assertLessThan(expected: (int)strpos($css, '@media (prefers-color-scheme: dark)'), actual: $body);
		$this->assertLessThan(expected: (int)strpos($css, 'body[data-theme-dark],'), actual: $body);
		$this->assertGreaterThan(expected: (int)strpos($css, ':root {'), actual: $body);
	}//end testABrandLayerOverrideIsDeclaredOnBodyBeforeTheDarkScopes()

	/**
	 * Thematiq tokens, settable variables and motion tokens stay out of the light body block.
	 *
	 * @return void
	 */
	public function testThematiqTokensSettableAndMotionTokensStayOffBody(): void {
		$css = $this->builder->build(
			tokens: [
				'--color-primary'                                       => '#154273',
				'--nldesign-component-button-primary-background-color' => '#123456',
				'--color-main-background'                               => '#ffffff',
				'--animation-quick'                                     => '400ms',
			]
		);

		$this->assertSame(expected: ['--color-primary' => '#154273 !important'], actual: $this->lightBody(css: $css));
	}//end testThematiqTokensSettableAndMotionTokensStayOffBody()

	/**
	 * Without a brand-layer override there is no light body block.
	 *
	 * @return void
	 */
	public function testNoBrandLayerOverrideWritesNoBodyBlock(): void {
		$css = $this->builder->build(tokens: ['--nldesign-component-button-primary-background-color' => '#123456']);

		$this->assertNull(actual: $this->lightBody(css: $css));
	}//end testNoBrandLayerOverrideWritesNoBodyBlock()
}//end class
