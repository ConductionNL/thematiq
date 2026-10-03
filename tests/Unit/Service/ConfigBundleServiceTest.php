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
use OCA\Thematiq\Service\CustomTokenSetService;
use OCA\Thematiq\Service\CustomTokenSetValidator;
use OCA\Thematiq\Service\DarkPaletteService;
use OCA\Thematiq\Service\DeprecationRecords;
use OCA\Thematiq\Service\DesignSystemService;
use OCA\Thematiq\Service\DocumentAssetService;
use OCA\Thematiq\Service\EmailThemingService;
use OCA\Thematiq\Service\FontService;
use OCA\Thematiq\Service\ImageSniffer;
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
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->appDir = sys_get_temp_dir() . '/nldesign-bundle-test-' . uniqid();
		mkdir($this->appDir . '/css/tokens', 0777, true);

		$appManager = $this->createMock(IAppManager::class);
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

		$config = $this->createMock(IConfig::class);
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
		$logger = $this->createMock(LoggerInterface::class);

		$records = new DeprecationRecords($config);
		$this->ownTokens = new OwnTokenService($config, new TokenValueValidator(), $records);
		$this->deprecations = new TokenDeprecationService($records, $this->ownTokens, $appManager, $cssParser);
		$this->overridesService = new CustomOverridesService(
			new DirectoryRuntimeFileStore($appManager->getAppPath('thematiq')),
			$cssParser,
			new DarkPaletteService($contrast, $cssParser, $appManager, $logger),
			$config,
			new DesignSystemService($appManager, $config),
			null,
			$this->ownTokens
		);
		$this->customTokenSetService = new CustomTokenSetService(
			new DirectoryRuntimeFileStore($appManager->getAppPath('thematiq')),
			$config,
			$customTokenSetValidator,
			$contrast,
			new DarkPaletteService($contrast, $cssParser, $appManager, $logger)
		);
		$cacheFactory = $this->createMock(ICacheFactory::class);
		$cacheFactory->method('createDistributed')->willReturn($this->createMock(ICache::class));

		$this->tokenSetService = new TokenSetService(
			$appManager,
			$config,
			$logger,
			new ShippedTokenSetAuditService($contrast, $cssParser),
			$cacheFactory,
			new TokenSetVocabularyAuditService($cssParser)
		);

		$appThemingService = new AppThemingService($config, $appManager);

		$urlGenerator = $this->createMock(IURLGenerator::class);
		$previewService = new TokenSetPreviewService($appManager);
		$emailThemingService = new EmailThemingService($config, $this->tokenSetService, $previewService, $urlGenerator);

		$clientService = $this->createMock(IClientService::class);
		$freshnessService = new UpstreamFreshnessService($config, $clientService, $this->tokenSetService, $logger);

		$this->fontService = $this->createMock(FontService::class);
		$this->fontService->method('getManifest')->willReturn([]);

		$this->service = new ConfigBundleService(
			$config,
			$appManager,
			$this->tokenSetService,
			$this->overridesService,
			$this->customTokenSetService,
			$customTokenSetValidator,
			$appThemingService,
			$cssParser,
			$emailThemingService,
			$this->fontService,
			$freshnessService,
			new ScheduledSwitchStore($config),
			$logger,
			new BundleExtraSections(
				new AssistantMarkService($config, $emailThemingService, $this->createMock(Capabilities::class)),
				new DocumentAssetService($this->createMock(IAppData::class), $config),
				new AppBrandService(
					$config,
					$appManager,
					$appThemingService,
					$this->tokenSetService,
					new AppBrandLogoStore($this->createMock(IAppData::class), new ImageSniffer()),
					$this->createMock(IURLGenerator::class)
				)
			),
			new TokenLifecycleBundleSection($this->ownTokens, $this->deprecations, $this->overridesService)
		);
	}//end setUp()

	/**
	 * Remove the temp app dir after each test.
	 */
	protected function tearDown(): void {
		$this->rrmdir($this->appDir);
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
				$this->rrmdir($path);
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
	 */
	public function testExportContainsAllSections(): void {
		$this->seedConfig();

		$bundle = $this->service->export();

		$this->assertSame('nldesign-config-bundle', $bundle['format']);
		$this->assertSame(3, $bundle['bundleVersion']);
		$this->assertSame('utrecht', $bundle['config']['tokenSet']);
		$this->assertTrue($bundle['config']['hideSlogan']);
		$this->assertTrue($bundle['config']['showMenuLabels']);
		$this->assertSame(['mail', 'files'], $bundle['config']['disabledApps']);
		$this->assertTrue($bundle['config']['upstreamFreshnessEnabled']);
		$this->assertSame('Gemeente Voorbeeld', $bundle['emailFooter']['orgName']);
		$this->assertStringContainsString('--color-primary: #123456', $bundle['customOverridesCss']);
		$this->assertCount(1, $bundle['customTokenSets']);
		$this->assertSame('custom-gemeente-x', $bundle['customTokenSets'][0]['id']);
		$this->assertStringContainsString('--nldesign-color-primary: #007bc7', $bundle['customTokenSets'][0]['css']);
		$this->assertFalse($bundle['customFonts']['binariesIncluded']);
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
	 */
	public function testImportInvalidCustomSetWritesNothingAndListsError(): void {
		$bundle = $this->baseBundle(
			[
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

		$this->assertFalse($result['valid']);
		$this->assertFalse($result['applied']);
		$this->assertNotEmpty($result['errors']);
		$this->assertSame('customTokenSets', $result['errors'][0]['section']);

		// Nothing was written: config untouched, no custom set file created.
		$this->assertSame($before, $this->appConfig);
		$this->assertFileDoesNotExist($this->appDir . '/css/tokens/custom-bad.css');
	}//end testImportInvalidCustomSetWritesNothingAndListsError()

	/**
	 * Unknown override variables are skipped and counted, not fatal — the
	 * import proceeds and writes only the recognised editable token.
	 */
	public function testUnknownOverrideTokensSkippedNotFatal(): void {
		$bundle = $this->baseBundle(
			[
				'customOverridesCss' => ":root {\n  --color-primary: #223344 !important;\n  --unknown-var: #ffffff !important;\n}\n",
			]
		);

		$result = $this->service->import(bundle: $bundle, dryRun: false);

		$this->assertTrue($result['valid']);
		$this->assertTrue($result['applied']);
		$this->assertSame(1, $result['sections']['customOverridesCss']['written']);
		$this->assertSame(1, $result['sections']['customOverridesCss']['skipped']);

		$written = $this->overridesService->read();
		$this->assertSame(['--color-primary' => '#223344'], $written);
	}//end testUnknownOverrideTokensSkippedNotFatal()

	/**
	 * A token set referencing a custom set contained WITHIN the same bundle
	 * resolves successfully, even though it has never existed on this
	 * instance before, and becomes the active set after apply.
	 */
	public function testTokenSetResolvableFromWithinBundle(): void {
		$bundle = $this->baseBundle(
			[
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

		$this->assertTrue($result['valid']);
		$this->assertTrue($result['applied']);
		$this->assertSame('custom-gemeente-x', $this->appConfig['token_set']);
		$this->assertTrue($this->tokenSetService->isValidTokenSet(tokenSetId: 'custom-gemeente-x'));
	}//end testTokenSetResolvableFromWithinBundle()

	/**
	 * A token set id that is neither shipped/installed nor present in the
	 * bundle's own custom sets is a hard error naming the unresolvable id.
	 */
	public function testNonexistentTokenSetIsHardError(): void {
		$bundle = $this->baseBundle(['config' => ['tokenSet' => 'atlantis']]);

		$result = $this->service->import(bundle: $bundle, dryRun: false);

		$this->assertFalse($result['valid']);
		$this->assertStringContainsString('atlantis', $result['errors'][0]['message']);
	}//end testNonexistentTokenSetIsHardError()

	/**
	 * The approved mark's three values travel in the bundle; an unsafe logo is refused, and an
	 * older bundle without the section leaves the mark alone.
	 *
	 * @spec openspec/specs/assistant-approved-mark/spec.md
	 */
	public function testAssistantMarkSurvivesExportAndImport(): void {
		$this->seedConfig();
		$this->appConfig['assistant_mark_enabled'] = '1';
		$this->appConfig['assistant_mark_organisation'] = 'Gemeente Voorbeeld';
		$this->appConfig['assistant_mark_logo'] = 'https://example.org/logo.svg';

		$bundle = $this->service->export();
		$this->assertSame(['enabled' => true, 'organisation' => 'Gemeente Voorbeeld', 'logo' => 'https://example.org/logo.svg'], $bundle['assistantMark']);

		$this->appConfig['assistant_mark_enabled'] = '0';
		$this->appConfig['assistant_mark_organisation'] = '';
		$this->appConfig['assistant_mark_logo'] = '';
		$result = $this->service->import(bundle: $bundle, dryRun: false);

		$this->assertTrue($result['valid']);
		$this->assertSame('1', $this->appConfig['assistant_mark_enabled']);
		$this->assertSame('Gemeente Voorbeeld', $this->appConfig['assistant_mark_organisation']);
		$this->assertSame('https://example.org/logo.svg', $this->appConfig['assistant_mark_logo']);

		$bundle['assistantMark']['logo'] = 'javascript:alert(1)';
		$this->assertFalse($this->service->import(bundle: $bundle, dryRun: true)['valid']);

		unset($bundle['assistantMark']);
		$this->appConfig['assistant_mark_enabled'] = '0';
		$this->assertTrue($this->service->import(bundle: $bundle, dryRun: false)['valid']);
		$this->assertSame('0', $this->appConfig['assistant_mark_enabled'], 'An older bundle leaves the mark alone.');
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
	 */
	public function testPrimaryDrivesComponentsSurvivesExportAndImport(): void {
		$this->seedConfig();
		$this->appConfig['primary_drives_components'] = '1';

		$bundle = $this->service->export();
		$this->assertTrue($bundle['config']['primaryDrivesComponents']);

		$this->appConfig['primary_drives_components'] = '0';
		$result = $this->service->import(bundle: $bundle, dryRun: false);

		$this->assertTrue($result['valid']);
		$this->assertSame('1', $this->appConfig['primary_drives_components']);
	}//end testPrimaryDrivesComponentsSurvivesExportAndImport()

	/**
	 * A bundle that omits the key imports as off rather than as an error.
	 *
	 * Bundles exported before the toggle existed have no such key, and the
	 * setting's default is off, so the absent case is a valid bundle — not
	 * something an admin has to hand-edit before a promotion will run.
	 *
	 * @spec openspec/specs/component-tokens/spec.md
	 */
	public function testAnAbsentPrimaryDrivesComponentsImportsAsOff(): void {
		$this->appConfig['primary_drives_components'] = '1';

		$result = $this->service->import(bundle: $this->baseBundle(), dryRun: false);

		$this->assertTrue($result['valid']);
		$this->assertSame('0', $this->appConfig['primary_drives_components']);
	}//end testAnAbsentPrimaryDrivesComponentsImportsAsOff()

	/**
	 * A non-boolean `primaryDrivesComponents` is rejected, not coerced.
	 *
	 * The string "false" is truthy in PHP, so coercion would turn an explicit
	 * off into an on and lock every component colour on the target
	 * environment.
	 *
	 * @spec openspec/specs/component-tokens/spec.md
	 */
	public function testANonBooleanPrimaryDrivesComponentsIsRejected(): void {
		$bundle = $this->baseBundle(['config' => ['primaryDrivesComponents' => 'false']]);

		$result = $this->service->import(bundle: $bundle, dryRun: false);

		$this->assertFalse($result['valid']);
		$this->assertNotEmpty(
			array_filter(
				$result['errors'],
				static fn (array $error): bool => str_contains(
					$error['message'],
					'config.primaryDrivesComponents'
				)
			),
			'the error names the offending key'
		);
	}//end testANonBooleanPrimaryDrivesComponentsIsRejected()

	/**
	 * A dry-run of a valid bundle reports the would-be sections and writes
	 * nothing at all.
	 */
	public function testDryRunWritesNothing(): void {
		$this->seedConfig();
		$before = $this->appConfig;
		$beforeOverrides = $this->overridesService->getRawContent();

		$bundle = $this->baseBundle(
			[
				'config' => ['tokenSet' => 'utrecht', 'hideSlogan' => false, 'showMenuLabels' => false],
			]
		);

		$result = $this->service->import(bundle: $bundle, dryRun: true);

		$this->assertTrue($result['valid']);
		$this->assertTrue($result['dryRun']);
		$this->assertFalse($result['applied']);
		$this->assertArrayHasKey('sections', $result);

		$this->assertSame($before, $this->appConfig);
		$this->assertSame($beforeOverrides, $this->overridesService->getRawContent());
	}//end testDryRunWritesNothing()

	/**
	 * Importing the same valid bundle twice yields byte-identical state —
	 * app values, custom-overrides.css bytes, and custom-set files/manifest.
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

		$this->assertSame($firstOverrides, $secondOverrides);
		$this->assertSame($firstManifest, $secondManifest);
		$this->assertSame($firstConfig, $secondConfig);
	}//end testImportIsIdempotent()

	/**
	 * Round-trip: seed a representative configuration, export it, wipe every
	 * part, import the exported bundle, and assert the resulting state is
	 * identical to the seeded state (app values, custom-overrides.css bytes,
	 * custom-set file bytes + manifest).
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

		$this->assertTrue($result['applied']);
		$this->assertSame($seededConfig['token_set'], $this->appConfig['token_set']);
		$this->assertSame($seededConfig['hide_slogan'], $this->appConfig['hide_slogan']);
		$this->assertSame($seededConfig['show_menu_labels'], $this->appConfig['show_menu_labels']);
		$this->assertSame($seededConfig['disabled_apps'], $this->appConfig['disabled_apps']);
		$this->assertSame($seededConfig['upstream_freshness_enabled'], $this->appConfig['upstream_freshness_enabled']);
		$this->assertSame($seededConfig['email_footer_org_name'], $this->appConfig['email_footer_org_name']);

		$this->assertSame($seededOverrides, $this->overridesService->getRawContent());
		$this->assertSame($seededManifest, $this->customTokenSetService->getManifest());
		$this->assertSame($seededCustomCss, $this->customTokenSetService->getRawContent(id: 'custom-gemeente-x'));
	}//end testRoundTripExportWipeImportRestoresIdenticalState()

	/**
	 * An envelope with the wrong `format` is a hard error before any other
	 * section is even considered.
	 */
	public function testWrongFormatIsHardError(): void {
		$bundle = $this->baseBundle(['format' => 'something-else']);

		$result = $this->service->import(bundle: $bundle, dryRun: false);

		$this->assertFalse($result['valid']);
		$this->assertSame('envelope', $result['errors'][0]['section']);
	}//end testWrongFormatIsHardError()

	/**
	 * An invalid emailFooter URL (non-http(s) scheme) is a hard error and
	 * blocks the whole import — reusing EmailThemingService's own rule set.
	 */
	public function testInvalidEmailFooterUrlIsHardError(): void {
		$bundle = $this->baseBundle(
			['emailFooter' => ['orgName' => 'X', 'accessibilityUrl' => 'javascript:alert(1)', 'privacyUrl' => '']]
		);

		$result = $this->service->import(bundle: $bundle, dryRun: false);

		$this->assertFalse($result['valid']);
		$this->assertSame('emailFooter', $result['errors'][0]['section']);
		$this->assertArrayNotHasKey('email_footer_org_name', $this->appConfig);
	}//end testInvalidEmailFooterUrlIsHardError()

	/**
	 * The document footer line travels as a value, the document images as metadata only.
	 *
	 * @spec openspec/specs/document-house-style/spec.md
	 */
	public function testDocumentStyleFooterLineSurvivesExportAndImport(): void {
		$this->seedConfig();
		$this->appConfig['document_style_footer_line'] = 'Postbus 1, 1234 AB Voorbeeld';
		$this->appConfig['document_style_assets'] = json_encode(['logo' => ['mime' => 'image/png', 'size' => 10, 'uploadedAt' => 1]]);

		$bundle = $this->service->export();
		$this->assertSame('Postbus 1, 1234 AB Voorbeeld', $bundle['documentStyle']['footerLine']);
		$this->assertFalse($bundle['documentStyle']['binariesIncluded']);
		$this->assertSame('image/png', $bundle['documentStyle']['assets']['logo']['mime']);

		$this->appConfig['document_style_footer_line'] = '';
		$this->appConfig['document_style_assets'] = '{}';
		$result = $this->service->import(bundle: $bundle, dryRun: false);

		$this->assertTrue($result['valid']);
		$this->assertSame('Postbus 1, 1234 AB Voorbeeld', $this->appConfig['document_style_footer_line']);
		$this->assertSame('{}', $this->appConfig['document_style_assets'], 'Image metadata is never applied.');

		$bundle['documentStyle']['footerLine'] = str_repeat('x', 201);
		$this->assertFalse($this->service->import(bundle: $bundle, dryRun: true)['valid']);
	}//end testDocumentStyleFooterLineSurvivesExportAndImport()

	/**
	 * Brands per app travel as app => set, logos as metadata only; a version 2 bundle leaves them alone.
	 *
	 * @spec openspec/specs/per-app-theming/spec.md
	 */
	public function testAppBrandsSurviveExportAndImport(): void {
		$this->seedConfig();
		$this->appConfig['app_brands'] = json_encode(['collectives' => ['tokenSet' => 'utrecht', 'logoLarge' => ['mime' => 'image/png', 'size' => 9, 'uploadedAt' => 1], 'logoSmall' => null]]);

		$bundle = json_decode((string)json_encode($this->service->export()), true);
		$this->assertSame(3, $bundle['bundleVersion']);
		$this->assertSame('utrecht', $bundle['appBrands']['collectives']['tokenSet']);

		$this->appConfig['app_brands'] = '{}';
		$result = $this->service->import(bundle: $bundle, dryRun: false);
		$this->assertTrue($result['valid'], json_encode($result['errors'] ?? []));
		$stored = json_decode($this->appConfig['app_brands'], true);
		$this->assertSame('utrecht', $stored['collectives']['tokenSet']);
		$this->assertNull($stored['collectives']['logoLarge'], 'Logo metadata is never applied without its file.');

		$bundle['appBrands']['collectives']['tokenSet'] = 'no-such-set';
		$this->assertFalse($this->service->import(bundle: $bundle, dryRun: true)['valid']);

		$bundle['bundleVersion'] = 2;
		unset($bundle['appBrands']);
		$this->assertTrue($this->service->import(bundle: $bundle, dryRun: false)['valid']);
		$this->assertSame('utrecht', json_decode($this->appConfig['app_brands'], true)['collectives']['tokenSet']);
	}//end testAppBrandsSurviveExportAndImport()

	/**
	 * The customFonts section is exported/reported as metadata only and is
	 * never applied — no custom_fonts app value is ever written by import().
	 */
	public function testCustomFontsSectionIsNeverApplied(): void {
		$bundle = $this->baseBundle(
			['customFonts' => ['manifest' => ['custom-x' => ['name' => 'X', 'role' => 'body']]]]
		);

		$result = $this->service->import(bundle: $bundle, dryRun: false);

		$this->assertTrue($result['applied']);
		$this->assertFalse($result['sections']['customFonts']['applied']);
		$this->assertArrayNotHasKey('custom_fonts', $this->appConfig);
	}//end testCustomFontsSectionIsNeverApplied()

	/**
	 * The environment config.php declares never travels in a bundle: it is
	 * the one value that must differ between OTAP environments.
	 *
	 * @spec openspec/specs/config-portability/spec.md
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
		$this->assertNotContains('environment', $keys);
		$this->assertArrayNotHasKey('environment', $bundle['config']);
		$this->assertStringNotContainsString('thematiq.environment', (string)json_encode($bundle));
	}//end testExportNeverCarriesTheEnvironment()

	/**
	 * The planned switches travel in the bundle without their runtime state.
	 *
	 * @spec openspec/specs/config-portability/spec.md
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
			[
				[
					'id' => 'a1',
					'tokenSet' => 'utrecht',
					'startAt' => '2027-04-26T16:00:00Z',
					'endAt' => '2027-04-28T06:00:00Z',
					'createdBy' => 'admin',
					'createdAt' => '2027-04-01T10:00:00Z',
				],
			],
			$switches
		);
	}//end testExportCarriesPlannedSwitchesWithoutRuntimeState()

	/**
	 * Scenario "A campaign prepared on acceptance goes to production": the
	 * planned switch names a custom set that only the bundle carries.
	 *
	 * @spec openspec/specs/config-portability/spec.md
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
		$this->rrmdir($this->appDir);
		mkdir($this->appDir . '/css/tokens', 0777, true);
		file_put_contents($this->appDir . '/css/tokens/utrecht.css', ":root {\n  --nldesign-color-primary: #000000;\n}\n");

		$result = $this->service->import(bundle: $bundle);

		$this->assertTrue($result['valid'], (string)json_encode($result['errors'] ?? []));
		$stored = json_decode($this->appConfig['scheduled_switches'], true);
		$this->assertSame('custom-gemeente-x', $stored[0]['tokenSet']);
		$this->assertSame('planned', $stored[0]['status']);
		$this->assertSame(1, $result['sections']['scheduledSwitches']['count']);
	}//end testAPlannedSwitchToABundledCustomSetIsImported()

	/**
	 * Importing a bundle that holds a running switch, as a version restore
	 * during a campaign does, keeps the switch running and its way back:
	 * restarting it would take the campaign look as the one to return to.
	 *
	 * @spec openspec/specs/config-portability/spec.md
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

		$this->assertTrue($result['applied'], (string)json_encode($result['errors'] ?? []));
		$stored = json_decode($this->appConfig['scheduled_switches'], true);
		$this->assertCount(1, $stored);
		$this->assertSame('running', $stored[0]['status']);
		$this->assertSame('nextcloud', $stored[0]['revertTo']);
		$this->assertTrue($stored[0]['coreSnapshot']);
	}//end testARunningSwitchKeepsItsStateThroughAnImport()

	/**
	 * A stale bundle does not replay a switch whose window has already ended.
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 */
	public function testAnEndedSwitchInABundleIsNotReplayed(): void {
		$this->seedConfig();
		$bundle = $this->service->export();
		$bundle['config']['scheduledSwitches'] = [
			['id' => 'old', 'tokenSet' => 'utrecht', 'startAt' => '2020-04-26T16:00:00Z', 'endAt' => '2020-04-28T06:00:00Z'],
			['id' => 'new', 'tokenSet' => 'utrecht', 'startAt' => '2099-04-26T16:00:00Z', 'endAt' => '2099-04-28T06:00:00Z'],
		];

		$result = $this->service->import(bundle: $bundle);

		$this->assertTrue($result['applied'], (string)json_encode($result['errors'] ?? []));
		$stored = json_decode($this->appConfig['scheduled_switches'], true);
		$this->assertSame(['new'], array_column($stored, 'id'));
		$this->assertSame('planned', $stored[0]['status']);
	}//end testAnEndedSwitchInABundleIsNotReplayed()

	/**
	 * A custom set keeps the design system it was created on through an export
	 * and an import, which a version restore is: a theme saved off stock
	 * Nextcloud must not come back as an NL Design System one. A design system
	 * the app does not ship is dropped rather than trusted.
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 */
	public function testACustomSetKeepsItsDesignSystemThroughARoundTrip(): void {
		$this->seedConfig();
		file_put_contents($this->appDir . '/design-systems.json', json_encode([['id' => 'none'], ['id' => 'nldesign']]));
		$manifest = json_decode($this->appConfig[CustomTokenSetService::MANIFEST_KEY], true);
		$manifest['custom-gemeente-x']['design_system'] = 'none';
		$this->appConfig[CustomTokenSetService::MANIFEST_KEY] = json_encode($manifest);
		$bundle = $this->service->export();

		$this->assertTrue($this->service->import(bundle: $bundle)['applied']);
		$stored = json_decode($this->appConfig[CustomTokenSetService::MANIFEST_KEY], true);
		$this->assertSame('none', $stored['custom-gemeente-x']['design_system']);

		$bundle['customTokenSets'][0]['design_system'] = 'evil';
		$this->assertTrue($this->service->import(bundle: $bundle)['applied']);
		$stored = json_decode($this->appConfig[CustomTokenSetService::MANIFEST_KEY], true);
		$this->assertArrayNotHasKey('design_system', $stored['custom-gemeente-x']);
	}//end testACustomSetKeepsItsDesignSystemThroughARoundTrip()

	/**
	 * A design-systems manifest that is not a list of design systems allows none.
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 */
	public function testAnUnreadableDesignSystemManifestAllowsNone(): void {
		$this->seedConfig();
		file_put_contents($this->appDir . '/design-systems.json', 'not json');
		$bundle = $this->service->export();
		$bundle['customTokenSets'][0]['design_system'] = 'none';

		$this->assertTrue($this->service->import(bundle: $bundle)['applied']);
		$stored = json_decode($this->appConfig[CustomTokenSetService::MANIFEST_KEY], true);
		$this->assertArrayNotHasKey('design_system', $stored['custom-gemeente-x']);
	}//end testAnUnreadableDesignSystemManifestAllowsNone()

	/**
	 * Scenario "An overlapping plan blocks the whole import".
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 */
	public function testOverlappingPlannedSwitchesBlockTheWholeImport(): void {
		$bundle = $this->baseBundle(['bundleVersion' => 2]);
		$bundle['config']['hideSlogan'] = true;
		$bundle['config']['scheduledSwitches'] = [
			['id' => 'a1', 'tokenSet' => 'utrecht', 'startAt' => '2027-05-01T00:00:00Z', 'endAt' => '2027-05-07T00:00:00Z', 'syncCoreTheming' => false],
			['id' => 'a2', 'tokenSet' => 'utrecht', 'startAt' => '2027-05-05T00:00:00Z', 'endAt' => '2027-05-10T00:00:00Z', 'syncCoreTheming' => false],
		];

		$result = $this->service->import(bundle: $bundle);

		$this->assertFalse($result['valid']);
		$this->assertStringContainsString('overlap', (string)json_encode($result['errors']));
		$this->assertArrayNotHasKey('hide_slogan', $this->appConfig);
		$this->assertArrayNotHasKey('scheduled_switches', $this->appConfig);
	}//end testOverlappingPlannedSwitchesBlockTheWholeImport()

	/**
	 * A planned switch to a set neither installed nor bundled is refused.
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 */
	public function testAPlannedSwitchToAnUnknownSetIsRefused(): void {
		$bundle = $this->baseBundle(['bundleVersion' => 2]);
		$bundle['config']['scheduledSwitches'] = [
			['id' => 'a1', 'tokenSet' => 'custom-nergens', 'startAt' => '2027-05-01T00:00:00Z', 'endAt' => null, 'syncCoreTheming' => false],
		];

		$result = $this->service->import(bundle: $bundle);

		$this->assertFalse($result['valid']);
		$this->assertStringContainsString('custom-nergens', (string)json_encode($result['errors']));
	}//end testAPlannedSwitchToAnUnknownSetIsRefused()

	/**
	 * A version 1 bundle (kept versions from before planned switches) still
	 * imports and leaves the planned switches alone.
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 */
	public function testAVersionOneBundleStillImportsAndKeepsPlannedSwitches(): void {
		$this->appConfig['scheduled_switches'] = '[{"id":"keep"}]';

		$result = $this->service->import(bundle: $this->baseBundle());

		$this->assertTrue($result['valid'], (string)json_encode($result['errors'] ?? []));
		$this->assertSame('[{"id":"keep"}]', $this->appConfig['scheduled_switches']);
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

		$this->assertTrue($result['applied'], json_encode($result));
		$this->assertSame(['applied' => true, 'count' => 2], $result['sections']['ownTokens']);
		$this->assertSame($tokens, $this->ownTokens->list());
		$this->assertSame('info', $this->deprecations->list()['--nldesign-org-gap']['severity']);
		$this->assertStringContainsString('--nldesign-org-brand-accent: #e17000;', $this->overridesService->getRawContent());
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

		$this->assertTrue($result['applied'], json_encode($result));
		$this->assertArrayHasKey('--nldesign-org-keep', $this->ownTokens->list());
		$this->assertSame(['applied' => false, 'count' => 0], $result['sections']['ownTokens']);
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

		$this->assertFalse($result['valid']);
		$this->assertSame('ownTokens', $result['errors'][0]['section']);
		$this->assertSame([], $this->ownTokens->list());
	}//end testBadOwnTokenRefusesTheBundle()
}//end class
