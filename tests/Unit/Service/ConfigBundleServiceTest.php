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

use OCA\Thematiq\Service\AppThemingService;
use OCA\Thematiq\Service\ConfigBundleService;
use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\CustomOverridesService;
use OCA\Thematiq\Service\CustomTokenSetService;
use OCA\Thematiq\Service\CustomTokenSetValidator;
use OCA\Thematiq\Service\DarkPaletteService;
use OCA\Thematiq\Service\EmailThemingService;
use OCA\Thematiq\Service\FontService;
use OCA\Thematiq\Service\ShippedTokenSetAuditService;
use OCA\Thematiq\Service\TokenSetPreviewService;
use OCA\Thematiq\Service\TokenSetService;
use OCA\Thematiq\Service\TokenSetVocabularyAuditService;
use OCA\Thematiq\Service\UpstreamFreshnessService;
use OCP\App\IAppManager;
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

		$this->overridesService = new CustomOverridesService($appManager, $cssParser);
		$this->customTokenSetService = new CustomTokenSetService(
			$appManager,
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
			$logger
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
		$this->assertSame(1, $bundle['bundleVersion']);
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
}//end class
