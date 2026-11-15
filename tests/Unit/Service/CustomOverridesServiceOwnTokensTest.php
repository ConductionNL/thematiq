<?php

/**
 * Own tokens in the overrides file: after the editor's values, without !important, light in
 * :root and dark in both dark scopes, each deprecated one under its notice, in every set's file.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Tests
 * @package   OCA\Thematiq
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 *
 * @spec openspec/specs/own-tokens/spec.md#requirement-own-tokens-are-served-in-both-themes
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\CustomOverridesService;
use OCA\Thematiq\Service\DarkPaletteService;
use OCA\Thematiq\Service\DeprecationRecords;
use OCA\Thematiq\Service\OverridesSetContext;
use OCA\Thematiq\Service\OverridesStorage;
use OCA\Thematiq\Service\OwnTokenService;
use OCA\Thematiq\Service\RuntimeFile\DirectoryRuntimeFileStore;
use OCA\Thematiq\Service\TokenDeprecationService;
use OCA\Thematiq\Service\TokenValueValidator;
use OCP\App\IAppManager;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Real services over a temp app dir and an in-memory app config.
 *
 * @spec openspec/specs/own-tokens/spec.md#requirement-own-tokens-are-served-in-both-themes
 */
final class CustomOverridesServiceOwnTokensTest extends TestCase {

	/**
	 * The temp app dir.
	 *
	 * @var string
	 */
	private string $appDir;

	/**
	 * The stored app values.
	 *
	 * @var array<string, string>
	 */
	private array $stored = [];

	/**
	 * The own tokens.
	 *
	 * @var OwnTokenService
	 */
	private OwnTokenService $ownTokens;

	/**
	 * The deprecations.
	 *
	 * @var TokenDeprecationService
	 */
	private TokenDeprecationService $deprecations;

	/**
	 * The overrides service under test.
	 *
	 * @var CustomOverridesService
	 */
	private CustomOverridesService $overrides;

	/**
	 * Set up.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->appDir = sys_get_temp_dir() . '/thematiq-own-tokens-' . bin2hex(random_bytes(4));
		mkdir($this->appDir . '/css/systems/nldesign', 0777, true);
		copy(\dirname(__DIR__, 3) . '/css/systems/nldesign/defaults.css', $this->appDir . '/css/systems/nldesign/defaults.css');
		$this->stored = [];
		$config = $this->createMock(originalClassName: IConfig::class);
		$config->method('getAppValue')->willReturnCallback(fn (string $app, string $key, $default = '') => ($this->stored[$key] ?? $default));
		$config->method('setAppValue')->willReturnCallback(function (string $app, string $key, $value): void {
			$this->stored[$key] = (string)$value;
		});
		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('getAppPath')->willReturn($this->appDir);
		$parser = new CssParserService();
		$records = new DeprecationRecords(config: $config);
		$this->ownTokens = new OwnTokenService(config: $config, values: new TokenValueValidator(), records: $records);
		$this->deprecations = new TokenDeprecationService(
			records: $records,
			ownTokens: $this->ownTokens,
			appManager: $appManager,
			cssParser: $parser
		);
		$dark = new DarkPaletteService(
			contrast: new ContrastService(),
			parser: $parser,
			appManager: $appManager,
			logger: $this->createMock(originalClassName: LoggerInterface::class)
		);
		$this->overrides = new CustomOverridesService(
			storage: new OverridesStorage(
				store: new DirectoryRuntimeFileStore(root: $this->appDir),
				cssParser: $parser,
				darkPalette: $dark
			),
			setContext: new OverridesSetContext(ownTokens: $this->ownTokens)
		);
	}//end setUp()

	/**
	 * Remove the temp dir.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		exec('rm -rf ' . escapeshellarg($this->appDir));
	}//end tearDown()

	/**
	 * Own tokens come after the editor's values in :root, without !important, and read() leaves them out.
	 *
	 * @return void
	 */
	public function testOwnTokensRenderedAfterOverrides(): void {
		$this->ownTokens->create(input: ['slug' => 'brand-accent', 'label' => 'Brand accent', 'type' => 'color', 'value' => '#e17000']);
		$this->overrides->write(tokens: ['--color-primary' => '#154273']);
		$css = $this->overrides->getRawContent();

		$root = substr($css, 0, (int)strpos($css, '}'));
		$this->assertMatchesRegularExpression(
			pattern: '/--color-primary: #154273 !important;\s+--nldesign-org-brand-accent: #e17000;/',
			string: $root
		);
		$this->assertSame(
			expected: ['--color-primary' => '#154273'],
			actual: $this->overrides->read(),
			message: 'own tokens are not editor overrides'
		);
	}//end testOwnTokensRenderedAfterOverrides()

	/**
	 * Scenario: a user in the dark theme gets the dark value; both dark scopes carry it.
	 *
	 * @return void
	 */
	public function testOwnTokenDarkValue(): void {
		$this->ownTokens->create(input: ['slug' => 'brand-accent', 'label' => 'Brand accent', 'type' => 'color', 'value' => '#e17000', 'darkValue' => '#ff9a3c']);
		$this->overrides->write(tokens: []);
		$css = $this->overrides->getRawContent();

		$this->assertStringContainsString(needle: '--nldesign-org-brand-accent: #e17000;', haystack: $css);
		$this->assertSame(expected: 2, actual: substr_count($css, '--nldesign-org-brand-accent: #ff9a3c;'));
		$this->assertStringContainsString(needle: 'body[data-theme-dark]', haystack: $css);
		$this->assertStringContainsString(needle: '@media (prefers-color-scheme: dark)', haystack: $css);
	}//end testOwnTokenDarkValue()

	/**
	 * A colour without a dark value uses its light value in both themes: no dark declaration.
	 *
	 * @return void
	 */
	public function testColourWithoutDarkValueStaysLight(): void {
		$this->ownTokens->create(input: ['slug' => 'brand-accent', 'label' => 'Brand accent', 'type' => 'color', 'value' => '#e17000']);
		$this->overrides->write(tokens: []);

		$this->assertSame(expected: 1, actual: substr_count($this->overrides->getRawContent(), '--nldesign-org-brand-accent'));
	}//end testColourWithoutDarkValueStaysLight()

	/**
	 * Scenario: a developer inspects a deprecated token; the notice is the line above it.
	 *
	 * @return void
	 */
	public function testDeprecationCommentWritten(): void {
		$this->ownTokens->create(input: ['slug' => 'old-accent', 'label' => 'Old', 'type' => 'color', 'value' => '#aa0000']);
		$this->ownTokens->create(input: ['slug' => 'brand-accent', 'label' => 'Brand', 'type' => 'color', 'value' => '#e17000']);
		$this->deprecations->deprecate(
			token: '--nldesign-org-old-accent',
			input: ['severity' => 'warning', 'replacement' => '--nldesign-org-brand-accent', 'removalDate' => '2027-03-01'],
			today: '2026-10-02'
		);
		$this->overrides->write(tokens: []);

		$this->assertStringContainsString(
			needle: "  /* deprecated (warning): use --nldesign-org-brand-accent, removal 2027-03-01 */\n  --nldesign-org-old-accent: #aa0000;",
			haystack: $this->overrides->getRawContent()
		);
	}//end testDeprecationCommentWritten()

	/**
	 * Own tokens do not depend on the set: rewriteAll() puts them in every set's file.
	 *
	 * @return void
	 */
	public function testEveryFileGetsOwnTokens(): void {
		file_put_contents($this->appDir . '/css/custom-overrides-nextcloud.css', "/* x */\n:root {}\n");
		$this->overrides->write(tokens: ['--color-primary' => '#154273']);
		$this->ownTokens->create(input: ['slug' => 'gap', 'label' => 'Gap', 'type' => 'text', 'value' => '8px']);

		$changed = $this->overrides->rewriteAll();
		sort($changed);

		$this->assertSame(expected: ['custom-overrides-nextcloud.css', 'custom-overrides.css'], actual: $changed);
		$this->assertStringContainsString(
			needle: '--nldesign-org-gap: 8px;',
			haystack: (string)file_get_contents($this->appDir . '/css/custom-overrides-nextcloud.css')
		);
		$this->assertSame(expected: [], actual: $this->overrides->rewriteAll(), message: 'a second run changes nothing');
	}//end testEveryFileGetsOwnTokens()
}//end class
