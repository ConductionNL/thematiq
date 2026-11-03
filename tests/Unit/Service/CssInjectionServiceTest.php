<?php

/**
 * Unit tests for CssInjectionService.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/archive/2026-07-23-render-event-injection/tasks.md#task-4.2
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\AppBrandService;
use OCA\Thematiq\Service\AppMenuService;
use OCA\Thematiq\Service\CssInjectionService;
use OCA\Thematiq\Service\CustomCssService;
use OCA\Thematiq\Service\DesignSystemService;
use OCA\Thematiq\Service\FontService;
use OCA\Thematiq\Service\GroupThemingService;
use OCA\Thematiq\Service\HeaderUserService;
use OCA\Thematiq\Service\LayoutOptionsService;
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
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->config = $this->createMock(originalClassName: IConfig::class);
		$this->designSystemService = $this->createMock(originalClassName: DesignSystemService::class);
		$this->customCssService = $this->createMock(originalClassName: CustomCssService::class);
		$this->fontService = $this->createMock(originalClassName: FontService::class);
		$this->urlGenerator = $this->createMock(originalClassName: IURLGenerator::class);
		$this->groupThemingService = $this->createMock(originalClassName: GroupThemingService::class);
		$this->previewBannerService = $this->createMock(originalClassName: ThemePreviewBannerService::class);
		$this->logger = $this->createMock(originalClassName: LoggerInterface::class);
		$this->stockTokens = $this->createMock(originalClassName: StockTokensService::class);
		$this->stockTokens->method('getCss')->willReturn(null);
		$this->appBrands = $this->createMock(originalClassName: AppBrandService::class);

		$this->runtimeDir = sys_get_temp_dir() . '/thematiq-injection-' . bin2hex(random_bytes(4));
		// The repository is the app directory, read-only, so shipped files
		// such as img/logos/rijkshuisstijl.svg resolve as they do in a release.
		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('getAppPath')->willReturn(\dirname(__DIR__, 3));
		$this->urlGenerator->method('linkTo')->willReturnCallback(
			fn (string $appName, string $file) => '/custom_apps/' . $appName . '/' . $file
		);
		$routes = $this->createMock(originalClassName: IURLGenerator::class);
		$routes->method('linkToRoute')->willReturnCallback(static fn (string $route, array $args): string => 'runtime:' . $args['name']);
		$this->runtimeFiles = new RuntimeFileLocator(
			appManager: $appManager,
			store: new DirectoryRuntimeFileStore(root: $this->runtimeDir . '/store'),
			urlGenerator: $routes,
			tempManager: $this->createMock(originalClassName: ITempManager::class)
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
	 * @param array<int, string> $log The emitted entries, in order.
	 * @param string $prefix The start to look for.
	 *
	 * @return int The index.
	 */
	private function indexStartingWith(array $log, string $prefix): int {
		foreach ($log as $index => $entry) {
			if (str_starts_with($entry, $prefix) === true) {
				return $index;
			}
		}

		$this->fail(message: 'no entry starts with ' . $prefix . ' in ' . json_encode($log));
	}//end indexStartingWith()

	/**
	 * Whether a static app stylesheet was linked as a header.
	 *
	 * @param array<int, string> $linkLog The emitted link URLs.
	 * @param string $file The stylesheet path relative to `css/`, without extension.
	 *
	 * @return bool True when one link points at it.
	 */
	private function linksStylesheet(array $linkLog, string $file): bool {
		foreach ($linkLog as $url) {
			if (str_starts_with($url, '/custom_apps/thematiq/css/' . $file . '.css?v=') === true) {
				return true;
			}
		}

		return false;
	}//end linksStylesheet()

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
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
		$service = $this->getMockBuilder(className: CssInjectionService::class)
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
					new LogoLayerService(config: $this->config, urlGenerator: $this->urlGenerator, logger: $this->logger, files: $this->runtimeFiles),
					$this->appBrands,
				]
			)
			->onlyMethods(['emitStyle', 'emitStylesheetLink', 'emitInlineStyle'])
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
	 *
	 * @return void
	 */
	public function testStandardNldesignOrder(): void {
		$this->configureAppValues(overrides: ['token_set' => 'rijkshuisstijl']);
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
			expected: [
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
			actual: $styleLog
		);
		$this->assertCount(expectedCount: 1, haystack: $fontLog);
		$this->assertStringStartsWith(prefix: 'runtime:css/custom-overrides.css?v=', string: $fontLog[0]);
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
	 *
	 * @return void
	 */
	public function testNoneDesignSystemLoadsOnlyTheComponentLayer(): void {
		$this->configureAppValues(overrides: ['token_set' => 'nextcloud']);
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

		$this->assertSame(expected: ['theme-scopes', 'component-scopes'], actual: $styleLog);
		$this->assertSame(expected: [], actual: $fontLog);
	}//end testNoneDesignSystemLoadsOnlyTheComponentLayer()

	/**
	 * A custom set saved off stock Nextcloud is on `none` too, but unlike the
	 * stock set it HAS a file, and that file is where its values live. It is
	 * loaded before the component layer that reads it, and the set gets an
	 * overrides file of its own — neither the shared one nor the stock set's.
	 *
	 * @return void
	 */
	public function testACustomSetOnNoneLoadsItsOwnTokenFile(): void {
		$this->configureAppValues(overrides: ['token_set' => 'custom-openwoo']);
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

		$this->assertSame(expected: ['tokens/custom-openwoo', 'theme-scopes', 'component-scopes'], actual: $styleLog);
	}//end testACustomSetOnNoneLoadsItsOwnTokenFile()

	/**
	 * A set that gives an internal token a value carries the internal scopes,
	 * inline and directly after the component scopes.
	 *
	 * @spec openspec/specs/css-architecture/spec.md
	 *
	 * @return void
	 */
	public function testASetWithAnInternalTokenCarriesTheInternalScopesAfterTheComponentScopes(): void {
		$this->configureAppValues(overrides: ['token_set' => 'custom-openwoo']);
		$this->designSystemService->method('getTokenSetMeta')->willReturn(['design_system' => 'none']);
		$this->designSystemService->method('getDesignSystem')->with('none')->willReturn(
			['id' => 'none', 'name' => 'No design system', 'description' => '', 'stylesheets' => []]
		);
		$this->runtimeFiles->store()->write('css/tokens/custom-openwoo.css', ":root {\n\t--nldesign-nc-dp-hover-color: #e8eef5;\n}\n");

		$styleLog = [];
		$fontLog = [];
		$manifest = $this->buildService(styleLog: $styleLog, fontLog: $fontLog)->getStylesheetManifest('custom-openwoo');

		$this->assertSame(
			expected: ['tokens', 'theme-scopes', 'component-scopes', 'internal-scopes'],
			actual: array_column($manifest['layers'], 'layer')
		);
		$last = end($manifest['layers']);
		$this->assertSame(expected: 'inline', actual: $last['kind']);
		$this->assertSame(expected: CssInjectionService::INTERNAL_SCOPES_STYLE_ID, actual: $last['id']);
		$this->assertStringContainsString(needle: '--dp-hover-color: var(--nldesign-nc-dp-hover-color);', haystack: (string)$last['css']);
	}//end testASetWithAnInternalTokenCarriesTheInternalScopesAfterTheComponentScopes()

	/**
	 * Saved overrides are the last stylesheet link the page gets, after every
	 * design-system and token layer.
	 *
	 * @spec openspec/specs/runtime-file-storage/spec.md
	 *
	 * @return void
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

		$this->assertNotContains(
			needle: 'custom-overrides-nextcloud',
			haystack: $styleLog,
			message: 'overrides are not a static app stylesheet any more'
		);
		$this->assertStringStartsWith(prefix: 'runtime:css/custom-overrides-nextcloud.css?v=', string: (string)end($fontLog));
	}//end testCustomOverridesAlwaysLoadedLast()

	/**
	 * hide-slogan and show-menu-labels load last, after custom-overrides,
	 * only when their respective appconfig flags are enabled.
	 *
	 * Nextcloud prints every addStyle() stylesheet before every header, and
	 * the saved overrides are a header, so the two toggles must be headers
	 * too: as addStyle() entries they landed BEFORE custom-overrides (#944).
	 *
	 * @spec openspec/specs/css-architecture/spec.md#conditional-css-loading
	 * @spec openspec/specs/menu-labels/spec.md#css-loading-order-relative-to-other-conditionals
	 *
	 * @return void
	 */
	public function testConditionalStylesheetsLoadedWhenEnabled(): void {
		$this->configureAppValues(overrides: ['hide_slogan' => '1', 'show_menu_labels' => '1']);
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

		$this->assertNotContains(needle: 'hide-slogan', haystack: $styleLog, message: 'an addStyle() entry is printed before every header');
		$this->assertNotContains(needle: 'show-menu-labels', haystack: $styleLog, message: 'an addStyle() entry is printed before every header');
		$overrides = $this->indexStartingWith(log: $fontLog, prefix: 'runtime:css/custom-overrides-nextcloud.css');
		$slogan = $this->indexStartingWith(log: $fontLog, prefix: '/custom_apps/thematiq/css/hide-slogan.css?v=');
		$labels = $this->indexStartingWith(log: $fontLog, prefix: '/custom_apps/thematiq/css/show-menu-labels.css?v=');
		$this->assertGreaterThanOrEqual(expected: 0, actual: $overrides, message: 'the saved overrides are linked');
		$this->assertGreaterThan(expected: $overrides, actual: $slogan, message: 'hide-slogan loads after custom-overrides');
		$this->assertGreaterThan(expected: $slogan, actual: $labels, message: 'show-menu-labels loads after hide-slogan');
	}//end testConditionalStylesheetsLoadedWhenEnabled()

	/**
	 * The conditional stylesheets are absent when their flags are disabled.
	 *
	 * @return void
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

		$this->assertNotContains(needle: 'hide-slogan', haystack: $styleLog);
		$this->assertNotContains(needle: 'show-menu-labels', haystack: $styleLog);
		$this->assertFalse(condition: $this->linksStylesheet(linkLog: $fontLog, file: 'hide-slogan'));
		$this->assertFalse(condition: $this->linksStylesheet(linkLog: $fontLog, file: 'show-menu-labels'));
		$this->assertNotContains(needle: 'primary-lock', haystack: $styleLog);
	}//end testConditionalStylesheetsAbsentWhenDisabled()

	/**
	 * With hide_slogan and show_menu_labels never written to IConfig, both
	 * stylesheets stay off: the injector's own default is '0'.
	 *
	 * @spec openspec/specs/hide-slogan/spec.md#default-value-when-not-configured
	 * @spec openspec/specs/menu-labels/spec.md#default-value-when-not-configured
	 *
	 * @return void
	 */
	public function testTogglesAbsentFromConfigDefaultToOff(): void {
		// Only the keys a fresh instance has; every other read gets the caller's default.
		$this->config->method('getAppValue')->willReturnCallback(
			function (string $app, string $key, string $default) {
				return ['token_set' => 'nextcloud', 'themed_contexts' => '[]'][$key] ?? $default;
			}
		);
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

		$this->assertNotContains(needle: 'hide-slogan', haystack: $styleLog);
		$this->assertNotContains(needle: 'show-menu-labels', haystack: $styleLog);
		$this->assertFalse(condition: $this->linksStylesheet(linkLog: $fontLog, file: 'hide-slogan'));
		$this->assertFalse(condition: $this->linksStylesheet(linkLog: $fontLog, file: 'show-menu-labels'));
	}//end testTogglesAbsentFromConfigDefaultToOff()

	/**
	 * Only the exact string '1' turns a toggle on. Loose truthy values that a
	 * `== true` or `(bool)` reading would accept leave the stylesheet out.
	 *
	 * @param string $stored The raw IConfig value.
	 *
	 * @dataProvider looseTruthyProvider
	 *
	 * @spec openspec/specs/hide-slogan/spec.md#boot-phase-reads-and-compares-correctly
	 *
	 * @return void
	 */
	public function testConditionalStylesheetsIgnoreLooseTruthyValues(string $stored): void {
		$this->configureAppValues(overrides: ['hide_slogan' => $stored, 'show_menu_labels' => $stored]);
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

		$this->assertNotContains(needle: 'hide-slogan', haystack: $styleLog);
		$this->assertNotContains(needle: 'show-menu-labels', haystack: $styleLog);
		$this->assertFalse(condition: $this->linksStylesheet(linkLog: $fontLog, file: 'hide-slogan'));
		$this->assertFalse(condition: $this->linksStylesheet(linkLog: $fontLog, file: 'show-menu-labels'));
	}//end testConditionalStylesheetsIgnoreLooseTruthyValues()

	/**
	 * Values a loose comparison would read as "on".
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function looseTruthyProvider(): array {
		return [
			'yes' => ['yes'],
			'true' => ['true'],
			'on' => ['on'],
			'01' => ['01'],
			'1 with a space' => [' 1'],
		];
	}//end looseTruthyProvider()

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
	 *
	 * @return void
	 */
	public function testPrimaryLockEmittedLastWhenTheSettingIsOn(): void {
		$this->configureAppValues(overrides: ['primary_drives_components' => '1']);
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
		$this->assertNotContains(needle: 'primary-lock', haystack: $styleLog);
		$this->assertStringContainsString(
			needle: 'css/primary-lock.css',
			haystack: (string)end($fontLog),
			message: 'primary-lock is the last link emitted'
		);
		$this->assertGreaterThan(
			expected: $this->indexStartingWith(log: $fontLog, prefix: 'runtime:css/custom-overrides-nextcloud.css'),
			actual: $this->indexStartingWith(log: $fontLog, prefix: '/custom_apps/thematiq/css/primary-lock.css'),
			message: 'primary-lock must come after custom-overrides, or the stored value would win'
		);
	}//end testPrimaryLockEmittedLastWhenTheSettingIsOn()

	/**
	 * Custom fonts inject a `<link>` header (not a static stylesheet) after
	 * the token-set styles, only when at least one font is configured, and
	 * never for the "none" design system.
	 *
	 * @return void
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

		$this->assertSame(expected: ['https://example.test/apps/thematiq/fonts/css?v=3'], actual: $fontLog);
	}//end testCustomFontsInjectedWhenConfigured()

	/**
	 * No font link is emitted for the "none" design system, even with fonts configured.
	 *
	 * @return void
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

		$this->assertSame(expected: [], actual: $fontLog);
	}//end testCustomFontsNotInjectedForNoneDesignSystem()

	/**
	 * When the active design system is `lasuite` AND `marianne_enabled` is
	 * `'1'`, `systems/lasuite/marianne` is emitted immediately after the
	 * declared lasuite stylesheets (which already include the base
	 * `systems/lasuite/fonts` layer).
	 *
	 * @spec openspec/specs/marianne-font/spec.md
	 *
	 * @return void
	 */
	public function testMarianneEmittedWhenLasuiteAndGateEnabled(): void {
		$this->configureAppValues(overrides: ['token_set' => 'lasuite', 'marianne_enabled' => '1']);
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
			expected: [
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
			actual: $styleLog
		);

		$fontsIndex = array_search('systems/lasuite/fonts', $styleLog, true);
		$marianneIndex = array_search('systems/lasuite/marianne', $styleLog, true);
		$this->assertGreaterThan(expected: $fontsIndex, actual: $marianneIndex, message: 'marianne.css must load after the base fonts layer.');
	}//end testMarianneEmittedWhenLasuiteAndGateEnabled()

	/**
	 * `systems/lasuite/marianne` is NOT emitted when the gate is off (the
	 * default), even for the `lasuite` design system.
	 *
	 * @spec openspec/specs/marianne-font/spec.md
	 *
	 * @return void
	 */
	public function testMarianneNotEmittedWhenGateDisabled(): void {
		$this->configureAppValues(overrides: ['token_set' => 'lasuite', 'marianne_enabled' => '0']);
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

		$this->assertNotContains(needle: 'systems/lasuite/marianne', haystack: $styleLog);
	}//end testMarianneNotEmittedWhenGateDisabled()

	/**
	 * `systems/lasuite/marianne` is NOT emitted for a non-lasuite design
	 * system, even when `marianne_enabled` is `'1'` (a stray flag from a
	 * previous lasuite session must not leak into another design system).
	 *
	 * @spec openspec/specs/marianne-font/spec.md
	 *
	 * @return void
	 */
	public function testMarianneNotEmittedForNonLasuiteDesignSystem(): void {
		$this->configureAppValues(overrides: ['token_set' => 'rijkshuisstijl', 'marianne_enabled' => '1']);
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

		$this->assertNotContains(needle: 'systems/lasuite/marianne', haystack: $styleLog);
	}//end testMarianneNotEmittedForNonLasuiteDesignSystem()

	/**
	 * Absent `themed_contexts` themes every context (byte-identical default).
	 *
	 * @return void
	 */
	public function testAbsentThemedContextsThemesEveryContext(): void {
		$this->configureAppValues(overrides: ['themed_contexts' => '[]']);
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

			$this->assertContains(needle: 'component-scopes', haystack: $styleLog, message: 'context ' . $context . ' must be themed');
		}
	}//end testAbsentThemedContextsThemesEveryContext()

	/**
	 * `["user"]` excludes every other configured context — `login` injects
	 * nothing, `user` remains fully themed.
	 *
	 * @return void
	 */
	public function testConfiguredListExcludesUnlistedContexts(): void {
		$this->configureAppValues(overrides: ['themed_contexts' => '["user"]']);
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
		$this->assertSame(expected: [], actual: $loginStyleLog);

		$userStyleLog = [];
		$userFontLog = [];
		$userService = $this->buildService(styleLog: $userStyleLog, fontLog: $userFontLog);
		$userService->inject('user');
		$this->assertContains(needle: 'component-scopes', haystack: $userStyleLog);
	}//end testConfiguredListExcludesUnlistedContexts()

	/**
	 * Unparseable JSON in `themed_contexts` fails open to themed, without raising.
	 *
	 * @return void
	 */
	public function testInvalidJsonThemedContextsFailsOpen(): void {
		$this->configureAppValues(overrides: ['themed_contexts' => 'not-json{']);
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

		$this->assertContains(needle: 'component-scopes', haystack: $styleLog);
	}//end testInvalidJsonThemedContextsFailsOpen()

	/**
	 * A non-array JSON value in `themed_contexts` also fails open to themed.
	 *
	 * @return void
	 */
	public function testNonArrayJsonThemedContextsFailsOpen(): void {
		$this->configureAppValues(overrides: ['themed_contexts' => '"not-an-array"']);
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

		$this->assertContains(needle: 'component-scopes', haystack: $styleLog);
	}//end testNonArrayJsonThemedContextsFailsOpen()

	/**
	 * An unrecognized context value (a future/unmapped `renderAs`) is always
	 * themed, even when a configured list would otherwise exclude "user".
	 *
	 * @return void
	 */
	public function testUnknownContextAlwaysThemed(): void {
		$this->configureAppValues(overrides: ['themed_contexts' => '["login"]']);
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

		$this->assertContains(needle: 'component-scopes', haystack: $styleLog);
	}//end testUnknownContextAlwaysThemed()

	/**
	 * The freeform layer is emitted AFTER custom-overrides so administrator
	 * intent wins the cascade.
	 *
	 * @spec openspec/specs/custom-css-freeform/spec.md
	 *
	 * @return void
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
			expected: $this->indexStartingWith(log: $fontLog, prefix: 'runtime:css/custom-overrides-nextcloud.css'),
			actual: $this->indexStartingWith(log: $fontLog, prefix: 'runtime:css/custom-css.css'),
			message: 'custom-css must be emitted AFTER custom-overrides so it wins the cascade.'
		);
	}//end testCustomCssEmittedLastWhenEnabledAndPresent()

	/**
	 * An instance that never opted in loads nothing, even if a file exists.
	 *
	 * @spec openspec/specs/custom-css-freeform/spec.md
	 *
	 * @return void
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

		$this->assertNotContains(needle: 'custom-css', haystack: $styleLog);
	}//end testCustomCssNotEmittedWhenDisabled()

	/**
	 * Enabled but empty must not emit a pointless <link>.
	 *
	 * @spec openspec/specs/custom-css-freeform/spec.md
	 *
	 * @return void
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

		$this->assertNotContains(needle: 'custom-css', haystack: $styleLog);
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
			overrides: [
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
	 * @spec openspec/specs/runtime-file-storage/spec.md
	 *
	 * @return void
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

		$this->assertFalse(
			condition: $this->runtimeFiles->store()->exists('css/custom-overrides-nextcloud.css'),
			message: 'rendering created an overrides file'
		);
		$this->assertSame(expected: [], actual: $this->runtimeFiles->store()->listDirectory('css'), message: 'rendering stored a file');
		$this->assertContains(needle: 'systems/nldesign/fonts', haystack: $styleLog);
		$this->assertCount(
			expectedCount: 3,
			haystack: $fontLog,
			message: 'the fonts link and the two toggles: nothing was saved, so no overrides link'
		);
		$this->assertStringContainsString(needle: '/index.php/apps/thematiq/fonts.css', haystack: $fontLog[0]);
		$this->assertTrue(condition: $this->linksStylesheet(linkLog: $fontLog, file: 'hide-slogan'), message: 'layer 5 was cancelled');
		$this->assertTrue(condition: $this->linksStylesheet(linkLog: $fontLog, file: 'show-menu-labels'), message: 'layer 5 was cancelled');
		$this->assertTrue(condition: $bannerInjected, message: 'layer 6 (preview banner) was cancelled');
	}//end testRenderingWritesNothingAndLaterLayersStillRun()

	/**
	 * No saved overrides is the normal state of a fresh instance, not a
	 * failure: nothing is logged for it.
	 *
	 * @spec openspec/specs/runtime-file-storage/spec.md
	 *
	 * @return void
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

		$this->assertSame(expected: [], actual: $warnings);
	}//end testNoSavedOverridesIsNotAWarning()

	/**
	 * The isolation is general, not a special case for the overrides layer:
	 * a failure in an EARLY layer must not cancel the later ones either.
	 *
	 * @spec openspec/specs/css-architecture/spec.md#standard-css-load-order-for-nldesign-design-system
	 *
	 * @return void
	 */
	public function testAFailingDesignSystemLayerDoesNotCancelTheLaterLayers(): void {
		$this->configureAppValues(overrides: ['hide_slogan' => '1']);
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

		$this->assertStringStartsWith(
			prefix: 'runtime:css/custom-overrides-nextcloud.css',
			string: (string)($fontLog[0] ?? ''),
			message: 'layer 4 was cancelled'
		);
		$this->assertTrue(condition: $this->linksStylesheet(linkLog: $fontLog, file: 'hide-slogan'), message: 'layer 5 was cancelled');
		$this->assertTrue(condition: $bannerInjected, message: 'layer 6 (preview banner) was cancelled');
	}//end testAFailingDesignSystemLayerDoesNotCancelTheLaterLayers()

	/**
	 * A failure in the LAST layer must not escape `inject()` either — it used
	 * to reach the listener's catch-all, which is what made the whole class
	 * of failures invisible.
	 *
	 * @spec openspec/specs/theme-preview/spec.md
	 *
	 * @return void
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

		$this->assertContains(needle: 'component-scopes', haystack: $styleLog);
		$this->assertCount(expectedCount: 1, haystack: $warnings);
		$this->assertStringContainsString(needle: 'preview-banner', haystack: $warnings[0]);
	}//end testAFailingPreviewBannerIsContainedAndLogged()

	/**
	 * The prerequisite block is NOT a layer: if the active token set cannot be
	 * resolved there is nothing for any layer to be a function of, so the
	 * render is left unthemed — but it is logged, and it still does not throw.
	 *
	 * @spec openspec/specs/css-architecture/spec.md#standard-css-load-order-for-nldesign-design-system
	 *
	 * @return void
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

		$this->assertSame(expected: [], actual: $styleLog);
		$this->assertCount(expectedCount: 1, haystack: $warnings);
		$this->assertStringContainsString(needle: 'could not resolve the active token set', haystack: $warnings[0]);
	}//end testAnUnresolvableTokenSetLeavesThePageUnthemedAndLogged()

	/**
	 * Emit the design system + token set for one set, and return the log.
	 *
	 * @param string $tokenSet The selected set.
	 *
	 * @return array<int, string> The emitted stylesheets, in order.
	 */
	private function emitFor(string $tokenSet): array {
		$this->configureAppValues(overrides: ['token_set' => $tokenSet]);
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
		$emitted = $this->emitFor(tokenSet: 'frankendesk');

		$this->assertContains(needle: 'token-overrides/frankendesk', haystack: $emitted);
		$this->assertGreaterThan(
			expected: array_search('systems/nldesign/element-overrides', $emitted, true),
			actual: array_search('token-overrides/frankendesk', $emitted, true),
			message: 'the set override must come AFTER the shared element overrides, or it cannot win'
		);
		$this->assertGreaterThan(
			expected: array_search('tokens/frankendesk', $emitted, true),
			actual: array_search('token-overrides/frankendesk', $emitted, true),
			message: 'and after its own tokens, whose values it reads'
		);
	}//end testASetWithElementOverridesGetsThemAfterItsTokens()

	/**
	 * A set with a dark logo carries it AFTER its generated dark variant
	 * (thematiq#1021).
	 *
	 * The dark file declares the dark logo as a relative url, which resolves
	 * against the stylesheet that uses it and so breaks from
	 * css/token-overrides/. The inline dark layer restates it as an absolute
	 * url, and it can only win by coming later at the same specificity. It is
	 * part of the manifest, so applying the set without a reload carries it.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/frankendesk-token-set/spec.md
	 */
	public function testADarkLogoLayerFollowsTheDarkVariant(): void {
		$this->configureAppValues(overrides: ['token_set' => 'frankendesk']);
		$this->designSystemService->method('getTokenSetMeta')->with('frankendesk')
			->willReturn(['design_system' => 'lasuite']);
		$this->designSystemService->method('getDesignSystem')->with('lasuite')->willReturn(
			[
				'id' => 'lasuite',
				'name' => 'La Suite',
				'description' => '',
				'stylesheets' => ['systems/lasuite/element-overrides'],
			]
		);
		$this->designSystemService->method('hasGeneratedDarkVariant')->willReturn(true);
		// The shared locator's url generator answers routes only; this one also
		// links shipped files, so the layer has a url to declare.
		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('getAppPath')->willReturn(\dirname(__DIR__, 3));
		$links = $this->createMock(originalClassName: IURLGenerator::class);
		$links->method('linkTo')->willReturnCallback(static fn (string $appName, string $file): string => '/custom_apps/' . $appName . '/' . $file);
		$this->runtimeFiles = new RuntimeFileLocator(
			appManager: $appManager,
			store: new DirectoryRuntimeFileStore(root: $this->runtimeDir . '/store'),
			urlGenerator: $links,
			tempManager: $this->createMock(originalClassName: ITempManager::class)
		);

		$styleLog = [];
		$fontLog = [];
		$layers = $this->buildService(styleLog: $styleLog, fontLog: $fontLog)->getStylesheetManifest('frankendesk')['layers'];

		$darkFile = null;
		$darkLogo = null;
		foreach ($layers as $index => $layer) {
			if ($layer['kind'] !== 'inline' && str_contains((string)($layer['href'] ?? ''), '/css/tokens/dark/frankendesk.css') === true) {
				$darkFile = $index;
			}

			if ($layer['kind'] === 'inline' && $layer['id'] === CssInjectionService::DARK_LOGO_STYLE_ID) {
				$darkLogo = $index;
			}
		}

		$this->assertNotNull(actual: $darkFile, message: 'the dark variant is in the manifest');
		$this->assertNotNull(actual: $darkLogo, message: 'the dark logo layer is in the manifest');
		$this->assertGreaterThan(
			expected: $darkFile,
			actual: $darkLogo,
			message: 'the dark logo layer must come after the dark variant, or its relative url wins'
		);
		$this->assertStringContainsString(needle: 'url(/custom_apps/thematiq/img/logos/frankendesk-dark.svg)', haystack: $layers[$darkLogo]['css']);
	}//end testADarkLogoLayerFollowsTheDarkVariant()

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
		$emitted = $this->emitFor(tokenSet: 'rijkshuisstijl');

		$this->assertContains(needle: 'tokens/rijkshuisstijl', haystack: $emitted);
		$this->assertNotContains(needle: 'token-overrides/rijkshuisstijl', haystack: $emitted);
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
		$this->configureAppValues(overrides: ['token_set' => 'rijkshuisstijl', 'installed_version' => '9.9.9']);
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
				$this->assertStringEndsWith(
					suffix: '?v=9.9.9',
					string: $layer['href'],
					message: 'a manifest href carries the installed version as cache-buster'
				);
				$manifestFiles[] = preg_replace('#^/custom_apps/thematiq/css/(.*)\.css\?v=.*$#', '$1', $layer['href']);
			} else {
				$this->assertSame(expected: 'inline', actual: $layer['kind']);
				$this->assertSame(
					expected: CssInjectionService::LOGO_STYLE_ID,
					actual: $layer['id'],
					message: 'the inline logo layer carries the id the client replaces it by'
				);
				$this->assertStringContainsString(needle: '--nldesign-logo-url', haystack: $layer['css']);
			}
		}

		$this->assertSame(expected: $injectedSetFiles, actual: $manifestFiles, message: 'manifest files equal the injected set layers, in order');
		$this->assertSame(expected: 'rijkshuisstijl', actual: $manifest['tokenSet']);
		$this->assertSame(expected: 'nldesign', actual: $manifest['designSystem']);
		$this->assertNotContains(needle: 'custom-overrides', haystack: $manifestFiles);
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

		$this->assertSame(expected: 'none', actual: $manifest['designSystem']);
		$this->assertSame(
			expected: ['theme-scopes', 'component-scopes'],
			actual: array_column($manifest['layers'], 'layer')
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
		$this->configureAppValues(overrides: ['token_set' => 'nextcloud']);
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

		$this->stockTokens = $this->createMock(originalClassName: StockTokensService::class);
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

		$this->assertSame(expected: ['tokens', 'theme-scopes', 'component-scopes'], actual: array_column($manifest['layers'], 'layer'));
		$this->assertSame(expected: 'inline', actual: $manifest['layers'][0]['kind']);
		$this->assertSame(expected: CssInjectionService::STOCK_TOKENS_STYLE_ID, actual: $manifest['layers'][0]['id']);
		$this->assertSame(expected: $block, actual: $manifest['layers'][0]['css']);
		foreach ($manifest['layers'] as $layer) {
			$this->assertStringNotContainsString(
				needle: 'tokens/nextcloud',
				haystack: ($layer['href'] ?? ''),
				message: 'the shipped snapshot is not loaded'
			);
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
		$service = $this->getMockBuilder(className: CssInjectionService::class)
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
					new LogoLayerService(config: $this->config, urlGenerator: $this->urlGenerator, logger: $this->logger, files: $this->runtimeFiles),
					$this->appBrands,
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
			expected: [
				'inline:' . CssInjectionService::STOCK_TOKENS_STYLE_ID . ':' . $block,
				'file:theme-scopes',
				'file:component-scopes',
			],
			actual: $log
		);
	}//end testInjectEmitsTheInstanceResolvedStockTokensOnAStockPage()

	/**
	 * Point the design-system mock at the nldesign system with no stylesheets,
	 * and at a token-set manifest entry that may carry a `layout` block.
	 *
	 * @param array<string, mixed> $meta The manifest entry of the active set.
	 *
	 * @return void
	 */
	private function configureLayoutSet(array $meta): void {
		$this->designSystemService->method('getTokenSetMeta')->willReturn(array_merge(['design_system' => 'nldesign'], $meta));
		$this->designSystemService->method('getDesignSystem')->willReturn(
			[
				'id' => 'nldesign',
				'name' => 'NL Design System',
				'description' => '',
				'stylesheets' => [],
			]
		);
	}//end configureLayoutSet()

	/**
	 * With no stored choice and a set that names no layout, neither layout
	 * stylesheet is emitted: every set that existed before the options did
	 * renders exactly as it did.
	 *
	 * @spec openspec/changes/zuiddrecht-workplace-theme/specs/workplace-layout/spec.md#requirement-the-light-layout-is-one-conditional-stylesheet
	 * @spec openspec/changes/zuiddrecht-workplace-theme/specs/brand-stripe/spec.md#requirement-the-stripe-is-one-conditional-stylesheet
	 *
	 * @return void
	 */
	public function testLayoutStylesheetsAreAbsentByDefault(): void {
		$this->configureAppValues(overrides: ['token_set' => 'rijkshuisstijl']);
		$this->configureLayoutSet(meta: []);

		$styleLog = [];
		$linkLog = [];
		$this->buildService(styleLog: $styleLog, fontLog: $linkLog)->inject('user');

		$this->assertFalse(condition: $this->linksStylesheet(linkLog: $linkLog, file: 'workplace-layout'));
		$this->assertFalse(condition: $this->linksStylesheet(linkLog: $linkLog, file: 'brand-stripe'));
	}//end testLayoutStylesheetsAreAbsentByDefault()

	/**
	 * A set that carries layout defaults wears them while the administrator
	 * has stored no choice, and both stylesheets come after the other toggles.
	 *
	 * @spec openspec/changes/zuiddrecht-workplace-theme/specs/workplace-layout/spec.md#requirement-a-token-set-may-carry-layout-defaults
	 *
	 * @return void
	 */
	public function testASetsLayoutDefaultsEmitBothStylesheets(): void {
		$this->configureAppValues(overrides: ['token_set' => 'zuiddrecht', 'show_menu_labels' => '1']);
		$this->configureLayoutSet(meta: ['layout' => ['workplace_layout' => 'light', 'brand_stripe' => true]]);

		$styleLog = [];
		$linkLog = [];
		$this->buildService(styleLog: $styleLog, fontLog: $linkLog)->inject('user');

		$labels = $this->indexStartingWith(log: $linkLog, prefix: '/custom_apps/thematiq/css/show-menu-labels.css');
		$layout = $this->indexStartingWith(log: $linkLog, prefix: '/custom_apps/thematiq/css/workplace-layout.css');
		$stripe = $this->indexStartingWith(log: $linkLog, prefix: '/custom_apps/thematiq/css/brand-stripe.css');
		$this->assertGreaterThan(expected: $labels, actual: $layout);
		$this->assertGreaterThan(expected: $layout, actual: $stripe);
	}//end testASetsLayoutDefaultsEmitBothStylesheets()

	/**
	 * A stored choice wins over the set, in both directions: an administrator
	 * can switch a set's layout off, and can give a set without one the light
	 * layout and the stripe.
	 *
	 * @param array<string, string> $stored The stored app values.
	 * @param array<string, mixed> $meta The active set's manifest entry.
	 * @param bool $expectLayout Whether workplace-layout is emitted.
	 * @param bool $expectStripe Whether brand-stripe is emitted.
	 *
	 * @return void
	 *
	 * @dataProvider storedLayoutChoiceProvider
	 *
	 * @spec openspec/changes/zuiddrecht-workplace-theme/specs/workplace-layout/spec.md#requirement-a-token-set-may-carry-layout-defaults
	 */
	public function testAStoredLayoutChoiceWinsOverTheSet(array $stored, array $meta, bool $expectLayout, bool $expectStripe): void {
		$this->configureAppValues(overrides: array_merge(['token_set' => 'rijkshuisstijl'], $stored));
		$this->configureLayoutSet(meta: $meta);

		$styleLog = [];
		$linkLog = [];
		$this->buildService(styleLog: $styleLog, fontLog: $linkLog)->inject('user');

		$this->assertSame(expected: $expectLayout, actual: $this->linksStylesheet(linkLog: $linkLog, file: 'workplace-layout'));
		$this->assertSame(expected: $expectStripe, actual: $this->linksStylesheet(linkLog: $linkLog, file: 'brand-stripe'));
	}//end testAStoredLayoutChoiceWinsOverTheSet()

	/**
	 * Stored choices against set defaults.
	 *
	 * @return array<string, array{0: array<string, string>, 1: array<string, mixed>, 2: bool, 3: bool}>
	 */
	public static function storedLayoutChoiceProvider(): array {
		$carries = ['layout' => ['workplace_layout' => 'light', 'brand_stripe' => true]];

		return [
			'switched off on a set that carries both' => [['workplace_layout' => 'default', 'brand_stripe' => '0'], $carries, false, false],
			'switched on for a set that carries none' => [['workplace_layout' => 'light', 'brand_stripe' => '1'], [], true, true],
			'an unknown stored layout follows the set' => [['workplace_layout' => 'wide', 'brand_stripe' => 'yes'], [], false, false],
			'only the stripe switched off' => [['brand_stripe' => '0'], $carries, true, false],
		];
	}//end storedLayoutChoiceProvider()

	/**
	 * The stylesheet manifest carries what the set resolves to, so the admin
	 * panel can add or drop the two stylesheets when it swaps sets in place.
	 *
	 * @spec openspec/changes/zuiddrecht-workplace-theme/specs/workplace-layout/spec.md#requirement-a-token-set-may-carry-layout-defaults
	 *
	 * @return void
	 */
	public function testTheManifestCarriesTheResolvedLayout(): void {
		$this->configureAppValues(overrides: ['token_set' => 'nextcloud']);
		$this->configureLayoutSet(meta: ['layout' => ['workplace_layout' => 'light', 'brand_stripe' => true]]);

		$styleLog = [];
		$linkLog = [];
		$manifest = $this->buildService(styleLog: $styleLog, fontLog: $linkLog)->getStylesheetManifest('zuiddrecht');

		$this->assertSame(
			expected: [
				'workplaceLayout' => 'light',
				'brandStripe' => true,
				'navigationWidth' => null,
				'navigationActiveStyle' => 'default',
				'brandStripePlacement' => 'header-and-login',
				'loginWatermark' => true,
				'headerStyle' => 'default',
			],
			actual: $manifest['layout']
		);
	}//end testTheManifestCarriesTheResolvedLayout()

	/**
	 * A set that carries every newer layout default gets each conditional
	 * stylesheet, in cascade order after the two older ones, and the
	 * navigation width as one inline `:root` variable after the links. A set
	 * that carries none gets none of them (the control).
	 *
	 * @spec openspec/changes/layout-options-navigation-stripe-watermark/specs/workplace-layout/spec.md#requirement-each-newer-option-is-one-conditional-stylesheet
	 *
	 * @return void
	 */
	public function testTheNewerLayoutDefaultsEmitTheirStylesheetsAndTheInlineWidth(): void {
		$this->configureAppValues(overrides: ['token_set' => 'zuiddrecht']);
		$this->configureLayoutSet(
			meta: [
				'layout' => [
					'workplace_layout' => 'light',
					'brand_stripe' => true,
					'navigation_width' => 264,
					'navigation_active_style' => 'soft',
					'brand_stripe_placement' => 'login',
					'login_watermark' => false,
				],
			]
		);

		$log = [];
		$service = $this->getMockBuilder(className: CssInjectionService::class)
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
					new LogoLayerService(config: $this->config, urlGenerator: $this->urlGenerator, logger: $this->logger, files: $this->runtimeFiles),
					$this->appBrands,
				]
			)
			->onlyMethods(['emitStyle', 'emitStylesheetLink', 'emitInlineStyle'])
			->getMock();
		$service->method('emitStylesheetLink')->willReturnCallback(
			function (string $url) use (&$log) {
				$log[] = 'link:' . $url;
			}
		);
		$service->method('emitInlineStyle')->willReturnCallback(
			function (string $css, ?string $id = null) use (&$log) {
				$log[] = 'inline:' . (string)$id . ':' . $css;
			}
		);

		$service->inject('user');

		$conditional = array_values(
			array_filter(
				$log,
				static fn (string $entry): bool => str_starts_with($entry, 'inline:thematiq-navigation-width')
					|| preg_match(
						'#/css/(workplace-layout|login-watermark-off|brand-stripe|brand-stripe-login-only|brand-stripe-header-only|navigation-width|navigation-active-soft)\.css#',
						$entry
					) === 1
			)
		);
		$this->assertSame(
			expected: [
				'link:/custom_apps/thematiq/css/workplace-layout.css?v=0',
				'link:/custom_apps/thematiq/css/login-watermark-off.css?v=0',
				'link:/custom_apps/thematiq/css/brand-stripe.css?v=0',
				'link:/custom_apps/thematiq/css/brand-stripe-login-only.css?v=0',
				'link:/custom_apps/thematiq/css/navigation-width.css?v=0',
				'link:/custom_apps/thematiq/css/navigation-active-soft.css?v=0',
				'inline:thematiq-navigation-width::root { --thematiq-navigation-width: 264px; }',
			],
			actual: $conditional
		);
	}//end testTheNewerLayoutDefaultsEmitTheirStylesheetsAndTheInlineWidth()

	/**
	 * The control for the test above: a set without a layout block and no
	 * stored choice loads none of the newer stylesheets and no inline width.
	 *
	 * @spec openspec/changes/layout-options-navigation-stripe-watermark/specs/workplace-layout/spec.md#requirement-each-newer-option-is-one-conditional-stylesheet
	 *
	 * @return void
	 */
	public function testTheNewerLayoutStylesheetsAreAbsentByDefault(): void {
		$this->configureAppValues(overrides: ['token_set' => 'rijkshuisstijl']);
		$this->configureLayoutSet(meta: []);

		$styleLog = [];
		$linkLog = [];
		$this->buildService(styleLog: $styleLog, fontLog: $linkLog)->inject('user');

		foreach (['login-watermark-off', 'brand-stripe-login-only', 'brand-stripe-header-only', 'navigation-width', 'navigation-active-soft'] as $file) {
			$this->assertFalse(condition: $this->linksStylesheet(linkLog: $linkLog, file: $file), message: $file);
		}
	}//end testTheNewerLayoutStylesheetsAreAbsentByDefault()

	/**
	 * The workplace header style loads its stylesheet for a light set that
	 * names it; a set that names it on the default layout, and a set that
	 * names nothing, do not (the controls). The person's layer is asked every
	 * time, with the page's set, and decides from the same stylesheets.
	 *
	 * @param array<string, mixed> $layout The set's layout block.
	 * @param bool $expected Whether the header sheet and the person are emitted.
	 *
	 * @dataProvider headerStyleProvider
	 *
	 * @spec openspec/changes/header-style-workplace/specs/workplace-layout/spec.md#requirement-the-header-style-is-a-layout-option
	 * @spec openspec/changes/header-style-workplace/specs/workplace-layout/spec.md#requirement-the-workplace-header-shows-the-name-and-the-role
	 *
	 * @return void
	 */
	public function testTheWorkplaceHeaderStyleLoadsItsSheetAndItsPerson(array $layout, bool $expected): void {
		$this->configureAppValues(overrides: ['token_set' => 'zuiddrecht']);
		$this->configureLayoutSet(meta: ['layout' => $layout]);

		$headerUser = $this->createMock(originalClassName: HeaderUserService::class);
		$headerUser->expects($this->once())->method('inject')->with('zuiddrecht', $this->isInstanceOf(className: LayoutOptionsService::class));

		$links = [];
		$service = $this->getMockBuilder(className: CssInjectionService::class)
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
					new LogoLayerService(config: $this->config, urlGenerator: $this->urlGenerator, logger: $this->logger, files: $this->runtimeFiles),
					$this->appBrands,
					null,
					null,
					$headerUser,
				]
			)
			->onlyMethods(['emitStyle', 'emitStylesheetLink', 'emitInlineStyle'])
			->getMock();
		$service->method('emitStylesheetLink')->willReturnCallback(
			function (string $url) use (&$links) {
				$links[] = $url;
			}
		);

		$service->inject('user');

		$this->assertSame(expected: $expected, actual: $this->linksStylesheet(linkLog: $links, file: 'header-workplace'));
	}//end testTheWorkplaceHeaderStyleLoadsItsSheetAndItsPerson()

	/**
	 * The header style on and off the light layout.
	 *
	 * @return array<string, array{0: array<string, mixed>, 1: bool}>
	 */
	public static function headerStyleProvider(): array {
		return [
			'a light set that names the workplace bar' => [['workplace_layout' => 'light', 'header_style' => 'workplace'], true],
			'the workplace bar named on the default layout' => [['header_style' => 'workplace'], false],
			'a light set that names no header style' => [['workplace_layout' => 'light'], false],
		];
	}//end headerStyleProvider()

	/**
	 * The apps menu's layer is asked on every page with the page's set; it
	 * decides from the same stylesheets as the person's layer.
	 *
	 * @spec openspec/changes/apps-menu-board/specs/workplace-layout/spec.md#requirement-the-workplace-header-draws-the-apps-menu-of-the-board
	 *
	 * @return void
	 */
	public function testTheAppMenuLayerIsAskedWithThePagesSet(): void {
		$this->configureAppValues(overrides: ['token_set' => 'zuiddrecht']);
		$this->configureLayoutSet(meta: ['layout' => ['workplace_layout' => 'light', 'header_style' => 'workplace']]);

		$appMenu = $this->createMock(originalClassName: AppMenuService::class);
		$appMenu->expects($this->once())->method('inject')->with('zuiddrecht', $this->isInstanceOf(className: LayoutOptionsService::class));

		$service = $this->getMockBuilder(className: CssInjectionService::class)
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
					new LogoLayerService(config: $this->config, urlGenerator: $this->urlGenerator, logger: $this->logger, files: $this->runtimeFiles),
					$this->appBrands,
					null,
					null,
					null,
					$appMenu,
				]
			)
			->onlyMethods(['emitStyle', 'emitStylesheetLink', 'emitInlineStyle'])
			->getMock();

		$service->inject('user');
	}//end testTheAppMenuLayerIsAskedWithThePagesSet()
}//end class
