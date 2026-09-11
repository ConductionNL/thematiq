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

use OCA\Thematiq\Service\CssInjectionService;
use OCA\Thematiq\Service\CustomCssService;
use OCA\Thematiq\Service\CustomOverridesService;
use OCA\Thematiq\Service\DesignSystemService;
use OCA\Thematiq\Service\FontService;
use OCA\Thematiq\Service\GroupThemingService;
use OCA\Thematiq\Service\ThemePreviewBannerService;
use OCP\IConfig;
use OCP\IURLGenerator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Unit tests for CssInjectionService.
 *
 * `emitStyle()`/`emitFontLink()` are the only two side-effecting calls in the
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
	 * The custom overrides service mock.
	 *
	 * @var CustomOverridesService&MockObject
	 */
	private $customOverridesService;

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
	 * Set up mocks before each test.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->config = $this->createMock(IConfig::class);
		$this->designSystemService = $this->createMock(DesignSystemService::class);
		$this->customOverridesService = $this->createMock(CustomOverridesService::class);
		$this->customCssService = $this->createMock(CustomCssService::class);
		$this->fontService = $this->createMock(FontService::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->groupThemingService = $this->createMock(GroupThemingService::class);
		$this->previewBannerService = $this->createMock(ThemePreviewBannerService::class);
		$this->logger = $this->createMock(LoggerInterface::class);

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
	 * Build the service under test as a partial mock that captures every
	 * `emitStyle()`/`emitFontLink()` call (in order) instead of calling the
	 * real static Nextcloud API.
	 *
	 * @param array<int, string> $styleLog Populated with each `emitStyle()` file, in call order.
	 * @param array<int, string> $fontLog Populated with each `emitFontLink()` url, in call order.
	 *
	 * @return CssInjectionService&MockObject The service under test.
	 */
	private function buildService(array &$styleLog, array &$fontLog): CssInjectionService {
		$service = $this->getMockBuilder(CssInjectionService::class)
			->setConstructorArgs(
				[
					$this->config,
					$this->designSystemService,
					$this->customOverridesService,
					$this->customCssService,
					$this->fontService,
					$this->urlGenerator,
					$this->groupThemingService,
					$this->previewBannerService,
					$this->logger,
				]
			)
			->onlyMethods(['emitStyle', 'emitFontLink'])
			->getMock();

		$service->method('emitStyle')->willReturnCallback(
			function (string $file) use (&$styleLog) {
				$styleLog[] = $file;
			}
		);
		$service->method('emitFontLink')->willReturnCallback(
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

		// REGISTERED BEFORE THE CALL, DELIBERATELY. This line used to sit
		// AFTER `inject()` and read `expects($this->never())` — and a mock
		// expectation only counts invocations made AFTER it is registered, so
		// it observed 0 calls and could never fail, while the production code
		// was in fact calling `ensureExists()` on every render. Proven by
		// A/B: with the line in its old position, both `never()` and
		// `exactly(2)` pass. In this position `exactly(2)` fails, as it should.
		$this->customOverridesService->expects($this->once())->method('ensureExists');

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
				'custom-overrides',
			],
			$styleLog
		);
		$this->assertSame([], $fontLog);
	}//end testStandardNldesignOrder()

	/**
	 * The "none" design system (stock Nextcloud) loads no layer 1-7
	 * stylesheet and no token/contrast CSS, but custom-overrides still loads.
	 */
	public function testNoneDesignSystemLoadsNoStylesheets(): void {
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

		$this->assertSame(['custom-overrides'], $styleLog);
		$this->assertSame([], $fontLog);
	}//end testNoneDesignSystemLoadsNoStylesheets()

	/**
	 * Custom overrides load after all design-system and token layers, and
	 * `ensureExists()` runs before the stylesheet is emitted.
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

		$ensureExistsCalledBeforeStyle = false;
		$this->customOverridesService->expects($this->once())->method('ensureExists')
			->willReturnCallback(
				function () use (&$ensureExistsCalledBeforeStyle) {
					$ensureExistsCalledBeforeStyle = true;
				}
			);

		$styleLog = [];
		$fontLog = [];
		$service = $this->buildService(styleLog: $styleLog, fontLog: $fontLog);
		$service->inject('user');

		$this->assertTrue($ensureExistsCalledBeforeStyle);
		$this->assertSame(end($styleLog), 'custom-overrides');
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
			['tokens/nextcloud', 'icon-contrast', 'error-contrast', 'custom-overrides', 'hide-slogan', 'show-menu-labels'],
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
	}//end testConditionalStylesheetsAbsentWhenDisabled()

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
				'custom-overrides',
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

			$this->assertContains('custom-overrides', $styleLog, 'context ' . $context . ' must be themed');
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
		$this->assertContains('custom-overrides', $userStyleLog);
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

		$this->assertContains('custom-overrides', $styleLog);
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

		$this->assertContains('custom-overrides', $styleLog);
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

		$this->assertContains('custom-overrides', $styleLog);
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

		$styleLog = [];
		$fontLog = [];
		$service = $this->buildService(styleLog: $styleLog, fontLog: $fontLog);
		$service->inject('user');

		$this->assertContains('custom-css', $styleLog);
		$this->assertGreaterThan(
			array_search('custom-overrides', $styleLog, true),
			array_search('custom-css', $styleLog, true),
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
	 * nldesign#264 — a failing `css/` write must not cancel the rest of the
	 * cascade.
	 *
	 * `CustomOverridesService::ensureExists()` WRITES into the app directory,
	 * which throws on a read-only or non-www-data-owned install. Before the
	 * fix that exception escaped `inject()` into the listener's catch-all, so
	 * custom fonts, the conditional stylesheets and the preview banner all
	 * silently vanished while the page still looked correctly themed.
	 *
	 * @spec openspec/specs/css-architecture/spec.md#custom-overrides-always-loaded-last
	 */
	public function testAFailingOverridesWriteDoesNotCancelTheLaterLayers(): void {
		$this->configureAllLaterLayers();
		$this->customOverridesService->method('ensureExists')->willThrowException(
			new RuntimeException('Could not write custom-overrides.css.tmp')
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

		// The layer that failed is the ONLY one missing: there is no file to
		// link, so emitting the tag would be a guaranteed 404.
		$this->assertNotContains('custom-overrides', $styleLog);

		// Layer 2 ran (it precedes the failure) ...
		$this->assertContains('systems/nldesign/fonts', $styleLog);
		// ... and so did every layer AFTER it. This is the regression.
		$this->assertCount(1, $fontLog, 'layer 4.5 was cancelled');
		$this->assertStringContainsString('/index.php/apps/thematiq/fonts.css', $fontLog[0]);
		$this->assertContains('hide-slogan', $styleLog, 'layer 5 was cancelled');
		$this->assertContains('show-menu-labels', $styleLog, 'layer 5 was cancelled');
		$this->assertTrue($bannerInjected, 'layer 6 (preview banner) was cancelled');
	}//end testAFailingOverridesWriteDoesNotCancelTheLaterLayers()

	/**
	 * A skipped layer must reach the log. The pre-fix code swallowed the
	 * throw in an EMPTY catch block, so the failure was invisible at every
	 * log level and presented as "one theming feature is broken".
	 *
	 * @spec openspec/specs/css-architecture/spec.md#custom-overrides-always-loaded-last
	 */
	public function testASkippedOverridesLayerIsLogged(): void {
		$this->configureAllLaterLayers();
		$this->customOverridesService->method('ensureExists')->willThrowException(
			new RuntimeException('Could not write custom-overrides.css.tmp')
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

		$this->assertCount(1, $warnings);
		$this->assertStringContainsString('custom-overrides.css', $warnings[0]);
	}//end testASkippedOverridesLayerIsLogged()

	/**
	 * The isolation is general, not a special case for the overrides layer:
	 * a failure in an EARLY layer must not cancel the later ones either.
	 *
	 * @spec openspec/specs/css-architecture/spec.md#standard-css-load-order-for-nldesign-design-system
	 */
	public function testAFailingDesignSystemLayerDoesNotCancelTheLaterLayers(): void {
		$this->configureAppValues(['hide_slogan' => '1']);
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

		$this->assertContains('custom-overrides', $styleLog, 'layer 4 was cancelled');
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

		$this->assertContains('custom-overrides', $styleLog);
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
	 * Stock Nextcloud (design system `none`) has no set layers, so its manifest
	 * is empty — which is what lets the client remove every Thematiq layer when
	 * an admin switches back to stock without a reload.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/apply-without-reload/specs/css-architecture/spec.md
	 */
	public function testStylesheetManifestIsEmptyForStockNextcloud(): void {
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
		$this->assertSame([], $manifest['layers']);
	}//end testStylesheetManifestIsEmptyForStockNextcloud()
}//end class
