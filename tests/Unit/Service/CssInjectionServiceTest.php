<?php

/**
 * Unit tests for CssInjectionService.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/render-event-injection/tasks.md#task-4.2
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\AppBrandService;
use OCA\Thematiq\Service\CssInjectionService;
use OCA\Thematiq\Service\CustomCssService;
use OCA\Thematiq\Service\CustomOverridesService;
use OCA\Thematiq\Service\DesignSystemService;
use OCA\Thematiq\Service\FontService;
use OCA\Thematiq\Service\GroupThemingService;
use OCA\Thematiq\Service\LogoLayerService;
use OCA\Thematiq\Service\RuntimeFile\DirectoryRuntimeFileStore;
use OCA\Thematiq\Service\RuntimeFile\RuntimeFileLocator;
use OCA\Thematiq\Service\StockTokensService;
use OCA\Thematiq\Service\ThemePreviewBannerService;
use OCP\App\IAppManager;
use OCP\IConfig;
use OCP\ITempManager;
use OCP\IURLGenerator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Unit tests for CssInjectionService.
 *
 * `emitStyle()`/`emitStylesheetLink()` are the only two side-effecting calls in the
 * service (they delegate to `\OCP\Util::addStyle()`/`addHeader()`, which
 * requires a full Nextcloud bootstrap this suite does not have). They are
 * overridden via a partial mock so every test can assert the exact call
 * sequence without ever invoking the real static Nextcloud API.
 */
class CssInjectionServiceTest extends TestCase {

	/**
	 * The config mock.
	 *
	 * @var IConfig&MockObject
	 */
	private $config;

	/**
	 * The design system service mock.
	 *
	 * @var DesignSystemService&MockObject
	 */
	private $designSystemService;


	/**
	 * Gates and reads the freeform custom CSS layer.
	 *
	 * @var CustomCssService|\PHPUnit\Framework\MockObject\MockObject
	 */
	private $customCssService;

	/**
	 * The font service mock.
	 *
	 * @var FontService&MockObject
	 */
	private $fontService;

	/**
	 * The URL generator mock.
	 *
	 * @var IURLGenerator&MockObject
	 */
	private $urlGenerator;

	/**
	 * The per-group theming service mock (resolves the effective token set).
	 *
	 * @var GroupThemingService&MockObject
	 */
	private $groupThemingService;

	/**
	 * The theme preview banner service mock.
	 *
	 * The banner has its own dedicated coverage
	 * ({@see ThemePreviewBannerServiceTest}); these tests assert the
	 * stylesheet cascade only, so the mock is left inert.
	 *
	 * @var ThemePreviewBannerService&MockObject
	 */
	private $previewBannerService;

	/**
	 * The logger mock — asserts that a skipped layer is never silent.
	 *
	 * @var LoggerInterface&MockObject
	 */
	private $logger;

	/**
	 * The stock-token resolver mock.
	 *
	 * Inert by default — `getCss()` returns null, which is the "could not read
	 * the instance" answer and therefore the shipped-file behaviour every other
	 * test in this suite was written against.
	 *
	 * @var StockTokensService&MockObject
	 */
	private $stockTokens;

	/**
	 * A real locator over a temporary store; a test puts a runtime file in
	 * place with `$this->runtimeFiles->store()->write()`.
	 *
	 * @var RuntimeFileLocator
	 */
	private RuntimeFileLocator $runtimeFiles;

	/**
	 * The temporary directory the locator's store and app path use.
	 *
	 * @var string
	 */
	private string $runtimeDir;

	/**
	 * The brand-per-app mock; no app has a brand unless a test says so.
	 *
	 * @var AppBrandService&MockObject
	 */
	private $appBrands;

	/**
	 * Set up mocks before each test.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->config = $this->createMock(IConfig::class);
		$this->designSystemService = $this->createMock(DesignSystemService::class);
		$this->customCssService = $this->createMock(CustomCssService::class);
		$this->fontService = $this->createMock(FontService::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->groupThemingService = $this->createMock(GroupThemingService::class);
		$this->previewBannerService = $this->createMock(ThemePreviewBannerService::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->stockTokens = $this->createMock(StockTokensService::class);
		$this->stockTokens->method('getCss')->willReturn(null);
		$this->appBrands = $this->createMock(AppBrandService::class);

		$this->runtimeDir = sys_get_temp_dir() . '/thematiq-injection-' . bin2hex(random_bytes(4));
		// The repository is the app directory, read-only, so shipped files
		// such as img/logos/rijkshuisstijl.svg resolve as they do in a release.
		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getAppPath')->willReturn(\dirname(__DIR__, 3));
		$this->urlGenerator->method('linkTo')->willReturnCallback(
			fn (string $appName, string $file) => '/custom_apps/' . $appName . '/' . $file
		);
		$routes = $this->createMock(IURLGenerator::class);
		$routes->method('linkToRoute')->willReturnCallback(static fn (string $route, array $args): string => 'runtime:' . $args['name']);
		$this->runtimeFiles = new RuntimeFileLocator(
			$appManager,
			new DirectoryRuntimeFileStore($this->runtimeDir . '/store'),
			$routes,
			$this->createMock(ITempManager::class)
		);

		// Default: no group mapping configured, so the resolver returns the
		// plain appconfig token set — byte-identical to pre-per-group behaviour.
		$this->groupThemingService->method('resolveTokenSetForRequest')->willReturnCallback(
			fn () => $this->config->getAppValue('nldesign', 'token_set', 'nextcloud')
		);

		// No blanket `hasFonts()` default here: PHPUnit's InvocationMocker
		// resolves overlapping unconstrained stubs in REGISTRATION order (the
		// first-registered unconstrained stub wins for every call), so a
		// setUp()-level default would silently shadow a test's own
		// `willReturn(true)`. Each test configures `hasFonts()` explicitly
		// instead (an unconfigured bool-returning mock method defaults to
		// `false`, which is what every non-font test below relies on).
	}//end setUp()

	/**
	 * The position of the first entry that starts with a prefix, failing the test when there is none.
	 *
	 * @param array<int, string> $log    The emitted entries, in order.
	 * @param string             $prefix The start to look for.
	 *
	 * @return int The index.
	 */
	private function indexStartingWith(array $log, string $prefix): int {
		foreach ($log as $index => $entry) {
			if (str_starts_with($entry, $prefix) === true) {
				return $index;
			}
		}

		$this->fail('no entry starts with ' . $prefix . ' in ' . json_encode($log));
	}//end indexStartingWith()

	protected function tearDown(): void {
		$remove = function (string $path) use (&$remove): void {
			if (is_dir($path) === true) {
				foreach (scandir($path) ?: [] as $entry) {
					if ($entry !== '.' && $entry !== '..') {
						$remove($path . '/' . $entry);
					}
				}

				rmdir($path);
			} elseif (is_file($path) === true) {
				unlink($path);
			}
		};
		$remove($this->runtimeDir);
		parent::tearDown();
	}//end tearDown()

	/**
	 * Build the service under test as a partial mock that captures every
	 * `emitStyle()`/`emitStylesheetLink()` call (in order) instead of calling the
	 * real static Nextcloud API.
	 *
	 * @param array<int, string> $styleLog Populated with each `emitStyle()` file, in call order.
	 * @param array<int, string> $fontLog Populated with each `emitStylesheetLink()` url, in call order.
	 *
	 * @return CssInjectionService&MockObject The service under test.
	 */
	private function buildService(array &$styleLog, array &$fontLog): CssInjectionService {
		$service = $this->getMockBuilder(CssInjectionService::class)
			->setConstructorArgs(
				[
					$this->config,
					$this->designSystemService,
					$this->customCssService,
					$this->fontService,
					$this->urlGenerator,
					$this->groupThemingService,
					$this->previewBannerService,
					$this->logger,
					$this->stockTokens,
					$this->runtimeFiles,
					new LogoLayerService($this->config, $this->urlGenerator, $this->logger, $this->runtimeFiles),
					$this->appBrands,
				]
			)
			->onlyMethods(['emitStyle', 'emitStylesheetLink'])
			->getMock();

		$service->method('emitStyle')->willReturnCallback(
			function (string $file) use (&$styleLog) {
				$styleLog[] = $file;
			}
		);
		$service->method('emitStylesheetLink')->willReturnCallback(
			function (string $url) use (&$fontLog) {
				$fontLog[] = $url;
			}
		);

		return $service;
	}//end buildService()

	/**
	 * Configure the config mock to resolve to a given appconfig value map,
	 * defaulting `themed_contexts` to absent (all contexts themed).
	 *
	 * @param array<string, string> $overrides Appconfig key => value overrides.
	 *
	 * @return void
	 */
	private function configureAppValues(array $overrides = []): void {
		$defaults = [
			'token_set' => 'nextcloud',
			'hide_slogan' => '0',
			'show_menu_labels' => '0',
			'themed_contexts' => '[]',
		];
		$values = array_merge($defaults, $overrides);

		$this->config->method('getAppValue')->willReturnCallback(
			function (string $app, string $key, string $default) use ($values) {
				return $values[$key] ?? $default;
			}
		);
	}//end configureAppValues()

	/**
	 * The standard nldesign order: design-system stylesheets (declared
	 * order), token set, icon/error contrast, custom-overrides — layers 1-8.
	 */
	public function testStandardNldesignOrder(): void {
		$this->configureAppValues(['token_set' => 'rijkshuisstijl']);
		$this->designSystemService->method('getTokenSetMeta')->with('rijkshuisstijl')
			->willReturn(['design_system' => 'nldesign']);
		$this->designSystemService->method('getDesignSystem')->with('nldesign')->willReturn(
			[
				'id' => 'nldesign',
				'name' => 'NL Design System',
				'description' => '',
				'stylesheets' => [
					'systems/nldesign/fonts',
					'systems/nldesign/defaults',
					'systems/nldesign/utrecht-bridge',
					'systems/nldesign/theme',
					'systems/nldesign/overrides',
					'systems/nldesign/element-overrides',
				],
			]
		);

		// Saved overrides live in the runtime store and are linked by route,
		// not emitted as a static app stylesheet.
		$this->runtimeFiles->store()->write('css/custom-overrides.css', ':root { --color-primary: #154273; }');

		$styleLog = [];
		$fontLog = [];
		$service = $this->buildService(styleLog: $styleLog, fontLog: $fontLog);
		$service->inject('user');

		$this->assertSame(
			[
				'systems/nldesign/fonts',
				'systems/nldesign/defaults',
				'systems/nldesign/utrecht-bridge',
				'systems/nldesign/theme',
				'systems/nldesign/overrides',
				'systems/nldesign/element-overrides',
				'tokens/rijkshuisstijl',
				'icon-contrast',
				'error-contrast',
				'theme-scopes',
				'component-scopes',
			],
			$styleLog
		);
		$this->assertCount(1, $fontLog);
		$this->assertStringStartsWith('runtime:css/custom-overrides.css?v=', $fontLog[0]);
	}//end testStandardNldesignOrder()

	/**
	 * The "none" design system (stock Nextcloud) loads no layer 1-7 stylesheet
	 * and no token/contrast CSS. It DOES load component-scopes, and then
	 * custom-overrides on top of it.
	 *
	 * The component layer is what makes a stock instance themable at all: every
	 * instance starts on the stock `nextcloud` set, so without it an admin had
	 * to pick some other theme before the token editor could change anything —
	 * you needed a theme in order to make one. It only redirects Nextcloud's own
	 * variables inside a component's subtree and every component token is
	 * undeclared until somebody sets one, so an untouched stock instance still
	 * renders byte-identically to stock.
	 */
	public function testNoneDesignSystemLoadsOnlyTheComponentLayer(): void {
		$this->configureAppValues(['token_set' => 'nextcloud']);
		$this->designSystemService->method('getTokenSetMeta')->willReturn(['design_system' => 'none']);
		$this->designSystemService->method('getDesignSystem')->with('none')->willReturn(
			[
				'id' => 'none',
				'name' => 'No design system',
				'description' => '',
				'stylesheets' => [],
			]
		);

		$styleLog = [];
		$fontLog = [];
		$service = $this->buildService(styleLog: $styleLog, fontLog: $fontLog);
		$service->inject('user');

		$this->assertSame(['theme-scopes', 'component-scopes'], $styleLog);
		$this->assertSame([], $fontLog);
	}//end testNoneDesignSystemLoadsOnlyTheComponentLayer()

	/**
	 * A custom set saved off stock Nextcloud is on `none` too, but unlike the
	 * stock set it HAS a file, and that file is where its values live. It is
	 * loaded before the component layer that reads it, and the set gets an
	 * overrides file of its own — neither the shared one nor the stock set's.
	 */
	public function testACustomSetOnNoneLoadsItsOwnTokenFile(): void {
		$this->configureAppValues(['token_set' => 'custom-openwoo']);
		$this->designSystemService->method('getTokenSetMeta')->willReturn(['design_system' => 'none']);
		$this->designSystemService->method('getDesignSystem')->with('none')->willReturn(
			[
				'id' => 'none',
				'name' => 'No design system',
				'description' => '',
				'stylesheets' => [],
			]
		);

		$styleLog = [];
		$fontLog = [];
		$service = $this->buildService(styleLog: $styleLog, fontLog: $fontLog);
		$service->inject('user');

		$this->assertSame(['tokens/custom-openwoo', 'theme-scopes', 'component-scopes'], $styleLog);
	}//end testACustomSetOnNoneLoadsItsOwnTokenFile()

	/**
	 * Saved overrides are the last stylesheet link the page gets, after every
	 * design-system and token layer.
	 *
	 * @spec openspec/changes/runtime-files-in-appdata/specs/runtime-file-storage/spec.md
	 */
	public function testCustomOverridesAlwaysLoadedLast(): void {
		$this->configureAppValues();
		$this->designSystemService->method('getTokenSetMeta')->willReturn(['design_system' => 'nldesign']);
		$this->designSystemService->method('getDesignSystem')->willReturn(
			[
				'id' => 'nldesign',
				'name' => 'NL Design System',
				'description' => '',
				'stylesheets' => ['systems/nldesign/fonts'],
			]
		);
		$this->runtimeFiles->store()->write('css/custom-overrides-nextcloud.css', ':root {}');

		$styleLog = [];
		$fontLog = [];
		$service = $this->buildService(styleLog: $styleLog, fontLog: $fontLog);
		$service->inject('user');

		$this->assertNotContains('custom-overrides-nextcloud', $styleLog, 'overrides are not a static app stylesheet any more');
		$this->assertStringStartsWith('runtime:css/custom-overrides-nextcloud.css?v=', (string)end($fontLog));
	}//end testCustomOverridesAlwaysLoadedLast()

	/**
	 * hide-slogan and show-menu-labels load last, after custom-overrides,
	 * only when their respective appconfig flags are enabled.
	 */
	public function testConditionalStylesheetsLoadedWhenEnabled(): void {
		$this->configureAppValues(['hide_slogan' => '1', 'show_menu_labels' => '1']);
		$this->designSystemService->method('getTokenSetMeta')->willReturn(['design_system' => 'nldesign']);
		$this->designSystemService->method('getDesignSystem')->willReturn(
			[
				'id' => 'nldesign',
				'name' => 'NL Design System',
				'description' => '',
				'stylesheets' => [],
			]
		);

		$styleLog = [];
		$fontLog = [];
		$service = $this->buildService(styleLog: $styleLog, fontLog: $fontLog);
		$service->inject('user');

		$this->assertSame(
			['tokens/nextcloud', 'icon-contrast', 'error-contrast', 'theme-scopes', 'component-scopes', 'hide-slogan', 'show-menu-labels'],
			$styleLog
		);
	}//end testConditionalStylesheetsLoadedWhenEnabled()

	/**
	 * The conditional stylesheets are absent when their flags are disabled.
	 */
	public function testConditionalStylesheetsAbsentWhenDisabled(): void {
		$this->configureAppValues();
		$this->designSystemService->method('getTokenSetMeta')->willReturn(['design_system' => 'nldesign']);
		$this->designSystemService->method('getDesignSystem')->willReturn(
			[
				'id' => 'nldesign',
				'name' => 'NL Design System',
				'description' => '',
				'stylesheets' => [],
			]
		);

		$styleLog = [];
		$fontLog = [];
		$service = $this->buildService(styleLog: $styleLog, fontLog: $fontLog);
		$service->inject('user');

		$this->assertNotContains('hide-slogan', $styleLog);
		$this->assertNotContains('show-menu-labels', $styleLog);
		$this->assertNotContains('primary-lock', $styleLog);
	}//end testConditionalStylesheetsAbsentWhenDisabled()

	/**
	 * `primary-lock` is emitted only while the setting is on, and LAST of all.
	 *
	 * It and `custom-overrides.css` both write `--nldesign-component-*` at
	 * `:root` with `!important`, so the later of the two wins. While the
	 * setting is on the brand primary is meant to beat a per-component value
	 * the admin stored earlier, which is only true if this layer comes after
	 * the overrides — hence the position is asserted, not just the presence.
	 *
	 * @spec openspec/specs/component-tokens/spec.md
	 */
	public function testPrimaryLockEmittedLastWhenTheSettingIsOn(): void {
		$this->configureAppValues(['primary_drives_components' => '1']);
		$this->runtimeFiles->store()->write('css/custom-overrides-nextcloud.css', ':root {}');
		$this->designSystemService->method('getTokenSetMeta')->willReturn(['design_system' => 'nldesign']);
		$this->designSystemService->method('getDesignSystem')->willReturn(
			[
				'id' => 'nldesign',
				'name' => 'NL Design System',
				'description' => '',
				'stylesheets' => [],
			]
		);

		$styleLog = [];
		$fontLog = [];
		$service = $this->buildService(styleLog: $styleLog, fontLog: $fontLog);
		$service->inject('user');

		// Nextcloud prints every addStyle() stylesheet before every header,
		// and the saved overrides are a header, so the lock must be one too.
		$this->assertNotContains('primary-lock', $styleLog);
		$this->assertStringContainsString('css/primary-lock.css', (string)end($fontLog), 'primary-lock is the last link emitted');
		$this->assertGreaterThan(
			$this->indexStartingWith($fontLog, 'runtime:css/custom-overrides-nextcloud.css'),
			$this->indexStartingWith($fontLog, '/custom_apps/thematiq/css/primary-lock.css'),
			'primary-lock must come after custom-overrides, or the stored value would win'
		);
	}//end testPrimaryLockEmittedLastWhenTheSettingIsOn()

	/**
	 * Custom fonts inject a `<link>` header (not a static stylesheet) after
	 * the token-set styles, only when at least one font is configured, and
	 * never for the "none" design system.
	 */
	public function testCustomFontsInjectedWhenConfigured(): void {
		$this->configureAppValues();
		$this->designSystemService->method('getTokenSetMeta')->willReturn(['design_system' => 'nldesign']);
		$this->designSystemService->method('getDesignSystem')->willReturn(
			[
				'id' => 'nldesign',
				'name' => 'NL Design System',
				'description' => '',
				'stylesheets' => [],
			]
		);
		$this->fontService->method('hasFonts')->willReturn(true);
		$this->fontService->method('getRevision')->willReturn(3);
		$this->urlGenerator->method('linkToRoute')->with('thematiq.font.css')
			->willReturn('https://example.test/apps/thematiq/fonts/css');

		$styleLog = [];
		$fontLog = [];
		$service = $this->buildService(styleLog: $styleLog, fontLog: $fontLog);
		$service->inject('user');

		$this->assertSame(['https://example.test/apps/thematiq/fonts/css?v=3'], $fontLog);
	}//end testCustomFontsInjectedWhenConfigured()

	/**
	 * No font link is emitted for the "none" design system, even with fonts configured.
	 */
	public function testCustomFontsNotInjectedForNoneDesignSystem(): void {
		$this->configureAppValues();
		$this->designSystemService->method('getTokenSetMeta')->willReturn(['design_system' => 'none']);
		$this->designSystemService->method('getDesignSystem')->willReturn(
			[
				'id' => 'none',
				'name' => 'No design system',
				'description' => '',
				'stylesheets' => [],
			]
		);
		$this->fontService->method('hasFonts')->willReturn(true);

		$styleLog = [];
		$fontLog = [];
		$service = $this->buildService(styleLog: $styleLog, fontLog: $fontLog);
		$service->inject('user');

		$this->assertSame([], $fontLog);
	}//end testCustomFontsNotInjectedForNoneDesignSystem()

	/**
	 * When the active design system is `lasuite` AND `marianne_enabled` is
	 * `'1'`, `systems/lasuite/marianne` is emitted immediately after the
	 * declared lasuite stylesheets (which already include the base
	 * `systems/lasuite/fonts` layer).
	 *
	 * @spec openspec/specs/marianne-font/spec.md
	 */
	public function testMarianneEmittedWhenLasuiteAndGateEnabled(): void {
		$this->configureAppValues(['token_set' => 'lasuite', 'marianne_enabled' => '1']);
		$this->designSystemService->method('getTokenSetMeta')->with('lasuite')
			->willReturn(['design_system' => 'lasuite']);
		$this->designSystemService->method('getDesignSystem')->with('lasuite')->willReturn(
			[
				'id' => 'lasuite',
				'name' => 'La Suite numérique',
				'description' => '',
				'stylesheets' => [
					'systems/lasuite/fonts',
					'systems/lasuite/defaults',
					'systems/lasuite/brand-override',
					'systems/lasuite/bridge',
					'systems/lasuite/element-overrides',
				],
			]
		);

		$styleLog = [];
		$fontLog = [];
		$service = $this->buildService(styleLog: $styleLog, fontLog: $fontLog);
		$service->inject('user');

		$this->assertSame(
			[
				'systems/lasuite/fonts',
				'systems/lasuite/defaults',
				'systems/lasuite/brand-override',
				'systems/lasuite/bridge',
				'systems/lasuite/element-overrides',
				'systems/lasuite/marianne',
				'tokens/lasuite',
				'icon-contrast',
				'error-contrast',
				'theme-scopes',
				'component-scopes',
			],
			$styleLog
		);

		$fontsIndex = array_search('systems/lasuite/fonts', $styleLog, true);
		$marianneIndex = array_search('systems/lasuite/marianne', $styleLog, true);
		$this->assertGreaterThan($fontsIndex, $marianneIndex, 'marianne.css must load after the base fonts layer.');
	}//end testMarianneEmittedWhenLasuiteAndGateEnabled()

	/**
	 * `systems/lasuite/marianne` is NOT emitted when the gate is off (the
	 * default), even for the `lasuite` design system.
	 *
	 * @spec openspec/specs/marianne-font/spec.md
	 */
	public function testMarianneNotEmittedWhenGateDisabled(): void {
		$this->configureAppValues(['token_set' => 'lasuite', 'marianne_enabled' => '0']);
		$this->designSystemService->method('getTokenSetMeta')->willReturn(['design_system' => 'lasuite']);
		$this->designSystemService->method('getDesignSystem')->willReturn(
			[
				'id' => 'lasuite',
				'name' => 'La Suite numérique',
				'description' => '',
				'stylesheets' => ['systems/lasuite/fonts'],
			]
		);

		$styleLog = [];
		$fontLog = [];
		$service = $this->buildService(styleLog: $styleLog, fontLog: $fontLog);
		$service->inject('user');

		$this->assertNotContains('systems/lasuite/marianne', $styleLog);
	}//end testMarianneNotEmittedWhenGateDisabled()

	/**
	 * `systems/lasuite/marianne` is NOT emitted for a non-lasuite design
	 * system, even when `marianne_enabled` is `'1'` (a stray flag from a
	 * previous lasuite session must not leak into another design system).
	 *
	 * @spec openspec/specs/marianne-font/spec.md
	 */
	public function testMarianneNotEmittedForNonLasuiteDesignSystem(): void {
		$this->configureAppValues(['token_set' => 'rijkshuisstijl', 'marianne_enabled' => '1']);
		$this->designSystemService->method('getTokenSetMeta')->willReturn(['design_system' => 'nldesign']);
		$this->designSystemService->method('getDesignSystem')->willReturn(
			[
				'id' => 'nldesign',
				'name' => 'NL Design System',
				'description' => '',
				'stylesheets' => ['systems/nldesign/fonts'],
			]
		);

		$styleLog = [];
		$fontLog = [];
		$service = $this->buildService(styleLog: $styleLog, fontLog: $fontLog);
		$service->inject('user');

		$this->assertNotContains('systems/lasuite/marianne', $styleLog);
	}//end testMarianneNotEmittedForNonLasuiteDesignSystem()

	/**
	 * Absent `themed_contexts` themes every context (byte-identical default).
	 */
	public function testAbsentThemedContextsThemesEveryContext(): void {
		$this->configureAppValues(['themed_contexts' => '[]']);
		$this->designSystemService->method('getTokenSetMeta')->willReturn(['design_system' => 'nldesign']);
		$this->designSystemService->method('getDesignSystem')->willReturn(
			[
				'id' => 'nldesign',
				'name' => 'NL Design System',
				'description' => '',
				'stylesheets' => [],
			]
		);

		foreach (['user', 'login', 'guest', 'public', 'error'] as $context) {
			$styleLog = [];
			$fontLog = [];
			$service = $this->buildService(styleLog: $styleLog, fontLog: $fontLog);
			$service->inject($context);

			$this->assertContains('component-scopes', $styleLog, 'context ' . $context . ' must be themed');
		}
	}//end testAbsentThemedContextsThemesEveryContext()

	/**
	 * `["user"]` excludes every other configured context — `login` injects
	 * nothing, `user` remains fully themed.
	 */
	public function testConfiguredListExcludesUnlistedContexts(): void {
		$this->configureAppValues(['themed_contexts' => '["user"]']);
		$this->designSystemService->method('getTokenSetMeta')->willReturn(['design_system' => 'nldesign']);
		$this->designSystemService->method('getDesignSystem')->willReturn(
			[
				'id' => 'nldesign',
				'name' => 'NL Design System',
				'description' => '',
				'stylesheets' => [],
			]
		);

		$loginStyleLog = [];
		$loginFontLog = [];
		$loginService = $this->buildService(styleLog: $loginStyleLog, fontLog: $loginFontLog);
		$loginService->inject('login');
		$this->assertSame([], $loginStyleLog);

		$userStyleLog = [];
		$userFontLog = [];
		$userService = $this->buildService(styleLog: $userStyleLog, fontLog: $userFontLog);
		$userService->inject('user');
		$this->assertContains('component-scopes', $userStyleLog);
	}//end testConfiguredListExcludesUnlistedContexts()

	/**
	 * Unparseable JSON in `themed_contexts` fails open to themed, without raising.
	 */
	public function testInvalidJsonThemedContextsFailsOpen(): void {
		$this->configureAppValues(['themed_contexts' => 'not-json{']);
		$this->designSystemService->method('getTokenSetMeta')->willReturn(['design_system' => 'nldesign']);
		$this->designSystemService->method('getDesignSystem')->willReturn(
			[
				'id' => 'nldesign',
				'name' => 'NL Design System',
				'description' => '',
				'stylesheets' => [],
			]
		);

		$styleLog = [];
		$fontLog = [];
		$service = $this->buildService(styleLog: $styleLog, fontLog: $fontLog);
		$service->inject('public');

		$this->assertContains('component-scopes', $styleLog);
	}//end testInvalidJsonThemedContextsFailsOpen()

	/**
	 * A non-array JSON value in `themed_contexts` also fails open to themed.
	 */
	public function testNonArrayJsonThemedContextsFailsOpen(): void {
		$this->configureAppValues(['themed_contexts' => '"not-an-array"']);
		$this->designSystemService->method('getTokenSetMeta')->willReturn(['design_system' => 'nldesign']);
		$this->designSystemService->method('getDesignSystem')->willReturn(
			[
				'id' => 'nldesign',
				'name' => 'NL Design System',
				'description' => '',
				'stylesheets' => [],
			]
		);

		$styleLog = [];
		$fontLog = [];
		$service = $this->buildService(styleLog: $styleLog, fontLog: $fontLog);
		$service->inject('guest');

		$this->assertContains('component-scopes', $styleLog);
	}//end testNonArrayJsonThemedContextsFailsOpen()

	/**
	 * An unrecognized context value (a future/unmapped `renderAs`) is always
	 * themed, even when a configured list would otherwise exclude "user".
	 */
	public function testUnknownContextAlwaysThemed(): void {
		$this->configureAppValues(['themed_contexts' => '["login"]']);
		$this->designSystemService->method('getTokenSetMeta')->willReturn(['design_system' => 'nldesign']);
		$this->designSystemService->method('getDesignSystem')->willReturn(
			[
				'id' => 'nldesign',
				'name' => 'NL Design System',
				'description' => '',
				'stylesheets' => [],
			]
		);

		$styleLog = [];
		$fontLog = [];
		$service = $this->buildService(styleLog: $styleLog, fontLog: $fontLog);
		$service->inject('blank');

		$this->assertContains('component-scopes', $styleLog);
	}//end testUnknownContextAlwaysThemed()

	/**
	 * The freeform layer is emitted AFTER custom-overrides so administrator
	 * intent wins the cascade.
	 *
	 * @spec openspec/specs/custom-css-freeform/spec.md
	 */
	public function testCustomCssEmittedLastWhenEnabledAndPresent(): void {
		$this->configureAppValues();
		$this->designSystemService->method('getTokenSetMeta')->willReturn([]);
		$this->designSystemService->method('getDesignSystem')->willReturn(['stylesheets' => []]);
		$this->customCssService->method('isEnabled')->willReturn(true);
		$this->customCssService->method('hasContent')->willReturn(true);
		$this->runtimeFiles->store()->write('css/custom-overrides-nextcloud.css', ':root {}');

		$styleLog = [];
		$fontLog = [];
		$service = $this->buildService(styleLog: $styleLog, fontLog: $fontLog);
		$service->inject('user');

		$this->assertGreaterThan(
			$this->indexStartingWith($fontLog, 'runtime:css/custom-overrides-nextcloud.css'),
			$this->indexStartingWith($fontLog, 'runtime:css/custom-css.css'),
			'custom-css must be emitted AFTER custom-overrides so it wins the cascade.'
		);
	}//end testCustomCssEmittedLastWhenEnabledAndPresent()

	/**
	 * An instance that never opted in loads nothing, even if a file exists.
	 *
	 * @spec openspec/specs/custom-css-freeform/spec.md
	 */
	public function testCustomCssNotEmittedWhenDisabled(): void {
		$this->configureAppValues();
		$this->designSystemService->method('getTokenSetMeta')->willReturn([]);
		$this->designSystemService->method('getDesignSystem')->willReturn(['stylesheets' => []]);
		$this->customCssService->method('isEnabled')->willReturn(false);
		$this->customCssService->method('hasContent')->willReturn(true);

		$styleLog = [];
		$fontLog = [];
		$service = $this->buildService(styleLog: $styleLog, fontLog: $fontLog);
		$service->inject('user');

		$this->assertNotContains('custom-css', $styleLog);
	}//end testCustomCssNotEmittedWhenDisabled()

	/**
	 * Enabled but empty must not emit a pointless <link>.
	 *
	 * @spec openspec/specs/custom-css-freeform/spec.md
	 */
	public function testCustomCssNotEmittedWhenEmpty(): void {
		$this->configureAppValues();
		$this->designSystemService->method('getTokenSetMeta')->willReturn([]);
		$this->designSystemService->method('getDesignSystem')->willReturn(['stylesheets' => []]);
		$this->customCssService->method('isEnabled')->willReturn(true);
		$this->customCssService->method('hasContent')->willReturn(false);

		$styleLog = [];
		$fontLog = [];
		$service = $this->buildService(styleLog: $styleLog, fontLog: $fontLog);
		$service->inject('user');

		$this->assertNotContains('custom-css', $styleLog);
	}//end testCustomCssNotEmittedWhenEmpty()

	/**
	 * Configure the mocks so every layer after the overrides layer has
	 * something observable to emit: a custom font (layer 4.5), both
	 * conditional stylesheets (layer 5) and the preview banner (layer 6).
	 *
	 * Without this the "later layers still ran" assertions below would be
	 * vacuous — an empty style log is what a cancelled cascade produces too.
	 *
	 * @return void
	 */
	private function configureAllLaterLayers(): void {
		$this->configureAppValues(
			[
				'hide_slogan' => '1',
				'show_menu_labels' => '1',
			]
		);
		$this->designSystemService->method('getTokenSetMeta')->willReturn(['design_system' => 'nldesign']);
		$this->designSystemService->method('getDesignSystem')->willReturn(
			['stylesheets' => ['systems/nldesign/fonts']]
		);
		$this->fontService->method('hasFonts')->willReturn(true);
		$this->urlGenerator->method('linkToRoute')->willReturn('/index.php/apps/thematiq/fonts.css');
	}//end configureAllLaterLayers()

	/**
	 * Rendering a page writes nothing. With no saved overrides there is no
	 * file to link, none is created, and every later layer still runs.
	 *
	 * The injector used to create an empty overrides file inside the app
	 * directory on every render, which put a code integrity warning on every
	 * instance and failed outright on a read-only app directory.
	 *
	 * @spec openspec/changes/runtime-files-in-appdata/specs/runtime-file-storage/spec.md
	 */
	public function testRenderingWritesNothingAndLaterLayersStillRun(): void {
		$this->configureAllLaterLayers();

		$bannerInjected = false;
		$this->previewBannerService->method('inject')->willReturnCallback(
			function () use (&$bannerInjected) {
				$bannerInjected = true;
			}
		);

		$styleLog = [];
		$fontLog = [];
		$service = $this->buildService(styleLog: $styleLog, fontLog: $fontLog);
		$service->inject('user');

		$this->assertFalse($this->runtimeFiles->store()->exists('css/custom-overrides-nextcloud.css'), 'rendering created an overrides file');
		$this->assertSame([], $this->runtimeFiles->store()->listDirectory('css'), 'rendering stored a file');
		$this->assertContains('systems/nldesign/fonts', $styleLog);
		$this->assertCount(1, $fontLog, 'only the fonts link: nothing was saved, so no overrides link');
		$this->assertStringContainsString('/index.php/apps/thematiq/fonts.css', $fontLog[0]);
		$this->assertContains('hide-slogan', $styleLog, 'layer 5 was cancelled');
		$this->assertContains('show-menu-labels', $styleLog, 'layer 5 was cancelled');
		$this->assertTrue($bannerInjected, 'layer 6 (preview banner) was cancelled');
	}//end testRenderingWritesNothingAndLaterLayersStillRun()

	/**
	 * No saved overrides is the normal state of a fresh instance, not a
	 * failure: nothing is logged for it.
	 *
	 * @spec openspec/changes/runtime-files-in-appdata/specs/runtime-file-storage/spec.md
	 */
	public function testNoSavedOverridesIsNotAWarning(): void {
		$this->configureAllLaterLayers();

		$warnings = [];
		$this->logger->method('warning')->willReturnCallback(
			function (string $message) use (&$warnings) {
				$warnings[] = $message;
			}
		);

		$styleLog = [];
		$fontLog = [];
		$service = $this->buildService(styleLog: $styleLog, fontLog: $fontLog);
		$service->inject('user');

		$this->assertSame([], $warnings);
	}//end testNoSavedOverridesIsNotAWarning()

	/**
	 * The isolation is general, not a special case for the overrides layer:
	 * a failure in an EARLY layer must not cancel the later ones either.
	 *
	 * @spec openspec/specs/css-architecture/spec.md#standard-css-load-order-for-nldesign-design-system
	 */
	public function testAFailingDesignSystemLayerDoesNotCancelTheLaterLayers(): void {
		$this->configureAppValues(['hide_slogan' => '1']);
		$this->runtimeFiles->store()->write('css/custom-overrides-nextcloud.css', ':root {}');
		$this->designSystemService->method('getTokenSetMeta')->willReturn(['design_system' => 'nldesign']);
		$this->designSystemService->method('getDesignSystem')->willThrowException(
			new RuntimeException('design-systems.json is unreadable')
		);

		$bannerInjected = false;
		$this->previewBannerService->method('inject')->willReturnCallback(
			function () use (&$bannerInjected) {
				$bannerInjected = true;
			}
		);

		$styleLog = [];
		$fontLog = [];
		$service = $this->buildService(styleLog: $styleLog, fontLog: $fontLog);
		$service->inject('user');

		$this->assertStringStartsWith('runtime:css/custom-overrides-nextcloud.css', (string)($fontLog[0] ?? ''), 'layer 4 was cancelled');
		$this->assertContains('hide-slogan', $styleLog, 'layer 5 was cancelled');
		$this->assertTrue($bannerInjected, 'layer 6 (preview banner) was cancelled');
	}//end testAFailingDesignSystemLayerDoesNotCancelTheLaterLayers()

	/**
	 * A failure in the LAST layer must not escape `inject()` either — it used
	 * to reach the listener's catch-all, which is what made the whole class
	 * of failures invisible.
	 *
	 * @spec openspec/specs/theme-preview/spec.md
	 */
	public function testAFailingPreviewBannerIsContainedAndLogged(): void {
		$this->configureAllLaterLayers();
		$this->previewBannerService->method('inject')->willThrowException(
			new RuntimeException('initial state unavailable')
		);

		$warnings = [];
		$this->logger->method('warning')->willReturnCallback(
			function (string $message) use (&$warnings) {
				$warnings[] = $message;
			}
		);

		$styleLog = [];
		$fontLog = [];
		$service = $this->buildService(styleLog: $styleLog, fontLog: $fontLog);
		$service->inject('user');

		$this->assertContains('component-scopes', $styleLog);
		$this->assertCount(1, $warnings);
		$this->assertStringContainsString('preview-banner', $warnings[0]);
	}//end testAFailingPreviewBannerIsContainedAndLogged()

	/**
	 * The prerequisite block is NOT a layer: if the active token set cannot be
	 * resolved there is nothing for any layer to be a function of, so the
	 * render is left unthemed — but it is logged, and it still does not throw.
	 *
	 * @spec openspec/specs/css-architecture/spec.md#standard-css-load-order-for-nldesign-design-system
	 */
	public function testAnUnresolvableTokenSetLeavesThePageUnthemedAndLogged(): void {
		$this->configureAppValues();
		$this->designSystemService->method('getTokenSetMeta')->willThrowException(
			new RuntimeException('token set manifest unreadable')
		);

		$warnings = [];
		$this->logger->method('warning')->willReturnCallback(
			function (string $message) use (&$warnings) {
				$warnings[] = $message;
			}
		);

		$styleLog = [];
		$fontLog = [];
		$service = $this->buildService(styleLog: $styleLog, fontLog: $fontLog);
		$service->inject('user');

		$this->assertSame([], $styleLog);
		$this->assertCount(1, $warnings);
		$this->assertStringContainsString('could not resolve the active token set', $warnings[0]);
	}//end testAnUnresolvableTokenSetLeavesThePageUnthemedAndLogged()

	/**
	 * Emit the design system + token set for one set, and return the log.
	 *
	 * @param string $tokenSet The selected set.
	 *
	 * @return array<int, string> The emitted stylesheets, in order.
	 */
	private function emitFor(string $tokenSet): array {
		$this->configureAppValues(['token_set' => $tokenSet]);
		$this->designSystemService->method('getTokenSetMeta')->with($tokenSet)
			->willReturn(['design_system' => 'nldesign']);
		$this->designSystemService->method('getDesignSystem')->with('nldesign')->willReturn(
			[
				'id' => 'nldesign',
				'name' => 'NL Design System',
				'description' => '',
				'stylesheets' => ['systems/nldesign/element-overrides'],
			]
		);

		$styleLog = [];
		$fontLog = [];
		$this->buildService(styleLog: $styleLog, fontLog: $fontLog)->inject('user');

		return $styleLog;
	}//end emitFor()

	/**
	 * A token set that ships element overrides gets them AFTER its tokens.
	 *
	 * 🔴 Order is the whole point. The override exists to beat the design
	 * system's shared `element-overrides.css`, and it can only do that by being
	 * emitted later. `frankendesk` is the one set that ships such a file today:
	 * lasuite's shared rule masks the header logo to a single colour, which
	 * would destroy a deliberately two-tone mark.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/app-token-set-selection/spec.md
	 */
	public function testASetWithElementOverridesGetsThemAfterItsTokens(): void {
		$emitted = $this->emitFor('frankendesk');

		$this->assertContains('token-overrides/frankendesk', $emitted);
		$this->assertGreaterThan(
			array_search('systems/nldesign/element-overrides', $emitted, true),
			array_search('token-overrides/frankendesk', $emitted, true),
			'the set override must come AFTER the shared element overrides, or it cannot win'
		);
		$this->assertGreaterThan(
			array_search('tokens/frankendesk', $emitted, true),
			array_search('token-overrides/frankendesk', $emitted, true),
			'and after its own tokens, whose values it reads'
		);
	}//end testASetWithElementOverridesGetsThemAfterItsTokens()

	/**
	 * A set without an overrides file loads nothing extra.
	 *
	 * The control: without this, the test above would pass on an
	 * implementation that emitted a `token-overrides/` stylesheet for EVERY
	 * set — including the ~45 that have no such file, each one a 404.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/app-token-set-selection/spec.md
	 */
	public function testASetWithoutElementOverridesLoadsNothingExtra(): void {
		$emitted = $this->emitFor('rijkshuisstijl');

		$this->assertContains('tokens/rijkshuisstijl', $emitted);
		$this->assertNotContains('token-overrides/rijkshuisstijl', $emitted);
	}//end testASetWithoutElementOverridesLoadsNothingExtra()
	/**
	 * The stylesheet manifest is the SAME list `inject()` emits for the
	 * set-dependent layers — one owner for the cascade. Every file the page
	 * render adds for a set appears in the manifest, in the same order, as a
	 * URL under the app's `css/`; the set-independent layers (custom overrides,
	 * freeform CSS, the toggles) are not in it.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/apply-without-reload/specs/css-architecture/spec.md
	 */
	public function testStylesheetManifestMatchesInjectedSetLayers(): void {
		$this->configureAppValues(['token_set' => 'rijkshuisstijl', 'installed_version' => '9.9.9']);
		$this->designSystemService->method('getTokenSetMeta')->with('rijkshuisstijl')
			->willReturn(['design_system' => 'nldesign']);
		$this->designSystemService->method('getDesignSystem')->with('nldesign')->willReturn(
			[
				'id' => 'nldesign',
				'name' => 'NL Design System',
				'description' => '',
				'stylesheets' => [
					'systems/nldesign/fonts',
					'systems/nldesign/defaults',
					'systems/nldesign/utrecht-bridge',
					'systems/nldesign/theme',
					'systems/nldesign/overrides',
					'systems/nldesign/element-overrides',
				],
			]
		);
		$this->urlGenerator->method('linkTo')->willReturnCallback(
			fn (string $appName, string $file) => '/custom_apps/' . $appName . '/' . $file
		);

		$styleLog = [];
		$fontLog = [];
		$service = $this->buildService(styleLog: $styleLog, fontLog: $fontLog);
		$service->inject('user');
		$manifest = $service->getStylesheetManifest('rijkshuisstijl');

		$setIndependent = ['custom-overrides', 'custom-css', 'hide-slogan', 'show-menu-labels'];
		$injectedSetFiles = array_values(
			array_filter($styleLog, fn (string $file) => in_array($file, $setIndependent, true) === false)
		);

		$manifestFiles = [];
		foreach ($manifest['layers'] as $layer) {
			if ($layer['kind'] === 'file') {
				$this->assertStringEndsWith('?v=9.9.9', $layer['href'], 'a manifest href carries the installed version as cache-buster');
				$manifestFiles[] = preg_replace('#^/custom_apps/thematiq/css/(.*)\.css\?v=.*$#', '$1', $layer['href']);
			} else {
				$this->assertSame('inline', $layer['kind']);
				$this->assertSame(CssInjectionService::LOGO_STYLE_ID, $layer['id'], 'the inline logo layer carries the id the client replaces it by');
				$this->assertStringContainsString('--nldesign-logo-url', $layer['css']);
			}
		}

		$this->assertSame($injectedSetFiles, $manifestFiles, 'manifest files equal the injected set layers, in order');
		$this->assertSame('rijkshuisstijl', $manifest['tokenSet']);
		$this->assertSame('nldesign', $manifest['designSystem']);
		$this->assertNotContains('custom-overrides', $manifestFiles);
	}//end testStylesheetManifestMatchesInjectedSetLayers()

	/**
	 * Stock Nextcloud (design system `none`) whose instance theme could not be
	 * read carries exactly one set layer: the component scopes. The shipped
	 * snapshot is not a fallback for it (see the resolved case below).
	 *
	 * The manifest is what the client swaps when an admin changes set without a
	 * reload, so the layer has to be IN it — otherwise switching to stock would
	 * strip the component layer off the live page and the token editor would go
	 * inert until the next reload, which is the same trap as not emitting it at
	 * all. Everything else Thematiq adds is still removed.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/apply-without-reload/specs/css-architecture/spec.md
	 */
	public function testStylesheetManifestCarriesOnlyComponentScopesForStockNextcloud(): void {
		$this->configureAppValues();
		$this->designSystemService->method('getTokenSetMeta')->with('nextcloud')
			->willReturn(['design_system' => 'none']);
		$this->designSystemService->method('getDesignSystem')->with('none')->willReturn(
			[
				'id' => 'none',
				'name' => 'Nextcloud',
				'description' => '',
				'stylesheets' => [],
			]
		);

		$styleLog = [];
		$fontLog = [];
		$service = $this->buildService(styleLog: $styleLog, fontLog: $fontLog);
		$manifest = $service->getStylesheetManifest('nextcloud');

		$this->assertSame('none', $manifest['designSystem']);
		$this->assertSame(
			['theme-scopes', 'component-scopes'],
			array_column($manifest['layers'], 'layer')
		);
	}//end testStylesheetManifestCarriesOnlyComponentScopesForStockNextcloud()

	/**
	 * Point the stock set at the `none` design system and make the instance
	 * answer with a resolved block, the way a running Nextcloud does.
	 *
	 * The mock is rebuilt rather than re-stubbed: setUp() already stubs
	 * `getCss()` to null, and PHPUnit lets the first unconstrained stub win.
	 *
	 * @param string $css The block the instance resolves to.
	 *
	 * @return void
	 */
	private function configureResolvedStockSet(string $css): void {
		$this->configureAppValues(['token_set' => 'nextcloud']);
		$this->designSystemService->method('getTokenSetMeta')->with('nextcloud')
			->willReturn(['design_system' => 'none']);
		$this->designSystemService->method('getDesignSystem')->with('none')->willReturn(
			[
				'id' => 'none',
				'name' => 'Nextcloud',
				'description' => '',
				'stylesheets' => [],
			]
		);

		$this->stockTokens = $this->createMock(StockTokensService::class);
		$this->stockTokens->expects($this->atLeastOnce())->method('getCss')->willReturn($css);
	}//end configureResolvedStockSet()

	/**
	 * The stock set carries the tokens the instance itself resolves, as the
	 * inline layer the client replaces by id, and never the shipped snapshot.
	 *
	 * Regression for thematiq#620: the only call to StockTokensService::getCss()
	 * sat behind the `none` early return, and the stock set is the one set on
	 * `none`, so the resolver never ran.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/component-playground/specs/nextcloud-variable-mapping/spec.md
	 */
	public function testStylesheetManifestCarriesTheInstanceResolvedStockTokens(): void {
		$block = ':root{--nldesign-color-primary:#00679e;}';
		$this->configureResolvedStockSet(css: $block);

		$styleLog = [];
		$fontLog = [];
		$manifest = $this->buildService(styleLog: $styleLog, fontLog: $fontLog)->getStylesheetManifest('nextcloud');

		$this->assertSame(['tokens', 'theme-scopes', 'component-scopes'], array_column($manifest['layers'], 'layer'));
		$this->assertSame('inline', $manifest['layers'][0]['kind']);
		$this->assertSame(CssInjectionService::STOCK_TOKENS_STYLE_ID, $manifest['layers'][0]['id']);
		$this->assertSame($block, $manifest['layers'][0]['css']);
		foreach ($manifest['layers'] as $layer) {
			$this->assertStringNotContainsString('tokens/nextcloud', ($layer['href'] ?? ''), 'the shipped snapshot is not loaded');
		}
	}//end testStylesheetManifestCarriesTheInstanceResolvedStockTokens()

	/**
	 * A stock page render emits the resolved block inline, before the component
	 * layer, and loads no token file.
	 *
	 * Asserted through `inject()`, the caller the page actually goes through,
	 * so the resolver cannot be wired into the manifest alone.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/component-playground/specs/nextcloud-variable-mapping/spec.md
	 */
	public function testInjectEmitsTheInstanceResolvedStockTokensOnAStockPage(): void {
		$block = ':root{--nldesign-color-primary:#00679e;}';
		$this->configureResolvedStockSet(css: $block);

		$log = [];
		$service = $this->getMockBuilder(CssInjectionService::class)
			->setConstructorArgs(
				[
					$this->config,
					$this->designSystemService,
					$this->customCssService,
					$this->fontService,
					$this->urlGenerator,
					$this->groupThemingService,
					$this->previewBannerService,
					$this->logger,
					$this->stockTokens,
					$this->runtimeFiles,
					new LogoLayerService($this->config, $this->urlGenerator, $this->logger, $this->runtimeFiles),
				]
			)
			->onlyMethods(['emitStyle', 'emitStylesheetLink', 'emitInlineStyle'])
			->getMock();
		$service->method('emitStyle')->willReturnCallback(
			function (string $file) use (&$log) {
				$log[] = 'file:' . $file;
			}
		);
		$service->method('emitInlineStyle')->willReturnCallback(
			function (string $css, ?string $id = null) use (&$log) {
				$log[] = 'inline:' . (string)$id . ':' . $css;
			}
		);

		$service->inject('user');

		$this->assertSame(
			[
				'inline:' . CssInjectionService::STOCK_TOKENS_STYLE_ID . ':' . $block,
				'file:theme-scopes',
				'file:component-scopes',
			],
			$log
		);
	}//end testInjectEmitsTheInstanceResolvedStockTokensOnAStockPage()
}//end class
