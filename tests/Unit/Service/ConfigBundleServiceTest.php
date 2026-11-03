<?php

/**
 * Unit tests for ConfigBundleService.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/config-portability/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Capabilities;
use OCA\Thematiq\Service\AppBrandLogoStore;
use OCA\Thematiq\Service\AppBrandService;
use OCA\Thematiq\Service\AppThemingService;
use OCA\Thematiq\Service\AssistantMarkService;
use OCA\Thematiq\Service\BundleExtraSections;
use OCA\Thematiq\Service\ConfigBundleService;
use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\CustomOverridesService;
use OCA\Thematiq\Service\CustomTokenSetsBundleSection;
use OCA\Thematiq\Service\CustomTokenSetService;
use OCA\Thematiq\Service\CustomTokenSetValidator;
use OCA\Thematiq\Service\DarkPaletteService;
use OCA\Thematiq\Service\DeprecationRecords;
use OCA\Thematiq\Service\DesignSystemService;
use OCA\Thematiq\Service\DocumentAssetService;
use OCA\Thematiq\Service\EmailThemingService;
use OCA\Thematiq\Service\FontService;
use OCA\Thematiq\Service\ImageSniffer;
use OCA\Thematiq\Service\OverridesSetContext;
use OCA\Thematiq\Service\OverridesStorage;
use OCA\Thematiq\Service\OwnTokenService;
use OCA\Thematiq\Service\RuntimeFile\DirectoryRuntimeFileStore;
use OCA\Thematiq\Service\ScheduledSwitchStore;
use OCA\Thematiq\Service\ShippedTokenSetAuditService;
use OCA\Thematiq\Service\TokenDeprecationService;
use OCA\Thematiq\Service\TokenLifecycleBundleSection;
use OCA\Thematiq\Service\TokenSetPreviewService;
use OCA\Thematiq\Service\TokenSetService;
use OCA\Thematiq\Service\TokenSetVocabularyAuditService;
use OCA\Thematiq\Service\TokenValueValidator;
use OCA\Thematiq\Service\UpstreamFreshnessService;
use OCP\App\IAppManager;
use OCP\Files\IAppData;
use OCP\Http\Client\IClientService;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IConfig;
use OCP\IURLGenerator;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Covers tasks.md#task-4.1/#task-4.2: export completeness, all-or-nothing
 * import, unknown-token skip semantics, in-bundle token-set resolution,
 * dry-run, idempotence, and the full round-trip.
 *
 * Real service instances back a temp app directory + in-memory appconfig —
 * the same "real filesystem, fake config" pattern CustomTokenSetServiceTest
 * uses — so the round-trip assertions exercise genuine file/manifest writes,
 * not mocked stand-ins. FontService is the one collaborator mocked outright:
 * its manifest is exported for information only and never applied (see
 * ConfigBundleService's class docblock), so there is nothing to round-trip.
 */
class ConfigBundleServiceTest extends TestCase {

	/**
	 * The temp app directory standing in for the nldesign app path.
	 *
	 * @var string
	 */
	private string $appDir;

	/**
	 * In-memory appconfig store: key => value.
	 *
	 * @var array<string, string>
	 */
	private array $appConfig = [];

	/**
	 * The service under test.
	 *
	 * @var ConfigBundleService
	 */
	private ConfigBundleService $service;

	/**
	 * The (real) custom token set service, exposed for direct assertions.
	 *
	 * @var CustomTokenSetService
	 */
	private CustomTokenSetService $customTokenSetService;

	/**
	 * The (real) custom overrides service, exposed for direct assertions.
	 *
	 * @var CustomOverridesService
	 */
	private CustomOverridesService $overridesService;

	/**
	 * The (real) token set service, exposed for direct assertions.
	 *
	 * @var TokenSetService
	 */
	private TokenSetService $tokenSetService;

	/**
	 * The (real) own token store.
	 *
	 * @var OwnTokenService
	 */
	private OwnTokenService $ownTokens;

	/**
	 * The (real) token deprecations.
	 *
	 * @var TokenDeprecationService
	 */
	private TokenDeprecationService $deprecations;

	/**
	 * The mocked font service (manifest passthrough only, see class docblock).
	 *
	 * @var FontService&\PHPUnit\Framework\MockObject\MockObject
	 */
	private $fontService;

	/**
	 * Set up a temp app dir + real collaborators + mocked config before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->appDir = sys_get_temp_dir() . '/nldesign-bundle-test-' . uniqid();
		mkdir($this->appDir . '/css/tokens', 0777, true);

		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('getAppPath')->willReturn($this->appDir);
		$appManager->method('getAppVersion')->willReturn('0.1.3-test');
		$appManager->method('isInstalled')->willReturn(true);
		// NOTE: no `getEnabledApps` stub. It is not on OCP\App\IAppManager in the
		// supported Nextcloud versions, so createMock() refuses to configure it
		// ("Trying to configure method ... which cannot be configured because it
		// does not exist") and every test in this class errors in setUp(). The
		// service under test never calls it either — ConfigBundleService uses
		// exactly one IAppManager method, getAppVersion(). A double must mirror
		// the real signature, never invent one.

		$config = $this->createMock(originalClassName: IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			fn (string $app, string $key, $default = '') => ($this->appConfig[$key] ?? $default)
		);
		$config->method('setAppValue')->willReturnCallback(
			function (string $app, string $key, $value): void {
				$this->appConfig[$key] = $value;
			}
		);

		$cssParser = new CssParserService();
		$contrast = new ContrastService();
		$customTokenSetValidator = new CustomTokenSetValidator();
		$logger = $this->createMock(originalClassName: LoggerInterface::class);

		$records = new DeprecationRecords(config: $config);
		$this->ownTokens = new OwnTokenService(config: $config, values: new TokenValueValidator(), records: $records);
		$this->deprecations = new TokenDeprecationService(records: $records, ownTokens: $this->ownTokens, appManager: $appManager, cssParser: $cssParser);
		$this->overridesService = new CustomOverridesService(
			storage: new OverridesStorage(
				store: new DirectoryRuntimeFileStore(root: $appManager->getAppPath('thematiq')),
				cssParser: $cssParser,
				darkPalette: new DarkPaletteService(contrast: $contrast, parser: $cssParser, appManager: $appManager, logger: $logger)
			),
			setContext: new OverridesSetContext(
				config: $config,
				designSystems: new DesignSystemService(appManager: $appManager, config: $config),
				values: null,
				ownTokens: $this->ownTokens
			)
		);
		$this->customTokenSetService = new CustomTokenSetService(
			store: new DirectoryRuntimeFileStore(root: $appManager->getAppPath('thematiq')),
			config: $config,
			validator: $customTokenSetValidator,
			contrast: $contrast,
			darkPalette: new DarkPaletteService(contrast: $contrast, parser: $cssParser, appManager: $appManager, logger: $logger)
		);
		$cacheFactory = $this->createMock(originalClassName: ICacheFactory::class);
		$cacheFactory->method('createDistributed')->willReturn($this->createMock(originalClassName: ICache::class));

		$this->tokenSetService = new TokenSetService(
			appManager: $appManager,
			config: $config,
			logger: $logger,
			audit: new ShippedTokenSetAuditService(contrast: $contrast, parser: $cssParser),
			cacheFactory: $cacheFactory,
			vocabularyAudit: new TokenSetVocabularyAuditService(parser: $cssParser)
		);

		$appThemingService = new AppThemingService(config: $config, appManager: $appManager);

		$urlGenerator = $this->createMock(originalClassName: IURLGenerator::class);
		$previewService = new TokenSetPreviewService(appManager: $appManager);
		$emailThemingService = new EmailThemingService(config: $config, tokenSetService: $this->tokenSetService, previewService: $previewService, urlGenerator: $urlGenerator);

		$clientService = $this->createMock(originalClassName: IClientService::class);
		$freshnessService = new UpstreamFreshnessService(config: $config, clientService: $clientService, tokenSetService: $this->tokenSetService, logger: $logger);

		$this->fontService = $this->createMock(originalClassName: FontService::class);
		$this->fontService->method('getManifest')->willReturn([]);

		$this->service = new ConfigBundleService(
			config: $config,
			appManager: $appManager,
			tokenSetService: $this->tokenSetService,
			overridesService: $this->overridesService,
			customTokenSets: new CustomTokenSetsBundleSection(
				customSets: $this->customTokenSetService,
				validator: $customTokenSetValidator,
				cssParser: $cssParser,
				appManager: $appManager
			),
			appThemingService: $appThemingService,
			cssParser: $cssParser,
			emailThemingService: $emailThemingService,
			fontService: $this->fontService,
			freshnessService: $freshnessService,
			scheduledSwitches: new ScheduledSwitchStore(config: $config),
			logger: $logger,
			extraSections: new BundleExtraSections(
				assistantMark: new AssistantMarkService(config: $config, emailTheming: $emailThemingService, capabilities: $this->createMock(originalClassName: Capabilities::class)),
				documentAssets: new DocumentAssetService(appData: $this->createMock(originalClassName: IAppData::class), config: $config),
				appBrands: new AppBrandService(
					config: $config,
					appManager: $appManager,
					appTheming: $appThemingService,
					tokenSets: $this->tokenSetService,
					logos: new AppBrandLogoStore(appData: $this->createMock(originalClassName: IAppData::class), sniffer: new ImageSniffer()),
					urls: $this->createMock(originalClassName: IURLGenerator::class)
				)
			),
			lifecycle: new TokenLifecycleBundleSection(ownTokens: $this->ownTokens, deprecations: $this->deprecations, overrides: $this->overridesService)
		);
	}//end setUp()

	/**
	 * Remove the temp app dir after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$this->rrmdir(dir: $this->appDir);
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
		if (is_dir($dir) === false) {
			return;
		}

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
	 * Seed a representative configuration: a shipped token set active, both
	 * toggles on, two disabled apps, one custom token set, and two overrides.
	 *
	 * @return void
	 */
	private function seedConfig(): void {
		// A shipped set — utrecht.css must exist on the filesystem for
		// isValidTokenSet() to accept it.
		file_put_contents($this->appDir . '/css/tokens/utrecht.css', ":root {\n  --nldesign-color-primary: #000000;\n}\n");

		$this->appConfig['token_set'] = 'utrecht';
		$this->appConfig['hide_slogan'] = '1';
		$this->appConfig['show_menu_labels'] = '1';
		$this->appConfig['disabled_apps'] = json_encode(['mail', 'files']);
		$this->appConfig['upstream_freshness_enabled'] = 'yes';
		$this->appConfig['email_footer_org_name'] = 'Gemeente Voorbeeld';
		$this->appConfig['email_footer_accessibility_url'] = 'https://example.org/toegankelijkheid';
		$this->appConfig['email_footer_privacy_url'] = 'https://example.org/privacy';

		$this->overridesService->write(
			tokens: [
				'--color-primary' => '#123456',
				'--color-error' => '#990000',
			]
		);

		$this->customTokenSetService->store(
			displayName: 'Gemeente X',
			description: 'A custom house style',
			declarations: ['--nldesign-color-primary' => '#007bc7']
		);
	}//end seedConfig()

	/**
	 * export() carries all sections: config toggles/exclusions, overrides
	 * CSS, and custom token sets (metadata + CSS).
	 *
	 * @return void
	 */
	public function testExportContainsAllSections(): void {
		$this->seedConfig();

		$bundle = $this->service->export();

		$this->assertSame(expected: 'nldesign-config-bundle', actual: $bundle['format']);
		$this->assertSame(expected: 4, actual: $bundle['bundleVersion']);
		$this->assertSame(expected: 'utrecht', actual: $bundle['config']['tokenSet']);
		$this->assertTrue(condition: $bundle['config']['hideSlogan']);
		$this->assertTrue(condition: $bundle['config']['showMenuLabels']);
		$this->assertSame(expected: ['mail', 'files'], actual: $bundle['config']['disabledApps']);
		$this->assertTrue(condition: $bundle['config']['upstreamFreshnessEnabled']);
		$this->assertSame(expected: 'Gemeente Voorbeeld', actual: $bundle['emailFooter']['orgName']);
		$this->assertStringContainsString(needle: '--color-primary: #123456', haystack: $bundle['customOverridesCss']);
		$this->assertCount(expectedCount: 1, haystack: $bundle['customTokenSets']);
		$this->assertSame(expected: 'custom-gemeente-x', actual: $bundle['customTokenSets'][0]['id']);
		$this->assertStringContainsString(needle: '--nldesign-color-primary: #007bc7', haystack: $bundle['customTokenSets'][0]['css']);
		$this->assertFalse(condition: $bundle['customFonts']['binariesIncluded']);
	}//end testExportContainsAllSections()

	/**
	 * Build a minimally valid bundle envelope + config referencing an
	 * already-shipped token set, for tests that only care about one section.
	 *
	 * @param array<string, mixed> $overrides Keys to merge over the baseline bundle.
	 *
	 * @return array<string, mixed> The bundle.
	 */
	private function baseBundle(array $overrides = []): array {
		file_put_contents($this->appDir . '/css/tokens/utrecht.css', ":root {\n  --nldesign-color-primary: #000000;\n}\n");

		$bundle = [
			'format' => 'nldesign-config-bundle',
			'bundleVersion' => 1,
			'exportedAt' => gmdate('c'),
			'app' => ['id' => 'nldesign', 'version' => '0.1.3-test'],
			'config' => [
				'tokenSet' => 'utrecht',
				'hideSlogan' => false,
				'showMenuLabels' => false,
				'disabledApps' => [],
				'upstreamFreshnessEnabled' => false,
			],
			'emailFooter' => [
				'orgName' => '',
				'accessibilityUrl' => '',
				'privacyUrl' => '',
			],
			'customOverridesCss' => '',
			'customTokenSets' => [],
			'customFonts' => ['manifest' => []],
		];

		return array_replace_recursive($bundle, $overrides);
	}//end baseBundle()

	/**
	 * A hard validation error in one custom token set writes NOTHING —
	 * config, overrides file, and custom sets are all untouched.
	 *
	 * @return void
	 */
	public function testImportInvalidCustomSetWritesNothingAndListsError(): void {
		$bundle = $this->baseBundle(
			overrides: [
				'customTokenSets' => [
					[
						'id' => 'custom-bad',
						'name' => 'Bad Set',
						'css' => ":root {\n  --nldesign-color-primary: javascript:alert(1);\n}\n",
					],
				],
			]
		);

		$before = $this->appConfig;

		$result = $this->service->import(bundle: $bundle, dryRun: false);

		$this->assertFalse(condition: $result['valid']);
		$this->assertFalse(condition: $result['applied']);
		$this->assertNotEmpty(actual: $result['errors']);
		$this->assertSame(expected: 'customTokenSets', actual: $result['errors'][0]['section']);

		// Nothing was written: config untouched, no custom set file created.
		$this->assertSame(expected: $before, actual: $this->appConfig);
		$this->assertFileDoesNotExist(filename: $this->appDir . '/css/tokens/custom-bad.css');
	}//end testImportInvalidCustomSetWritesNothingAndListsError()

	/**
	 * Unknown override variables are skipped and counted, not fatal — the
	 * import proceeds and writes only the recognised editable token.
	 *
	 * @return void
	 */
	public function testUnknownOverrideTokensSkippedNotFatal(): void {
		$bundle = $this->baseBundle(
			overrides: [
				'customOverridesCss' => ":root {\n  --color-primary: #223344 !important;\n  --unknown-var: #ffffff !important;\n}\n",
			]
		);

		$result = $this->service->import(bundle: $bundle, dryRun: false);

		$this->assertTrue(condition: $result['valid']);
		$this->assertTrue(condition: $result['applied']);
		$this->assertSame(expected: 1, actual: $result['sections']['customOverridesCss']['written']);
		$this->assertSame(expected: 1, actual: $result['sections']['customOverridesCss']['skipped']);

		$written = $this->overridesService->read();
		$this->assertSame(expected: ['--color-primary' => '#223344'], actual: $written);
	}//end testUnknownOverrideTokensSkippedNotFatal()

	/**
	 * A token set referencing a custom set contained WITHIN the same bundle
	 * resolves successfully, even though it has never existed on this
	 * instance before, and becomes the active set after apply.
	 *
	 * @return void
	 */
	public function testTokenSetResolvableFromWithinBundle(): void {
		$bundle = $this->baseBundle(
			overrides: [
				'config' => ['tokenSet' => 'custom-gemeente-x'],
				'customTokenSets' => [
					[
						'id' => 'custom-gemeente-x',
						'name' => 'Gemeente X',
						'css' => ":root {\n  --nldesign-color-primary: #007bc7;\n}\n",
					],
				],
			]
		);

		$result = $this->service->import(bundle: $bundle, dryRun: false);

		$this->assertTrue(condition: $result['valid']);
		$this->assertTrue(condition: $result['applied']);
		$this->assertSame(expected: 'custom-gemeente-x', actual: $this->appConfig['token_set']);
		$this->assertTrue(condition: $this->tokenSetService->isValidTokenSet(tokenSetId: 'custom-gemeente-x'));
	}//end testTokenSetResolvableFromWithinBundle()

	/**
	 * A token set id that is neither shipped/installed nor present in the
	 * bundle's own custom sets is a hard error naming the unresolvable id.
	 *
	 * @return void
	 */
	public function testNonexistentTokenSetIsHardError(): void {
		$bundle = $this->baseBundle(overrides: ['config' => ['tokenSet' => 'atlantis']]);

		$result = $this->service->import(bundle: $bundle, dryRun: false);

		$this->assertFalse(condition: $result['valid']);
		$this->assertStringContainsString(needle: 'atlantis', haystack: $result['errors'][0]['message']);
	}//end testNonexistentTokenSetIsHardError()

	/**
	 * The approved mark's three values travel in the bundle; an unsafe logo is refused, and an
	 * older bundle without the section leaves the mark alone.
	 *
	 * @spec openspec/specs/assistant-approved-mark/spec.md
	 *
	 * @return void
	 */
	public function testAssistantMarkSurvivesExportAndImport(): void {
		$this->seedConfig();
		$this->appConfig['assistant_mark_enabled'] = '1';
		$this->appConfig['assistant_mark_organisation'] = 'Gemeente Voorbeeld';
		$this->appConfig['assistant_mark_logo'] = 'https://example.org/logo.svg';

		$bundle = $this->service->export();
		$this->assertSame(expected: ['enabled' => true, 'organisation' => 'Gemeente Voorbeeld', 'logo' => 'https://example.org/logo.svg'], actual: $bundle['assistantMark']);

		$this->appConfig['assistant_mark_enabled'] = '0';
		$this->appConfig['assistant_mark_organisation'] = '';
		$this->appConfig['assistant_mark_logo'] = '';
		$result = $this->service->import(bundle: $bundle, dryRun: false);

		$this->assertTrue(condition: $result['valid']);
		$this->assertSame(expected: '1', actual: $this->appConfig['assistant_mark_enabled']);
		$this->assertSame(expected: 'Gemeente Voorbeeld', actual: $this->appConfig['assistant_mark_organisation']);
		$this->assertSame(expected: 'https://example.org/logo.svg', actual: $this->appConfig['assistant_mark_logo']);

		$bundle['assistantMark']['logo'] = 'javascript:alert(1)';
		$this->assertFalse(condition: $this->service->import(bundle: $bundle, dryRun: true)['valid']);

		unset($bundle['assistantMark']);
		$this->appConfig['assistant_mark_enabled'] = '0';
		$this->assertTrue(condition: $this->service->import(bundle: $bundle, dryRun: false)['valid']);
		$this->assertSame(expected: '0', actual: $this->appConfig['assistant_mark_enabled'], message: 'An older bundle leaves the mark alone.');
	}//end testAssistantMarkSurvivesExportAndImport()

	/**
	 * `primaryDrivesComponents` travels in the bundle, so an OTAP promotion
	 * carries the toggle instead of silently resetting it to off.
	 *
	 * Off is the default, and the difference between the two is visible: with
	 * it on, css/primary-lock.css is emitted and the per-component colour
	 * controls lock. A promotion that dropped the key would hand production a
	 * theme configured differently from the acceptance environment it was
	 * signed off on.
	 *
	 * @spec openspec/specs/component-tokens/spec.md
	 *
	 * @return void
	 */
	public function testPrimaryDrivesComponentsSurvivesExportAndImport(): void {
		$this->seedConfig();
		$this->appConfig['primary_drives_components'] = '1';

		$bundle = $this->service->export();
		$this->assertTrue(condition: $bundle['config']['primaryDrivesComponents']);

		$this->appConfig['primary_drives_components'] = '0';
		$result = $this->service->import(bundle: $bundle, dryRun: false);

		$this->assertTrue(condition: $result['valid']);
		$this->assertSame(expected: '1', actual: $this->appConfig['primary_drives_components']);
	}//end testPrimaryDrivesComponentsSurvivesExportAndImport()

	/**
	 * A bundle that omits the key imports as off rather than as an error.
	 *
	 * Bundles exported before the toggle existed have no such key, and the
	 * setting's default is off, so the absent case is a valid bundle — not
	 * something an admin has to hand-edit before a promotion will run.
	 *
	 * @spec openspec/specs/component-tokens/spec.md
	 *
	 * @return void
	 */
	public function testAnAbsentPrimaryDrivesComponentsImportsAsOff(): void {
		$this->appConfig['primary_drives_components'] = '1';

		$result = $this->service->import(bundle: $this->baseBundle(), dryRun: false);

		$this->assertTrue(condition: $result['valid']);
		$this->assertSame(expected: '0', actual: $this->appConfig['primary_drives_components']);
	}//end testAnAbsentPrimaryDrivesComponentsImportsAsOff()

	/**
	 * A non-boolean `primaryDrivesComponents` is rejected, not coerced.
	 *
	 * The string "false" is truthy in PHP, so coercion would turn an explicit
	 * off into an on and lock every component colour on the target
	 * environment.
	 *
	 * @spec openspec/specs/component-tokens/spec.md
	 *
	 * @return void
	 */
	public function testANonBooleanPrimaryDrivesComponentsIsRejected(): void {
		$bundle = $this->baseBundle(overrides: ['config' => ['primaryDrivesComponents' => 'false']]);

		$result = $this->service->import(bundle: $bundle, dryRun: false);

		$this->assertFalse(condition: $result['valid']);
		$this->assertNotEmpty(
			actual: array_filter(
				$result['errors'],
				static fn (array $error): bool => str_contains(
					$error['message'],
					'config.primaryDrivesComponents'
				)
			),
			message: 'the error names the offending key'
		);
	}//end testANonBooleanPrimaryDrivesComponentsIsRejected()

	/**
	 * The six layout choices travel as the administrator stored them, the
	 * empty string ("follow the theme") included, and come back the same.
	 *
	 * @spec openspec/changes/layout-options-navigation-stripe-watermark/specs/config-portability/spec.md#requirement-the-layout-options-travel-in-the-bundle
	 *
	 * @return void
	 */
	public function testTheLayoutOptionsSurviveExportAndImport(): void {
		$this->seedConfig();
		$this->appConfig['workplace_layout'] = 'light';
		$this->appConfig['brand_stripe'] = '0';
		$this->appConfig['navigation_width'] = '264';
		$this->appConfig['navigation_active_style'] = 'soft';
		$this->appConfig['brand_stripe_placement'] = 'login';
		$this->appConfig['login_watermark'] = '';
		$this->appConfig['header_style'] = 'workplace';

		$bundle = $this->service->export();
		$this->assertSame(
			expected: [
				'workplaceLayout' => 'light',
				'brandStripe' => '0',
				'navigationWidth' => '264',
				'navigationActiveStyle' => 'soft',
				'brandStripePlacement' => 'login',
				'loginWatermark' => '',
				'headerStyle' => 'workplace',
			],
			actual: $bundle['config']['layoutOptions']
		);

		foreach (['workplace_layout', 'brand_stripe', 'navigation_width', 'navigation_active_style', 'brand_stripe_placement', 'header_style'] as $key) {
			$this->appConfig[$key] = '';
		}

		$this->appConfig['login_watermark'] = '0';
		$result = $this->service->import(bundle: $bundle, dryRun: false);

		$this->assertTrue(condition: $result['valid'], message: json_encode($result['errors'] ?? []));
		$this->assertSame(expected: 'light', actual: $this->appConfig['workplace_layout']);
		$this->assertSame(expected: '0', actual: $this->appConfig['brand_stripe']);
		$this->assertSame(expected: '264', actual: $this->appConfig['navigation_width']);
		$this->assertSame(expected: 'soft', actual: $this->appConfig['navigation_active_style']);
		$this->assertSame(expected: 'login', actual: $this->appConfig['brand_stripe_placement']);
		$this->assertSame(expected: 'workplace', actual: $this->appConfig['header_style']);
		$this->assertSame(expected: '', actual: $this->appConfig['login_watermark'], message: 'The empty choice travels too: the target follows its theme again.');
	}//end testTheLayoutOptionsSurviveExportAndImport()

	/**
	 * A bundle from before the options travelled has no `layoutOptions`: each
	 * imports as "follow the theme", which is what such an instance stored.
	 *
	 * @spec openspec/changes/layout-options-navigation-stripe-watermark/specs/config-portability/spec.md#requirement-the-layout-options-travel-in-the-bundle
	 *
	 * @return void
	 */
	public function testAnAbsentLayoutOptionsSectionImportsAsFollowTheTheme(): void {
		$this->appConfig['workplace_layout'] = 'light';
		$this->appConfig['navigation_width'] = '264';

		$result = $this->service->import(bundle: $this->baseBundle(overrides: ['bundleVersion' => 3]), dryRun: false);

		$this->assertTrue(condition: $result['valid']);
		$this->assertSame(expected: '', actual: $this->appConfig['workplace_layout']);
		$this->assertSame(expected: '', actual: $this->appConfig['navigation_width']);
	}//end testAnAbsentLayoutOptionsSectionImportsAsFollowTheTheme()

	/**
	 * A layout value the option refuses is a hard error naming the key, and
	 * nothing is written.
	 *
	 * @spec openspec/changes/layout-options-navigation-stripe-watermark/specs/config-portability/spec.md#requirement-the-layout-options-travel-in-the-bundle
	 *
	 * @return void
	 */
	public function testALayoutValueTheOptionRefusesIsRejected(): void {
		$this->appConfig['navigation_width'] = '264';
		$bundle = $this->baseBundle(overrides: ['config' => ['layoutOptions' => ['navigationWidth' => '9000', 'workplaceLayout' => 'light']]]);

		$result = $this->service->import(bundle: $bundle, dryRun: false);

		$this->assertFalse(condition: $result['valid']);
		$this->assertNotEmpty(
			actual: array_filter(
				$result['errors'],
				static fn (array $error): bool => str_contains($error['message'], 'config.layoutOptions.navigationWidth')
			),
			message: 'the error names the offending key'
		);
		$this->assertSame(expected: '264', actual: $this->appConfig['navigation_width']);
		$this->assertArrayNotHasKey(key: 'workplace_layout', array: $this->appConfig);
	}//end testALayoutValueTheOptionRefusesIsRejected()

	/**
	 * A dry-run of a valid bundle reports the would-be sections and writes
	 * nothing at all.
	 *
	 * @return void
	 */
	public function testDryRunWritesNothing(): void {
		$this->seedConfig();
		$before = $this->appConfig;
		$beforeOverrides = $this->overridesService->getRawContent();

		$bundle = $this->baseBundle(
			overrides: [
				'config' => ['tokenSet' => 'utrecht', 'hideSlogan' => false, 'showMenuLabels' => false],
			]
		);

		$result = $this->service->import(bundle: $bundle, dryRun: true);

		$this->assertTrue(condition: $result['valid']);
		$this->assertTrue(condition: $result['dryRun']);
		$this->assertFalse(condition: $result['applied']);
		$this->assertArrayHasKey(key: 'sections', array: $result);

		$this->assertSame(expected: $before, actual: $this->appConfig);
		$this->assertSame(expected: $beforeOverrides, actual: $this->overridesService->getRawContent());
	}//end testDryRunWritesNothing()

	/**
	 * Importing the same valid bundle twice yields byte-identical state —
	 * app values, custom-overrides.css bytes, and custom-set files/manifest.
	 *
	 * @return void
	 */
	public function testImportIsIdempotent(): void {
		$this->seedConfig();
		$bundle = $this->service->export();

		$this->service->import(bundle: $bundle, dryRun: false);
		$firstOverrides = $this->overridesService->getRawContent();
		$firstManifest = $this->customTokenSetService->getManifest();
		$firstConfig = $this->appConfig;

		$this->service->import(bundle: $bundle, dryRun: false);
		$secondOverrides = $this->overridesService->getRawContent();
		$secondManifest = $this->customTokenSetService->getManifest();
		$secondConfig = $this->appConfig;

		$this->assertSame(expected: $firstOverrides, actual: $secondOverrides);
		$this->assertSame(expected: $firstManifest, actual: $secondManifest);
		$this->assertSame(expected: $firstConfig, actual: $secondConfig);
	}//end testImportIsIdempotent()

	/**
	 * Round-trip: seed a representative configuration, export it, wipe every
	 * part, import the exported bundle, and assert the resulting state is
	 * identical to the seeded state (app values, custom-overrides.css bytes,
	 * custom-set file bytes + manifest).
	 *
	 * @return void
	 */
	public function testRoundTripExportWipeImportRestoresIdenticalState(): void {
		$this->seedConfig();

		$bundle = $this->service->export();

		$seededOverrides = $this->overridesService->getRawContent();
		$seededManifest = $this->customTokenSetService->getManifest();
		$seededCustomCss = $this->customTokenSetService->getRawContent(id: 'custom-gemeente-x');
		$seededConfig = $this->appConfig;

		// Wipe: reset every app-value key and remove the overrides/custom-set files.
		$this->appConfig = [];
		unlink($this->appDir . '/css/custom-overrides.css');
		unlink($this->appDir . '/css/tokens/custom-gemeente-x.css');

		$result = $this->service->import(bundle: $bundle, dryRun: false);

		$this->assertTrue(condition: $result['applied']);
		$this->assertSame(expected: $seededConfig['token_set'], actual: $this->appConfig['token_set']);
		$this->assertSame(expected: $seededConfig['hide_slogan'], actual: $this->appConfig['hide_slogan']);
		$this->assertSame(expected: $seededConfig['show_menu_labels'], actual: $this->appConfig['show_menu_labels']);
		$this->assertSame(expected: $seededConfig['disabled_apps'], actual: $this->appConfig['disabled_apps']);
		$this->assertSame(expected: $seededConfig['upstream_freshness_enabled'], actual: $this->appConfig['upstream_freshness_enabled']);
		$this->assertSame(expected: $seededConfig['email_footer_org_name'], actual: $this->appConfig['email_footer_org_name']);

		$this->assertSame(expected: $seededOverrides, actual: $this->overridesService->getRawContent());
		$this->assertSame(expected: $seededManifest, actual: $this->customTokenSetService->getManifest());
		$this->assertSame(expected: $seededCustomCss, actual: $this->customTokenSetService->getRawContent(id: 'custom-gemeente-x'));
	}//end testRoundTripExportWipeImportRestoresIdenticalState()

	/**
	 * An envelope with the wrong `format` is a hard error before any other
	 * section is even considered.
	 *
	 * @return void
	 */
	public function testWrongFormatIsHardError(): void {
		$bundle = $this->baseBundle(overrides: ['format' => 'something-else']);

		$result = $this->service->import(bundle: $bundle, dryRun: false);

		$this->assertFalse(condition: $result['valid']);
		$this->assertSame(expected: 'envelope', actual: $result['errors'][0]['section']);
	}//end testWrongFormatIsHardError()

	/**
	 * An invalid emailFooter URL (non-http(s) scheme) is a hard error and
	 * blocks the whole import — reusing EmailThemingService's own rule set.
	 *
	 * @return void
	 */
	public function testInvalidEmailFooterUrlIsHardError(): void {
		$bundle = $this->baseBundle(
			overrides: ['emailFooter' => ['orgName' => 'X', 'accessibilityUrl' => 'javascript:alert(1)', 'privacyUrl' => '']]
		);

		$result = $this->service->import(bundle: $bundle, dryRun: false);

		$this->assertFalse(condition: $result['valid']);
		$this->assertSame(expected: 'emailFooter', actual: $result['errors'][0]['section']);
		$this->assertArrayNotHasKey(key: 'email_footer_org_name', array: $this->appConfig);
	}//end testInvalidEmailFooterUrlIsHardError()

	/**
	 * The document footer line travels as a value, the document images as metadata only.
	 *
	 * @spec openspec/specs/document-house-style/spec.md
	 *
	 * @return void
	 */
	public function testDocumentStyleFooterLineSurvivesExportAndImport(): void {
		$this->seedConfig();
		$this->appConfig['document_style_footer_line'] = 'Postbus 1, 1234 AB Voorbeeld';
		$this->appConfig['document_style_assets'] = json_encode(['logo' => ['mime' => 'image/png', 'size' => 10, 'uploadedAt' => 1]]);

		$bundle = $this->service->export();
		$this->assertSame(expected: 'Postbus 1, 1234 AB Voorbeeld', actual: $bundle['documentStyle']['footerLine']);
		$this->assertFalse(condition: $bundle['documentStyle']['binariesIncluded']);
		$this->assertSame(expected: 'image/png', actual: $bundle['documentStyle']['assets']['logo']['mime']);

		$this->appConfig['document_style_footer_line'] = '';
		$this->appConfig['document_style_assets'] = '{}';
		$result = $this->service->import(bundle: $bundle, dryRun: false);

		$this->assertTrue(condition: $result['valid']);
		$this->assertSame(expected: 'Postbus 1, 1234 AB Voorbeeld', actual: $this->appConfig['document_style_footer_line']);
		$this->assertSame(expected: '{}', actual: $this->appConfig['document_style_assets'], message: 'Image metadata is never applied.');

		$bundle['documentStyle']['footerLine'] = str_repeat('x', 201);
		$this->assertFalse(condition: $this->service->import(bundle: $bundle, dryRun: true)['valid']);
	}//end testDocumentStyleFooterLineSurvivesExportAndImport()

	/**
	 * Brands per app travel as app => set, logos as metadata only; a version 2 bundle leaves them alone.
	 *
	 * @spec openspec/specs/per-app-theming/spec.md
	 *
	 * @return void
	 */
	public function testAppBrandsSurviveExportAndImport(): void {
		$this->seedConfig();
		$this->appConfig['app_brands'] = json_encode(['collectives' => ['tokenSet' => 'utrecht', 'logoLarge' => ['mime' => 'image/png', 'size' => 9, 'uploadedAt' => 1], 'logoSmall' => null]]);

		$bundle = json_decode((string)json_encode($this->service->export()), true);
		$this->assertSame(expected: 4, actual: $bundle['bundleVersion']);
		$this->assertSame(expected: 'utrecht', actual: $bundle['appBrands']['collectives']['tokenSet']);

		$this->appConfig['app_brands'] = '{}';
		$result = $this->service->import(bundle: $bundle, dryRun: false);
		$this->assertTrue(condition: $result['valid'], message: json_encode($result['errors'] ?? []));
		$stored = json_decode($this->appConfig['app_brands'], true);
		$this->assertSame(expected: 'utrecht', actual: $stored['collectives']['tokenSet']);
		$this->assertNull(actual: $stored['collectives']['logoLarge'], message: 'Logo metadata is never applied without its file.');

		$bundle['appBrands']['collectives']['tokenSet'] = 'no-such-set';
		$this->assertFalse(condition: $this->service->import(bundle: $bundle, dryRun: true)['valid']);

		$bundle['bundleVersion'] = 2;
		unset($bundle['appBrands']);
		$this->assertTrue(condition: $this->service->import(bundle: $bundle, dryRun: false)['valid']);
		$this->assertSame(expected: 'utrecht', actual: json_decode($this->appConfig['app_brands'], true)['collectives']['tokenSet']);
	}//end testAppBrandsSurviveExportAndImport()

	/**
	 * The customFonts section is exported/reported as metadata only and is
	 * never applied — no custom_fonts app value is ever written by import().
	 *
	 * @return void
	 */
	public function testCustomFontsSectionIsNeverApplied(): void {
		$bundle = $this->baseBundle(
			overrides: ['customFonts' => ['manifest' => ['custom-x' => ['name' => 'X', 'role' => 'body']]]]
		);

		$result = $this->service->import(bundle: $bundle, dryRun: false);

		$this->assertTrue(condition: $result['applied']);
		$this->assertFalse(condition: $result['sections']['customFonts']['applied']);
		$this->assertArrayNotHasKey(key: 'custom_fonts', array: $this->appConfig);
	}//end testCustomFontsSectionIsNeverApplied()

	/**
	 * The environment config.php declares never travels in a bundle: it is
	 * the one value that must differ between OTAP environments.
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 *
	 * @return void
	 */
	public function testExportNeverCarriesTheEnvironment(): void {
		$this->seedConfig();

		$bundle = $this->service->export();

		$keys = [];
		array_walk_recursive(
			$bundle,
			static function ($value, $key) use (&$keys): void {
				$keys[] = strtolower((string)$key);
			}
		);
		$this->assertNotContains(needle: 'environment', haystack: $keys);
		$this->assertArrayNotHasKey(key: 'environment', array: $bundle['config']);
		$this->assertStringNotContainsString(needle: 'thematiq.environment', haystack: (string)json_encode($bundle));
	}//end testExportNeverCarriesTheEnvironment()

	/**
	 * The planned switches travel in the bundle without their runtime state.
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 *
	 * @return void
	 */
	public function testExportCarriesPlannedSwitchesWithoutRuntimeState(): void {
		$this->seedConfig();
		$this->appConfig['scheduled_switches'] = json_encode([
			[
				'id' => 'a1',
				'tokenSet' => 'utrecht',
				'startAt' => '2027-04-26T16:00:00Z',
				'endAt' => '2027-04-28T06:00:00Z',
				'syncCoreTheming' => true,
				'createdBy' => 'admin',
				'createdAt' => '2027-04-01T10:00:00Z',
				'status' => 'running',
				'revertTo' => 'nextcloud',
			],
		]);

		$switches = $this->service->export()['config']['scheduledSwitches'];

		$this->assertSame(
			expected: [
				[
					'id' => 'a1',
					'tokenSet' => 'utrecht',
					'startAt' => '2027-04-26T16:00:00Z',
					'endAt' => '2027-04-28T06:00:00Z',
					'createdBy' => 'admin',
					'createdAt' => '2027-04-01T10:00:00Z',
				],
			],
			actual: $switches
		);
	}//end testExportCarriesPlannedSwitchesWithoutRuntimeState()

	/**
	 * Scenario "A campaign prepared on acceptance goes to production": the
	 * planned switch names a custom set that only the bundle carries.
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 *
	 * @return void
	 */
	public function testAPlannedSwitchToABundledCustomSetIsImported(): void {
		$this->seedConfig();
		$this->appConfig['scheduled_switches'] = json_encode([
			[
				'id' => 'a1',
				'tokenSet' => 'custom-gemeente-x',
				'startAt' => '2027-04-26T16:00:00Z',
				'endAt' => null,
				'syncCoreTheming' => false,
				'createdBy' => 'admin',
				'createdAt' => '2027-04-01T10:00:00Z',
				'status' => 'planned',
			],
		]);
		$bundle = $this->service->export();

		// Production: a fresh config and no custom sets.
		$this->appConfig = [];
		$this->rrmdir(dir: $this->appDir);
		mkdir($this->appDir . '/css/tokens', 0777, true);
		file_put_contents($this->appDir . '/css/tokens/utrecht.css', ":root {\n  --nldesign-color-primary: #000000;\n}\n");

		$result = $this->service->import(bundle: $bundle);

		$this->assertTrue(condition: $result['valid'], message: (string)json_encode($result['errors'] ?? []));
		$stored = json_decode($this->appConfig['scheduled_switches'], true);
		$this->assertSame(expected: 'custom-gemeente-x', actual: $stored[0]['tokenSet']);
		$this->assertSame(expected: 'planned', actual: $stored[0]['status']);
		$this->assertSame(expected: 1, actual: $result['sections']['scheduledSwitches']['count']);
	}//end testAPlannedSwitchToABundledCustomSetIsImported()

	/**
	 * Importing a bundle that holds a running switch, as a version restore
	 * during a campaign does, keeps the switch running and its way back:
	 * restarting it would take the campaign look as the one to return to.
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 *
	 * @return void
	 */
	public function testARunningSwitchKeepsItsStateThroughAnImport(): void {
		$this->seedConfig();
		$running = [
			'id' => 'a1',
			'tokenSet' => 'utrecht',
			'startAt' => '2026-01-01T00:00:00Z',
			'endAt' => '2099-01-01T00:00:00Z',
			'createdBy' => 'admin',
			'createdAt' => '2025-12-01T10:00:00Z',
			'status' => 'running',
			'revertTo' => 'nextcloud',
			'coreSnapshot' => true,
		];
		$this->appConfig['scheduled_switches'] = json_encode([$running]);
		$bundle = $this->service->export();

		$result = $this->service->import(bundle: $bundle);

		$this->assertTrue(condition: $result['applied'], message: (string)json_encode($result['errors'] ?? []));
		$stored = json_decode($this->appConfig['scheduled_switches'], true);
		$this->assertCount(expectedCount: 1, haystack: $stored);
		$this->assertSame(expected: 'running', actual: $stored[0]['status']);
		$this->assertSame(expected: 'nextcloud', actual: $stored[0]['revertTo']);
		$this->assertTrue(condition: $stored[0]['coreSnapshot']);
	}//end testARunningSwitchKeepsItsStateThroughAnImport()

	/**
	 * A stale bundle does not replay a switch whose window has already ended.
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 *
	 * @return void
	 */
	public function testAnEndedSwitchInABundleIsNotReplayed(): void {
		$this->seedConfig();
		$bundle = $this->service->export();
		$bundle['config']['scheduledSwitches'] = [
			['id' => 'old', 'tokenSet' => 'utrecht', 'startAt' => '2020-04-26T16:00:00Z', 'endAt' => '2020-04-28T06:00:00Z'],
			['id' => 'new', 'tokenSet' => 'utrecht', 'startAt' => '2099-04-26T16:00:00Z', 'endAt' => '2099-04-28T06:00:00Z'],
		];

		$result = $this->service->import(bundle: $bundle);

		$this->assertTrue(condition: $result['applied'], message: (string)json_encode($result['errors'] ?? []));
		$stored = json_decode($this->appConfig['scheduled_switches'], true);
		$this->assertSame(expected: ['new'], actual: array_column($stored, 'id'));
		$this->assertSame(expected: 'planned', actual: $stored[0]['status']);
	}//end testAnEndedSwitchInABundleIsNotReplayed()

	/**
	 * A custom set keeps the design system it was created on through an export
	 * and an import, which a version restore is: a theme saved off stock
	 * Nextcloud must not come back as an NL Design System one. A design system
	 * the app does not ship is dropped rather than trusted.
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 *
	 * @return void
	 */
	public function testACustomSetKeepsItsDesignSystemThroughARoundTrip(): void {
		$this->seedConfig();
		file_put_contents($this->appDir . '/design-systems.json', json_encode([['id' => 'none'], ['id' => 'nldesign']]));
		$manifest = json_decode($this->appConfig[CustomTokenSetService::MANIFEST_KEY], true);
		$manifest['custom-gemeente-x']['design_system'] = 'none';
		$this->appConfig[CustomTokenSetService::MANIFEST_KEY] = json_encode($manifest);
		$bundle = $this->service->export();

		$this->assertTrue(condition: $this->service->import(bundle: $bundle)['applied']);
		$stored = json_decode($this->appConfig[CustomTokenSetService::MANIFEST_KEY], true);
		$this->assertSame(expected: 'none', actual: $stored['custom-gemeente-x']['design_system']);

		$bundle['customTokenSets'][0]['design_system'] = 'evil';
		$this->assertTrue(condition: $this->service->import(bundle: $bundle)['applied']);
		$stored = json_decode($this->appConfig[CustomTokenSetService::MANIFEST_KEY], true);
		$this->assertArrayNotHasKey(key: 'design_system', array: $stored['custom-gemeente-x']);
	}//end testACustomSetKeepsItsDesignSystemThroughARoundTrip()

	/**
	 * A design-systems manifest that is not a list of design systems allows none.
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 *
	 * @return void
	 */
	public function testAnUnreadableDesignSystemManifestAllowsNone(): void {
		$this->seedConfig();
		file_put_contents($this->appDir . '/design-systems.json', 'not json');
		$bundle = $this->service->export();
		$bundle['customTokenSets'][0]['design_system'] = 'none';

		$this->assertTrue(condition: $this->service->import(bundle: $bundle)['applied']);
		$stored = json_decode($this->appConfig[CustomTokenSetService::MANIFEST_KEY], true);
		$this->assertArrayNotHasKey(key: 'design_system', array: $stored['custom-gemeente-x']);
	}//end testAnUnreadableDesignSystemManifestAllowsNone()

	/**
	 * Scenario "An overlapping plan blocks the whole import".
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 *
	 * @return void
	 */
	public function testOverlappingPlannedSwitchesBlockTheWholeImport(): void {
		$bundle = $this->baseBundle(overrides: ['bundleVersion' => 2]);
		$bundle['config']['hideSlogan'] = true;
		$bundle['config']['scheduledSwitches'] = [
			['id' => 'a1', 'tokenSet' => 'utrecht', 'startAt' => '2027-05-01T00:00:00Z', 'endAt' => '2027-05-07T00:00:00Z', 'syncCoreTheming' => false],
			['id' => 'a2', 'tokenSet' => 'utrecht', 'startAt' => '2027-05-05T00:00:00Z', 'endAt' => '2027-05-10T00:00:00Z', 'syncCoreTheming' => false],
		];

		$result = $this->service->import(bundle: $bundle);

		$this->assertFalse(condition: $result['valid']);
		$this->assertStringContainsString(needle: 'overlap', haystack: (string)json_encode($result['errors']));
		$this->assertArrayNotHasKey(key: 'hide_slogan', array: $this->appConfig);
		$this->assertArrayNotHasKey(key: 'scheduled_switches', array: $this->appConfig);
	}//end testOverlappingPlannedSwitchesBlockTheWholeImport()

	/**
	 * A planned switch to a set neither installed nor bundled is refused.
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 *
	 * @return void
	 */
	public function testAPlannedSwitchToAnUnknownSetIsRefused(): void {
		$bundle = $this->baseBundle(overrides: ['bundleVersion' => 2]);
		$bundle['config']['scheduledSwitches'] = [
			['id' => 'a1', 'tokenSet' => 'custom-nergens', 'startAt' => '2027-05-01T00:00:00Z', 'endAt' => null, 'syncCoreTheming' => false],
		];

		$result = $this->service->import(bundle: $bundle);

		$this->assertFalse(condition: $result['valid']);
		$this->assertStringContainsString(needle: 'custom-nergens', haystack: (string)json_encode($result['errors']));
	}//end testAPlannedSwitchToAnUnknownSetIsRefused()

	/**
	 * A version 1 bundle (kept versions from before planned switches) still
	 * imports and leaves the planned switches alone.
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 *
	 * @return void
	 */
	public function testAVersionOneBundleStillImportsAndKeepsPlannedSwitches(): void {
		$this->appConfig['scheduled_switches'] = '[{"id":"keep"}]';

		$result = $this->service->import(bundle: $this->baseBundle());

		$this->assertTrue(condition: $result['valid'], message: (string)json_encode($result['errors'] ?? []));
		$this->assertSame(expected: '[{"id":"keep"}]', actual: $this->appConfig['scheduled_switches']);
	}//end testAVersionOneBundleStillImportsAndKeepsPlannedSwitches()

	/**
	 * Scenario: own tokens and their deprecations move from test to production.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/own-tokens/spec.md#requirement-own-tokens-travel-with-the-configuration-bundle
	 */
	public function testOwnTokensRoundTrip(): void {
		$this->seedConfig();
		$this->ownTokens->create(input: ['slug' => 'brand-accent', 'label' => 'Brand accent', 'type' => 'color', 'value' => '#e17000', 'darkValue' => '#ff9a3c']);
		$this->ownTokens->create(input: ['slug' => 'gap', 'label' => 'Gap', 'type' => 'text', 'value' => '8px']);
		$this->deprecations->deprecate(token: '--nldesign-org-gap', input: ['severity' => 'info']);
		$bundle = json_decode((string)json_encode($this->service->export()), true);
		$tokens = $this->ownTokens->list();

		unset($this->appConfig[OwnTokenService::CONFIG_KEY], $this->appConfig[DeprecationRecords::CONFIG_KEY]);
		$result = $this->service->import(bundle: $bundle);

		$this->assertTrue(condition: $result['applied'], message: json_encode($result));
		$this->assertSame(expected: ['applied' => true, 'count' => 2], actual: $result['sections']['ownTokens']);
		$this->assertSame(expected: $tokens, actual: $this->ownTokens->list());
		$this->assertSame(expected: 'info', actual: $this->deprecations->list()['--nldesign-org-gap']['severity']);
		$this->assertStringContainsString(needle: '--nldesign-org-brand-accent: #e17000;', haystack: $this->overridesService->getRawContent());
	}//end testOwnTokensRoundTrip()

	/**
	 * A bundle without the new keys, as an older release writes, leaves own tokens as they are.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/own-tokens/spec.md#requirement-own-tokens-travel-with-the-configuration-bundle
	 */
	public function testBundleWithoutOwnTokensLeavesThemUnchanged(): void {
		$this->seedConfig();
		$bundle = json_decode((string)json_encode($this->service->export()), true);
		unset($bundle['ownTokens'], $bundle['tokenDeprecations']);
		$this->ownTokens->create(input: ['slug' => 'keep', 'label' => 'Keep', 'type' => 'text', 'value' => 'x']);

		$result = $this->service->import(bundle: $bundle);

		$this->assertTrue(condition: $result['applied'], message: json_encode($result));
		$this->assertArrayHasKey(key: '--nldesign-org-keep', array: $this->ownTokens->list());
		$this->assertSame(expected: ['applied' => false, 'count' => 0], actual: $result['sections']['ownTokens']);
	}//end testBundleWithoutOwnTokensLeavesThemUnchanged()

	/**
	 * A bundle with a bad own token is refused as a whole, before anything is written.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/own-tokens/spec.md#requirement-own-tokens-travel-with-the-configuration-bundle
	 */
	public function testBadOwnTokenRefusesTheBundle(): void {
		$this->seedConfig();
		$bundle = json_decode((string)json_encode($this->service->export()), true);
		$bundle['ownTokens'] = ['--nldesign-org-x' => ['label' => 'X', 'type' => 'color', 'value' => 'red; } body {']];

		$result = $this->service->import(bundle: $bundle);

		$this->assertFalse(condition: $result['valid']);
		$this->assertSame(expected: 'ownTokens', actual: $result['errors'][0]['section']);
		$this->assertSame(expected: [], actual: $this->ownTokens->list());
	}//end testBadOwnTokenRefusesTheBundle()
}//end class
