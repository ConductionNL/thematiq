<?php

/**
 * Unit tests for ComplianceReportService.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/compliance-evidence/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\ComplianceReportService;
use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\ContrastTools;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\CustomOverridesService;
use OCA\Thematiq\Service\CustomTokenSetService;
use OCA\Thematiq\Service\DesignSystemService;
use OCA\Thematiq\Service\InstanceReportContext;
use OCA\Thematiq\Service\ShippedTokenSetAuditService;
use OCA\Thematiq\Service\ThemeSelectionSources;
use OCP\App\IAppManager;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;
use OCP\IURLGenerator;
use PHPUnit\Framework\TestCase;

/**
 * Fixtures-based unit tests for the active-configuration compliance report.
 *
 * Covers: known-ratio fixtures (tasks.md#task-4.1), override precedence and
 * overridesHash change (task-4.2), summary classification (task-4.3),
 * determinism + honest-scope discipline (task-4.4), and the shared-contract
 * scenario against ShippedTokenSetAuditService (task-4.5).
 */
class ComplianceReportServiceTest extends TestCase {

	/**
	 * The temp app directory standing in for the nldesign app path.
	 *
	 * @var string
	 */
	private string $appDir;

	/**
	 * The active token set id used by the fixture (mutable per test).
	 *
	 * @var string
	 */
	private string $activeTokenSetId = 'nldesign-fixture';

	/**
	 * The custom-overrides layer returned by the mocked CustomOverridesService.
	 *
	 * @var array<string, string>
	 */
	private array $customOverrides = [];

	/**
	 * The shipped token-set metadata returned by the mocked DesignSystemService.
	 *
	 * @var array<string, mixed>
	 */
	private array $tokenSetMeta = [];

	/**
	 * The custom-set manifest returned by the mocked CustomTokenSetService.
	 *
	 * @var array<string, mixed>
	 */
	private array $customManifest = [];

	/**
	 * The frozen clock timestamp (2026-01-01T00:00:00Z).
	 *
	 * @var int
	 */
	private int $frozenTime = 1767225600;

	/**
	 * The service under test.
	 *
	 * @var ComplianceReportService
	 */
	private ComplianceReportService $service;

	/**
	 * Set up a temp app dir with an "all pairs pass" fixture and mocked collaborators.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->appDir = sys_get_temp_dir() . '/nldesign-compliance-test-' . uniqid();
		mkdir($this->appDir . '/css/systems/nldesign', 0777, true);
		mkdir($this->appDir . '/css/tokens', 0777, true);

		$this->writeDefaults($this->allPassDefaults());
		$this->writeOverridesMapping();

		$this->activeTokenSetId = 'nldesign-fixture';
		$this->customOverrides = [];
		$this->tokenSetMeta = [
			'id' => 'nldesign-fixture',
			'name' => 'NL Design Fixture',
			'design_system' => 'nldesign',
			'theming' => ['background_color' => '#ffffff'],
		];
		$this->customManifest = [];

		$this->service = $this->buildService();
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
	 * The base --nldesign-* token values for which every one of the 18 pairs passes.
	 *
	 * @return array<string, string> Token name => value.
	 */
	private function allPassDefaults(): array {
		return [
			'--nldesign-color-primary' => '#000000',
			'--nldesign-color-primary-text' => '#ffffff',
			'--nldesign-color-primary-light' => '#ffffff',
			'--nldesign-color-text' => '#000000',
			'--nldesign-color-text-muted' => '#000000',
			'--nldesign-color-error' => '#660000',
			'--nldesign-color-warning' => '#664400',
			'--nldesign-color-success' => '#004d00',
			'--nldesign-color-info' => '#002d66',
			'--nldesign-color-border-dark' => '#000000',
		];
	}//end allPassDefaults()

	/**
	 * Write css/systems/nldesign/defaults.css from a token map.
	 *
	 * @param array<string, string> $tokens The token name => value map.
	 *
	 * @return void
	 */
	private function writeDefaults(array $tokens): void {
		$lines = [':root {'];
		foreach ($tokens as $name => $value) {
			$lines[] = '  ' . $name . ': ' . $value . ';';
		}

		$lines[] = '}';

		file_put_contents(
			$this->appDir . '/css/systems/nldesign/defaults.css',
			implode("\n", $lines) . "\n"
		);
	}//end writeDefaults()

	/**
	 * Write the fixed css/systems/nldesign/overrides.css mapping used by every
	 * test — the --color-* => --nldesign-* mapping for all 17 non-background
	 * pair tokens (mirrors the production file's mapping exactly).
	 *
	 * @return void
	 */
	private function writeOverridesMapping(): void {
		$lines = [
			':root {',
			'  --color-primary-text: var(--nldesign-color-primary-text) !important;',
			'  --color-primary: var(--nldesign-color-primary) !important;',
			'  --color-primary-element-text: var(--nldesign-color-primary-text) !important;',
			'  --color-primary-element: var(--nldesign-color-primary) !important;',
			'  --color-primary-light-text: var(--nldesign-color-primary) !important;',
			'  --color-primary-light: var(--nldesign-color-primary-light) !important;',
			'  --color-primary-element-light-text: var(--nldesign-color-primary) !important;',
			'  --color-primary-element-light: var(--nldesign-color-primary-light) !important;',
			'  --color-main-text: var(--nldesign-color-text) !important;',
			'  --color-text-maxcontrast: var(--nldesign-color-text-muted) !important;',
			'  --color-text-error: var(--nldesign-color-error) !important;',
			'  --color-text-success: var(--nldesign-color-success) !important;',
			'  --color-text-warning: var(--nldesign-color-warning) !important;',
			'  --color-error: var(--nldesign-color-error) !important;',
			'  --color-warning: var(--nldesign-color-warning) !important;',
			'  --color-success: var(--nldesign-color-success) !important;',
			'  --color-info: var(--nldesign-color-info) !important;',
			'  --color-border-maxcontrast: var(--nldesign-color-border-dark) !important;',
			'  --color-border-error: var(--nldesign-color-error) !important;',
			'  --color-border-success: var(--nldesign-color-success) !important;',
			'}',
		];

		file_put_contents(
			$this->appDir . '/css/systems/nldesign/overrides.css',
			implode("\n", $lines) . "\n"
		);
	}//end writeOverridesMapping()

	/**
	 * Build a ComplianceReportService wired to the current mutable test state.
	 *
	 * @return ComplianceReportService The service under test.
	 */
	private function buildService(): ComplianceReportService {
		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getAppPath')->willReturn($this->appDir);
		$appManager->method('getAppVersion')->willReturn('0.1.3');

		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			fn (string $app, string $key, $default = '') => ($key === 'token_set' ? $this->activeTokenSetId : $default)
		);
		$config->method('getSystemValue')->willReturnCallback(
			fn (string $key, $default = '') => ($key === 'instanceid' ? 'test-instance-abc' : $default)
		);

		$urlGenerator = $this->createMock(IURLGenerator::class);
		$urlGenerator->method('getBaseUrl')->willReturn('https://cloud.example.test');

		$timeFactory = $this->createMock(ITimeFactory::class);
		$timeFactory->method('getTime')->willReturnCallback(fn () => $this->frozenTime);

		$overridesService = $this->createMock(CustomOverridesService::class);
		$overridesService->method('read')->willReturnCallback(fn () => $this->customOverrides);

		$designSystemService = $this->createMock(DesignSystemService::class);
		$designSystemService->method('getTokenSetMeta')->willReturnCallback(fn () => $this->tokenSetMeta);

		$customTokenSetService = $this->createMock(CustomTokenSetService::class);
		$customTokenSetService->method('getManifest')->willReturnCallback(fn () => $this->customManifest);

		return new ComplianceReportService(
			measuring: new ContrastTools(contrast: new ContrastService(), cssParser: new CssParserService()),
			theme: new ThemeSelectionSources(
				overrides: $overridesService,
				designSystems: $designSystemService,
				customSets: $customTokenSetService
			),
			instance: new InstanceReportContext(
				appManager: $appManager,
				config: $config,
				urlGenerator: $urlGenerator,
				timeFactory: $timeFactory
			)
		);
	}//end buildService()

	/**
	 * Find a pair row by its foreground token name.
	 *
	 * @param array<int, array<string, mixed>> $pairs The evaluated pairs.
	 * @param string $foreground The foreground token to find.
	 *
	 * @return array<string, mixed> The matching pair row.
	 */
	private function findPair(array $pairs, string $foreground): array {
		foreach ($pairs as $pair) {
			if ($pair['foreground'] === $foreground) {
				return $pair;
			}
		}

		$this->fail('No pair found for foreground ' . $foreground);
	}//end findPair()

	/**
	 * Known ratio: #000000 on #ffffff is exactly 21.00:1 and passes.
	 */
	public function testKnownRatioBlackOnWhiteIs21AndPasses(): void {
		$data = $this->service->generate();
		$pair = $this->findPair(pairs: $data['pairs'], foreground: '--color-main-text');

		$this->assertSame('#000000', $pair['foregroundValue']);
		$this->assertSame('#ffffff', $pair['backgroundValue']);
		$this->assertEqualsWithDelta(21.0, $pair['ratio'], 0.01);
		$this->assertSame('pass', $pair['verdict']);
	}//end testKnownRatioBlackOnWhiteIs21AndPasses()

	/**
	 * Known ratio: #767676 on #ffffff is 4.54:1 — the AA boundary, and passes.
	 */
	public function testKnownRatioBoundaryPasses(): void {
		$this->writeDefaults(array_merge($this->allPassDefaults(), ['--nldesign-color-text' => '#767676']));

		$data = $this->service->generate();
		$pair = $this->findPair(pairs: $data['pairs'], foreground: '--color-main-text');

		$this->assertEqualsWithDelta(4.54, $pair['ratio'], 0.01);
		$this->assertSame('pass', $pair['verdict']);
	}//end testKnownRatioBoundaryPasses()

	/**
	 * Known ratio: #cccccc on #ffffff is ~1.61:1 and fails.
	 */
	public function testKnownRatioLowContrastFails(): void {
		$this->writeDefaults(array_merge($this->allPassDefaults(), ['--nldesign-color-text' => '#cccccc']));

		$data = $this->service->generate();
		$pair = $this->findPair(pairs: $data['pairs'], foreground: '--color-main-text');

		$this->assertEqualsWithDelta(1.61, $pair['ratio'], 0.01);
		$this->assertSame('fail', $pair['verdict']);
	}//end testKnownRatioLowContrastFails()

	/**
	 * An unresolved var() chain is reported unevaluated, never passing, with
	 * the unresolved token named in the note.
	 */
	public function testUnresolvedVarIsUnevaluatedNeverPassing(): void {
		$this->writeDefaults(array_merge($this->allPassDefaults(), ['--nldesign-color-text' => 'var(--nldesign-does-not-exist)']));

		$data = $this->service->generate();
		$pair = $this->findPair(pairs: $data['pairs'], foreground: '--color-main-text');

		$this->assertSame('unevaluated', $pair['verdict']);
		$this->assertNull($pair['ratio']);
		$this->assertStringContainsString('--nldesign-does-not-exist', (string)$pair['note']);
	}//end testUnresolvedVarIsUnevaluatedNeverPassing()

	/**
	 * The report contains exactly 18 pairs, in matrix order.
	 */
	public function testReportContainsEighteenPairsInMatrixOrder(): void {
		$data = $this->service->generate();

		$this->assertCount(18, $data['pairs']);
		$this->assertSame('--color-primary-text', $data['pairs'][0]['foreground']);
		$this->assertSame('--color-primary', $data['pairs'][0]['background']);
		$this->assertSame('--color-border-success', $data['pairs'][17]['foreground']);

		foreach ($data['pairs'] as $pair) {
			$this->assertArrayHasKey('foreground', $pair);
			$this->assertArrayHasKey('background', $pair);
			$this->assertArrayHasKey('foregroundValue', $pair);
			$this->assertArrayHasKey('backgroundValue', $pair);
			$this->assertArrayHasKey('ratio', $pair);
			$this->assertArrayHasKey('threshold', $pair);
			$this->assertArrayHasKey('basis', $pair);
			$this->assertArrayHasKey('verdict', $pair);
			$this->assertContains($pair['verdict'], ['pass', 'fail', 'unevaluated']);
		}
	}//end testReportContainsEighteenPairsInMatrixOrder()

	/**
	 * A custom override for --color-primary wins over the token set's value.
	 */
	public function testCustomOverrideWinsOverTokenSetValue(): void {
		$this->customOverrides = ['--color-primary' => '#767676'];

		$data = $this->service->generate();
		$pair = $this->findPair(pairs: $data['pairs'], foreground: '--color-primary');

		$this->assertSame('#767676', $pair['foregroundValue']);
		$this->assertNotSame('#000000', $pair['foregroundValue']);
	}//end testCustomOverrideWinsOverTokenSetValue()

	/**
	 * Changing a custom override changes the overridesHash.
	 */
	public function testOverridesHashChangesWithOverrides(): void {
		$withoutOverrides = $this->service->generate();

		$this->customOverrides = ['--color-primary' => '#767676'];
		$withOverrides = $this->service->generate();

		$this->assertNotSame(
			$withoutOverrides['metadata']['overridesHash'],
			$withOverrides['metadata']['overridesHash']
		);
	}//end testOverridesHashChangesWithOverrides()

	/**
	 * All 18 pairs passing yields an overall "pass" verdict with zero fail/unevaluated.
	 */
	public function testAllPassingYieldsOverallPass(): void {
		$data = $this->service->generate();

		$this->assertSame(18, $data['summary']['passed']);
		$this->assertSame(0, $data['summary']['failed']);
		$this->assertSame(0, $data['summary']['unevaluated']);
		$this->assertSame('pass', $data['summary']['verdict']);
	}//end testAllPassingYieldsOverallPass()

	/**
	 * One failing pair caps the overall verdict at "fail".
	 */
	public function testOneFailingPairFailsOverallVerdict(): void {
		$this->writeDefaults(array_merge($this->allPassDefaults(), ['--nldesign-color-text-muted' => '#999999']));

		$data = $this->service->generate();

		$this->assertSame(17, $data['summary']['passed']);
		$this->assertSame(1, $data['summary']['failed']);
		$this->assertSame(0, $data['summary']['unevaluated']);
		$this->assertSame('fail', $data['summary']['verdict']);
	}//end testOneFailingPairFailsOverallVerdict()

	/**
	 * Zero failing pairs but at least one unevaluated pair caps the verdict at
	 * "incomplete", never a clean "pass".
	 */
	public function testUnevaluatedPairsCapVerdictAtIncomplete(): void {
		$this->writeDefaults(
			array_merge(
				$this->allPassDefaults(),
				[
					'--nldesign-color-text-muted' => 'var(--nldesign-undefined-a)',
					'--nldesign-color-border-dark' => 'var(--nldesign-undefined-b)',
				]
			)
		);

		$data = $this->service->generate();

		$this->assertSame(0, $data['summary']['failed']);
		$this->assertSame(2, $data['summary']['unevaluated']);
		$this->assertSame('incomplete', $data['summary']['verdict']);
	}//end testUnevaluatedPairsCapVerdictAtIncomplete()

	/**
	 * A stock-Nextcloud active set (design_system: none) still produces a
	 * report, with every pair honestly unevaluated rather than scored against
	 * values the runtime never actually loads.
	 */
	public function testStockNextcloudConfigurationStillProducesReport(): void {
		$this->activeTokenSetId = 'nextcloud';
		$this->tokenSetMeta = [
			'id' => 'nextcloud',
			'name' => 'Nextcloud (default)',
			'design_system' => 'none',
			'theming' => ['background_color' => '#FFFFFF'],
		];

		$data = $this->service->generate();

		$this->assertCount(18, $data['pairs']);
		foreach ($data['pairs'] as $pair) {
			$this->assertSame('unevaluated', $pair['verdict']);
		}

		$this->assertSame('incomplete', $data['summary']['verdict']);
	}//end testStockNextcloudConfigurationStillProducesReport()

	/**
	 * Metadata carries every documented field, including the fallback
	 * "unversioned" token-set version.
	 */
	public function testMetadataIdentifiesTheAuditedConfiguration(): void {
		$data = $this->service->generate();
		$meta = $data['metadata'];

		$this->assertSame('test-instance-abc', $meta['instanceId']);
		$this->assertSame('https://cloud.example.test', $meta['instanceUrl']);
		$this->assertSame('0.1.3', $meta['appVersion']);
		$this->assertNotEmpty($meta['nextcloudVersion']);
		$this->assertSame('nldesign-fixture', $meta['tokenSet']['id']);
		$this->assertSame('NL Design Fixture', $meta['tokenSet']['name']);
		$this->assertSame('unversioned', $meta['tokenSet']['version']);
		$this->assertSame('nldesign', $meta['designSystem']);
		$this->assertSame('2026-01-01T00:00:00Z', $meta['generatedAt']);
		$this->assertSame(64, strlen((string)$meta['overridesHash']));
	}//end testMetadataIdentifiesTheAuditedConfiguration()

	/**
	 * A custom-set manifest version, when present, wins over "unversioned".
	 */
	public function testCustomSetManifestVersionIsUsedWhenPresent(): void {
		$this->activeTokenSetId = 'custom-foo';
		$this->tokenSetMeta = [];
		$this->customManifest = [
			'custom-foo' => [
				'name' => 'Custom Foo',
				'theming' => ['background_color' => '#ffffff'],
				'version' => '3',
			],
		];

		$data = $this->service->generate();

		$this->assertSame('3', $data['metadata']['tokenSet']['version']);
		// No design_system key in the custom manifest entry — falls back to
		// "nldesign", matching Application::injectThemeCSS()'s own default.
		$this->assertSame('nldesign', $data['metadata']['designSystem']);
	}//end testCustomSetManifestVersionIsUsedWhenPresent()

	/**
	 * Regeneration with an unchanged configuration and a frozen clock is
	 * byte-identical in both formats.
	 */
	public function testDeterministicRegeneration(): void {
		$this->assertSame($this->service->renderJson(), $this->service->renderJson());
		$this->assertSame($this->service->renderMarkdown(), $this->service->renderMarkdown());
	}//end testDeterministicRegeneration()

	/**
	 * Both renderers always embed the scope statement and never the phrase
	 * "WCAG compliant" or "voldoet aan WCAG" — even on a fully-passing report.
	 */
	public function testScopeStatementAlwaysPresentNeverClaimsCompliance(): void {
		$json = $this->service->renderJson();
		$markdown = $this->service->renderMarkdown();

		foreach ([$json, $markdown] as $rendered) {
			$this->assertStringContainsString('NOT a WCAG-EM audit', $rendered);
			$this->assertStringContainsString('NOT a full WCAG', $rendered);
			$this->assertStringNotContainsStringIgnoringCase('WCAG compliant', $rendered);
			$this->assertStringNotContainsStringIgnoringCase('voldoet aan WCAG', $rendered);
		}

		$decoded = json_decode($json, true);
		$this->assertArrayHasKey('scope', $decoded);
		$this->assertNotEmpty($decoded['scope']);
	}//end testScopeStatementAlwaysPresentNeverClaimsCompliance()

	/**
	 * The Markdown renderer embeds the JSON's pair count and metadata block.
	 */
	public function testMarkdownContainsMetadataAndPairTable(): void {
		$markdown = $this->service->renderMarkdown();

		$this->assertStringContainsString('## Metadata', $markdown);
		$this->assertStringContainsString('Instance id: test-instance-abc', $markdown);
		$this->assertStringContainsString('## Pair matrix (18 pairs)', $markdown);
		$this->assertStringContainsString('## Summary', $markdown);
		$this->assertStringContainsString('--color-primary-text', $markdown);
	}//end testMarkdownContainsMetadataAndPairTable()

	/**
	 * Shared-contract scenario: the same effective primary/primary-text and
	 * primary/background pairs, computed through ShippedTokenSetAuditService
	 * and ComplianceReportService, yield identical ratios (no overrides
	 * configured), and both classify a non-literal value as unevaluated.
	 */
	public function testSharedContractWithShippedTokenSetAuditService(): void {
		$contrast = new ContrastService();
		$cssParser = new CssParserService();
		$audit = new ShippedTokenSetAuditService($contrast, $cssParser);

		$shippedResult = $audit->auditSet(
			appPath: $this->appDir,
			id: 'irrelevant-no-tokens-file',
			theming: ['background_color' => '#ffffff']
		);

		$data = $this->service->generate();
		$textPair = $this->findPair(pairs: $data['pairs'], foreground: '--color-primary-text');
		$uiPair = $this->findPair(pairs: $data['pairs'], foreground: '--color-primary');

		$this->assertSame($shippedResult['textRatio'], $textPair['ratio']);
		$this->assertSame($shippedResult['uiRatio'], $uiPair['ratio']);
		$this->assertSame('pass', $shippedResult['verdict']);

		// Both classify a non-literal value as unevaluated.
		$this->writeDefaults(array_merge($this->allPassDefaults(), ['--nldesign-color-primary-text' => 'var(--undefined)']));
		$shippedUnevaluated = $audit->auditSet(
			appPath: $this->appDir,
			id: 'irrelevant-no-tokens-file',
			theming: ['background_color' => '#ffffff']
		);
		$complianceUnevaluated = $this->findPair(
			pairs: $this->service->generate()['pairs'],
			foreground: '--color-primary-text'
		);

		$this->assertSame('unevaluated', $shippedUnevaluated['verdict']);
		$this->assertSame('unevaluated', $complianceUnevaluated['verdict']);
	}//end testSharedContractWithShippedTokenSetAuditService()
}//end class
