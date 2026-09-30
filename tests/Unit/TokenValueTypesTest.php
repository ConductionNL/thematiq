<?php

/**
 * The server side of typed token values: the type check on save, dark values per colour,
 * motion tokens, and the opaque colour core theming gets.
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
 * @spec openspec/specs/token-editor-ui/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit;

use OCA\Thematiq\Controller\OverridesController;
use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\CustomOverridesService;
use OCA\Thematiq\Service\CustomTokenSetService;
use OCA\Thematiq\Service\CustomTokenSetValidator;
use OCA\Thematiq\Service\DarkPaletteService;
use OCA\Thematiq\Service\ThemingAuditService;
use OCA\Thematiq\Service\ThemingService;
use OCA\Thematiq\Service\TokenRegistry;
use OCP\App\IAppManager;
use OCP\IConfig;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Real services over a temp app dir; only the request, the audit log and config are doubles.
 *
 * @spec openspec/specs/token-editor-ui/spec.md
 */
class TokenValueTypesTest extends TestCase {

	/**
	 * The temp app dir.
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
	 * The overrides service.
	 *
	 * @var CustomOverridesService
	 */
	private CustomOverridesService $overrides;

	/**
	 * The app manager double.
	 *
	 * @var IAppManager&MockObject
	 */
	private IAppManager $appManager;

	/**
	 * Set up.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->appDir = sys_get_temp_dir() . '/thematiq-value-types-' . bin2hex(random_bytes(4));
		mkdir($this->appDir . '/css/tokens', 0777, true);
		$this->appManager = $this->createMock(IAppManager::class);
		$this->appManager->method('getAppPath')->willReturn($this->appDir);
		$parser = new CssParserService();
		$this->darkPalette = new DarkPaletteService(new ContrastService(), $parser, $this->appManager, $this->createMock(LoggerInterface::class));
		$this->overrides = new CustomOverridesService($this->appManager, $parser, $this->darkPalette);
		$this->overrides->write(tokens: ['--color-primary' => '#000000']);
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
	 * Post overrides and get the response data and status.
	 *
	 * @param array<string, mixed> $params The request params.
	 *
	 * @return array{0: int, 1: array<string, mixed>} Status and data.
	 */
	private function post(array $params): array {
		$request = $this->createMock(IRequest::class);
		$request->method('getParams')->willReturn($params);
		$request->method('getParam')->willReturnCallback(fn (string $key, $default = null) => ($params[$key] ?? $default));
		$response = (new OverridesController(
			'thematiq',
			$request,
			$this->overrides,
			new CssParserService(),
			$this->createMock(ThemingAuditService::class),
			$this->createMock(IConfig::class),
			$this->createMock(ThemingService::class)
		))->setOverrides();

		return [$response->getStatus(), (array)$response->getData()];
	}//end post()

	/**
	 * Scenario: a duration without a unit is refused, naming the token and the type; nothing is written.
	 *
	 * @return void
	 */
	public function testDurationWithoutUnitIs400(): void {
		$before = $this->overrides->getRawContent();
		[$status, $data] = $this->post(['overrides' => ['--animation-quick' => '150']]);

		$this->assertSame(400, $status);
		$this->assertStringContainsString('--animation-quick', json_encode($data));
		$this->assertStringContainsString('duration', json_encode($data));
		$this->assertSame($before, $this->overrides->getRawContent());
	}//end testDurationWithoutUnitIs400()

	/**
	 * Scenario: a malformed colour is refused, naming the token and the type.
	 *
	 * @return void
	 */
	public function testMalformedColourIs400(): void {
		[$status, $data] = $this->post(['overrides' => ['--color-primary' => '#15427']]);

		$this->assertSame(400, $status);
		$this->assertStringContainsString('--color-primary', json_encode($data));
		$this->assertStringContainsString('color', (string)($data['rejected']['--color-primary'] ?? ''));
	}//end testMalformedColourIs400()

	/**
	 * Scenario: an easing curve out of range is refused.
	 *
	 * @return void
	 */
	public function testEasingOutOfRangeIs400(): void {
		[$status] = $this->post(['overrides' => ['--nldesign-animation-easing' => 'cubic-bezier(1.5, 0, 0, 1)']]);

		$this->assertSame(400, $status);
	}//end testEasingOutOfRangeIs400()

	/**
	 * The injection filter still runs after the type check (task 5.7).
	 *
	 * @return void
	 */
	public function testInjectionStillRefusedAfterTypeCheck(): void {
		[$status] = $this->post(['overrides' => ['--border-radius' => '4px; } body { display: none']]);

		$this->assertSame(400, $status);
	}//end testInjectionStillRefusedAfterTypeCheck()

	/**
	 * Scenario: an administrator slows down quick animations. Both names are written.
	 *
	 * @return void
	 */
	public function testMotionOverrideWritesBothNames(): void {
		[$status] = $this->post(['overrides' => ['--animation-quick' => '150ms']]);
		$css = $this->overrides->getRawContent();

		$this->assertSame(200, $status);
		$this->assertStringContainsString('--animation-quick: 150ms !important;', $css);
		$this->assertStringContainsString('--nldesign-animation-quick: 150ms !important;', $css);
		$this->assertSame(['--animation-quick' => '150ms'], $this->overrides->read(), 'the twin is not an editor value');
	}//end testMotionOverrideWritesBothNames()

	/**
	 * Scenario: an administrator picks a custom easing curve.
	 *
	 * @return void
	 */
	public function testCustomEasingIsSaved(): void {
		[$status] = $this->post(['overrides' => ['--nldesign-animation-easing' => 'cubic-bezier(0.2, 0, 0, 1)']]);

		$this->assertSame(200, $status);
		$this->assertStringContainsString('--nldesign-animation-easing: cubic-bezier(0.2, 0, 0, 1) !important;', $this->overrides->getRawContent());
	}//end testCustomEasingIsSaved()

	/**
	 * The motion rows are typed, and the easing token is editable (task 1.3).
	 *
	 * @return void
	 */
	public function testMotionTokensAreTyped(): void {
		$tokens = TokenRegistry::getTokens();

		$this->assertSame('duration', $tokens['--animation-quick']['type']);
		$this->assertSame('duration', $tokens['--animation-slow']['type']);
		$this->assertSame('easing', $tokens['--nldesign-animation-easing']['type']);
	}//end testMotionTokensAreTyped()

	/**
	 * Every `ease` in a theme.css transition reads the easing token, and defaults.css declares it (task 4.4, 5.5).
	 *
	 * @return void
	 */
	public function testThemeTransitionsReadTheEasingToken(): void {
		$root = \dirname(__DIR__, 2);
		$theme = (string)file_get_contents($root . '/css/systems/nldesign/theme.css');
		preg_match_all('/transition[a-z-]*\s*:([^;]*);/i', $theme, $declarations);
		foreach ($declarations[1] as $value) {
			$this->assertDoesNotMatchRegularExpression('/\)\s+ease\b(?!-)/', $value, 'a transition still uses a bare ease: ' . trim($value));
		}

		$this->assertMatchesRegularExpression('/--nldesign-animation-easing:\s*ease;/', (string)file_get_contents($root . '/css/systems/nldesign/defaults.css'));
	}//end testThemeTransitionsReadTheEasingToken()

	/**
	 * Scenario: an administrator gives the primary colour its own dark value. Both dark scopes carry it,
	 * and it reads back apart from a derived one.
	 *
	 * @return void
	 */
	public function testOwnDarkValueIsWrittenAndReadBack(): void {
		[$status] = $this->post(['overrides' => ['--color-primary' => '#154273', '--color-primary-element' => '#154273'], 'darkOverrides' => ['--color-primary' => '#5b9bd5']]);
		$css = $this->overrides->getRawContent();

		$this->assertSame(200, $status);
		$this->assertSame(2, substr_count($css, '--color-primary: #5b9bd5 !important;'));
		$this->assertSame(['--color-primary' => '#5b9bd5'], $this->overrides->readDark());
	}//end testOwnDarkValueIsWrittenAndReadBack()

	/**
	 * Scenario: an empty dark value is derived; it is not reported as the administrator's own.
	 *
	 * @return void
	 */
	public function testEmptyDarkValueIsDerived(): void {
		$this->post(['overrides' => ['--color-primary' => '#154273']]);

		$derived = $this->darkPalette->deriveDarkValue(token: '--color-primary', lightValue: '#154273');
		$this->assertStringContainsString('--color-primary: ' . $derived . ' !important;', $this->overrides->getRawContent());
		$this->assertSame([], $this->overrides->readDark());
	}//end testEmptyDarkValueIsDerived()

	/**
	 * A dark value must be a colour, for a colour token that is overridden.
	 *
	 * @return void
	 */
	public function testDarkValueIsTypeChecked(): void {
		[$status] = $this->post(['overrides' => ['--color-primary' => '#154273'], 'darkOverrides' => ['--color-primary' => 'dark blue']]);
		$this->assertSame(400, $status);

		[$status] = $this->post(['overrides' => ['--color-primary' => '#154273'], 'darkOverrides' => ['--border-radius' => '#000000']]);
		$this->assertSame(400, $status);
	}//end testDarkValueIsTypeChecked()

	/**
	 * Scenario: a translucent primary is synced as its blend over the background; an opaque one unchanged.
	 *
	 * @return void
	 */
	public function testTranslucentPrimaryIsBlendedForCore(): void {
		$stored = [];
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(fn (string $app, string $key, $default = '') => ($stored[$key] ?? $default));
		$config->method('setAppValue')->willReturnCallback(
			function (string $app, string $key, $value) use (&$stored): void {
				$stored[$key] = (string)$value;
			}
		);
		$store = new CustomTokenSetService($this->appManager, $config, new CustomTokenSetValidator(), new ContrastService(), $this->darkPalette);

		$store->store(displayName: 'Doorschijnend', description: '', declarations: ['--nldesign-color-primary' => '#15427380', '--nldesign-color-background' => '#ffffff']);
		$store->store(displayName: 'Dekkend', description: '', declarations: ['--nldesign-color-primary' => '#154273']);

		$manifest = array_column($store->list(), null, 'id');
		$this->assertSame('#8aa0b9', $manifest['custom-doorschijnend']['theming']['primary_color'] ?? null);
		$this->assertSame('#15427380', $manifest['custom-doorschijnend']['theming']['primary_color_original'] ?? null);
		$this->assertSame('#154273', $manifest['custom-dekkend']['theming']['primary_color'] ?? null);
	}//end testTranslucentPrimaryIsBlendedForCore()
}//end class
