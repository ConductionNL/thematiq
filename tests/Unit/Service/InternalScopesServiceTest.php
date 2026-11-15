<?php

/**
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/component-tokens/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\InternalScopesService;
use OCA\Thematiq\Service\RuntimeFile\DirectoryRuntimeFileStore;
use OCA\Thematiq\Service\RuntimeFile\RuntimeFileLocator;
use OCA\Thematiq\Service\TokenRegistry;
use OCA\Thematiq\Service\TokenSetWear;
use OCP\App\IAppManager;
use OCP\ITempManager;
use OCP\IURLGenerator;
use PHPUnit\Framework\TestCase;

/**
 * The internal scopes carry exactly the internal tokens something gives a value, and nothing else.
 */
class InternalScopesServiceTest extends TestCase {

	/**
	 * The dir.
	 *
	 * @var string
	 */
	private string $dir;

	/**
	 * The store.
	 *
	 * @var DirectoryRuntimeFileStore
	 */
	private DirectoryRuntimeFileStore $store;

	/**
	 * The service.
	 *
	 * @var InternalScopesService
	 */
	private InternalScopesService $service;

	/**
	 * Set up each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->dir = sys_get_temp_dir() . '/thematiq-internal-' . bin2hex(random_bytes(4));
		mkdir($this->dir . '/app', 0777, true);
		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('getAppPath')->willReturn($this->dir . '/app');
		$this->store = new DirectoryRuntimeFileStore(root: $this->dir . '/store');
		$locator = new RuntimeFileLocator(
			appManager: $appManager,
			store: $this->store,
			urlGenerator: $this->createMock(originalClassName: IURLGenerator::class),
			tempManager: $this->createMock(originalClassName: ITempManager::class)
		);
		$this->service = new InternalScopesService(files: $locator);
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		exec('rm -rf ' . escapeshellarg($this->dir));
		parent::tearDown();
	}

	/**
	 * A set token and an override produce exactly two rules.
	 *
	 * @return void
	 */
	public function testASetTokenAndAnOverrideProduceExactlyTwoRules(): void {
		$this->store->write(
			'css/tokens/custom-test.css',
			":root {\n\t--nldesign-color-primary: #154273;\n\t--nldesign-nc-dp-hover-color: #e8eef5;\n}\n"
		);
		$this->store->write('css/custom-overrides.css', ":root {\n\t--nldesign-nc-plyr-audio-control-background-hover: #f4f1ea !important;\n}\n");

		$css = $this->service->forSet(tokenSet: 'custom-test', wear: new TokenSetWear(designSystemId: 'nldesign', withDark: true));

		$this->assertSame(expected: 2, actual: substr_count($css, '{'), message: $css);
		$this->assertStringContainsString(needle: '--dp-hover-color: var(--nldesign-nc-dp-hover-color);', haystack: $css);
		$this->assertStringContainsString(needle: '.dp__theme_light', haystack: $css);
		$this->assertStringContainsString(
			needle: ':is(body)' . InternalScopesService::BUMP . " {\n\t--plyr-audio-control-background-hover: var(--nldesign-nc-plyr-audio-control-background-hover);",
			haystack: $css
		);
		$this->assertStringNotContainsString(needle: '#154273', haystack: $css, message: 'no value from the set reaches the output');
	}

	/**
	 * Nothing set means no rules.
	 *
	 * @return void
	 */
	public function testNothingSetMeansNoRules(): void {
		$this->store->write('css/tokens/custom-test.css', ":root {\n\t--nldesign-color-primary: #154273;\n\t--nldesign-nc-color-mark: #ffe08a;\n}\n");

		$this->assertSame(expected: '', actual: $this->service->forSet(tokenSet: 'custom-test', wear: new TokenSetWear(designSystemId: 'nldesign', withDark: true)));
		$this->assertSame(expected: '', actual: $this->service->forSet(tokenSet: 'missing', wear: new TokenSetWear(designSystemId: 'nldesign', withDark: true)));
	}

	/**
	 * An override save is reflected on the next build.
	 *
	 * @return void
	 */
	public function testAnOverrideSaveIsReflectedOnTheNextBuild(): void {
		$this->store->write('css/tokens/custom-test.css', ":root {\n\t--nldesign-nc-dp-hover-color: #e8eef5;\n}\n");
		$before = $this->service->forSet(tokenSet: 'custom-test', wear: new TokenSetWear(designSystemId: 'nldesign', withDark: true));
		$this->store->write('css/custom-overrides.css', ":root {\n\t--nldesign-cn-kpi-accent: #24578f !important;\n}\n");
		$after = $this->service->forSet(tokenSet: 'custom-test', wear: new TokenSetWear(designSystemId: 'nldesign', withDark: true));

		$this->assertSame(expected: 1, actual: substr_count($before, '{'));
		$this->assertSame(expected: 2, actual: substr_count($after, '{'));
		$this->assertStringContainsString(needle: '--cn-kpi-accent: var(--nldesign-cn-kpi-accent);', haystack: $after);
	}

	/**
	 * A token declared only in dark applies only in the dark scopes.
	 *
	 * @return void
	 */
	public function testATokenDeclaredOnlyInDarkAppliesOnlyInTheDarkScopes(): void {
		$this->store->write(
			'css/tokens/dark/custom-test.css',
			"body[data-theme-dark], body[data-themes*=dark] {\n\t--nldesign-nc-dp-hover-color: #333333;\n}\n"
		);

		$css = $this->service->forSet(tokenSet: 'custom-test', wear: new TokenSetWear(designSystemId: 'nldesign', withDark: true));

		$this->assertStringContainsString(needle: '@media (prefers-color-scheme: dark)', haystack: $css);
		$this->assertStringContainsString(needle: ':where(body[data-theme-dark], body[data-themes*=dark]) :is(', haystack: $css);
		$this->assertStringNotContainsString(
			needle: ':is(.vue-date-time-picker__wrapper[data-v-02e90461] .dp__theme_light)' . InternalScopesService::BUMP . ' {',
			haystack: $css,
			message: 'no unscoped rule'
		);
		$this->assertStringNotContainsString(needle: ':root', haystack: $css);
	}

	/**
	 * A dark variant that is not injected is not read.
	 *
	 * @return void
	 */
	public function testADarkVariantThatIsNotInjectedIsNotRead(): void {
		$this->store->write('css/tokens/dark/custom-test.css', "body[data-theme-dark] {\n\t--nldesign-nc-dp-hover-color: #333333;\n}\n");

		$this->assertSame(expected: '', actual: $this->service->forSet(tokenSet: 'custom-test', wear: new TokenSetWear(designSystemId: 'nldesign', withDark: false)));
	}

	/**
	 * A body level selector is scoped on the same element.
	 *
	 * @return void
	 */
	public function testABodyLevelSelectorIsScopedOnTheSameElement(): void {
		$body = array_key_first(array_filter(
			TokenRegistry::getInternalTokens(),
			static fn (array $t): bool => in_array('body', $t['selectors'], true) && count($t['selectors']) === 1
		));
		$this->assertNotNull(actual: $body, message: 'the map holds a token declared on :root');

		$css = $this->service->build(lightNames: [], anyNames: [$body]);

		$this->assertStringContainsString(
			needle: ':is(body):where(body[data-theme-dark], body[data-themes*=dark])' . InternalScopesService::BUMP,
			haystack: $css
		);
	}

	/**
	 * The registry holds every internal token and no runtime one.
	 *
	 * @return void
	 */
	public function testTheRegistryHoldsEveryInternalTokenAndNoRuntimeOne(): void {
		$internal = TokenRegistry::getInternalTokens();

		$this->assertTrue(condition: TokenRegistry::isEditable('--nldesign-nc-dp-hover-color'));
		$this->assertSame(expected: 'dp', actual: $internal['--nldesign-nc-dp-hover-color']['owner']);
		$this->assertSame(expected: '--cn-kpi-accent', actual: $internal['--nldesign-cn-kpi-accent']['variable']);
		$this->assertNotContains(needle: '--systemtag-color', haystack: array_column($internal, 'variable'));
		$this->assertFalse(condition: TokenRegistry::isEditable('--nldesign-nc-systemtag-color'));
		$this->assertArrayNotHasKey(
			key: '--nldesign-nc-dp-hover-color',
			array: TokenRegistry::getTokens(),
			message: 'kept out of the editor tabs until change 4'
		);
		// Under the Nextcloud name too: the settable theme loader read every
		// settable status entry and listed all 453 as brand rows.
		$this->assertArrayNotHasKey(key: '--dp-hover-color', array: TokenRegistry::getTokens());
		$this->assertCount(
			expectedCount: 44,
			haystack: TokenRegistry::getSettableTokens(),
			message: 'only the 44 theme variables (--color-info-rgb is mapped)'
		);
		$this->assertNull(actual: TokenRegistry::settableToken('--dp-hover-color'));
		$this->assertSame(expected: '--nldesign-nc-color-mark', actual: TokenRegistry::settableToken('--color-mark'));
		$this->assertCount(expectedCount: 453, haystack: $internal);
	}
	/**
	 * Golden: the rules for every internal token, half of them light and half dark only, with unknown names left out.
	 *
	 * Recorded on the code before the split as a hash of the CSS, so any changed byte fails.
	 *
	 * @return void
	 */
	public function testEveryInternalTokenGolden(): void {
		$names = array_keys(TokenRegistry::getInternalTokens());
		$light = array_values(array_filter($names, static fn (string $n, int $i): bool => $i % 2 === 0, ARRAY_FILTER_USE_BOTH));
		$css = $this->service->build(lightNames: array_merge($light, ['--nldesign-not-internal']), anyNames: array_merge(array_reverse($names), ['--unknown']));

		$this->assertSame(expected: '216abb3848af1d88 453', actual: substr(hash('sha256', $css), 0, 16) . ' ' . count($names));
		$this->assertSame(expected: '', actual: $this->service->build(lightNames: ['--unknown'], anyNames: []));
	}

	/**
	 * Golden: the files a set is read from, with and without its dark variant, and which overrides file.
	 *
	 * @return void
	 */
	public function testTheFilesASetIsReadFrom(): void {
		$names = array_keys(TokenRegistry::getInternalTokens());
		$this->store->write('css/tokens/custom-test.css', ":root {\n\t" . $names[0] . ": #111;\n}\n");
		$this->store->write('css/tokens/dark/custom-test.css', "body[data-theme-dark] {\n\t" . $names[1] . ": #222;\n}\n");
		$this->store->write('css/custom-overrides.css', ":root {\n\t" . $names[2] . ": #333;\n}\n");
		$this->store->write('css/custom-overrides-custom-test.css', ":root {\n\t" . $names[3] . ": #444;\n}\n");

		$out = [];
		foreach ([['nldesign', true], ['nldesign', false], ['none', true]] as [$system, $dark]) {
			$out[] = substr(hash('sha256', $this->service->forSet(tokenSet: 'custom-test', wear: new TokenSetWear(designSystemId: $system, withDark: $dark))), 0, 12);
		}

		$this->assertSame(expected: ['c8f0c41f0c66', '6886c669a9f9', 'aa02dc6643c1'], actual: $out);
	}
}
