<?php

/**
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/internal-variable-tokens/specs/component-tokens/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\InternalScopesService;
use OCA\Thematiq\Service\RuntimeFile\DirectoryRuntimeFileStore;
use OCA\Thematiq\Service\RuntimeFile\RuntimeFileLocator;
use OCA\Thematiq\Service\TokenRegistry;
use OCP\App\IAppManager;
use OCP\ITempManager;
use OCP\IURLGenerator;
use PHPUnit\Framework\TestCase;

/**
 * The internal scopes carry exactly the internal tokens something gives a value, and nothing else.
 */
class InternalScopesServiceTest extends TestCase {

	private string $dir;

	private DirectoryRuntimeFileStore $store;

	private InternalScopesService $service;

	protected function setUp(): void {
		parent::setUp();
		$this->dir = sys_get_temp_dir() . '/thematiq-internal-' . bin2hex(random_bytes(4));
		mkdir($this->dir . '/app', 0777, true);
		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getAppPath')->willReturn($this->dir . '/app');
		$this->store = new DirectoryRuntimeFileStore($this->dir . '/store');
		$locator = new RuntimeFileLocator(
			$appManager,
			$this->store,
			$this->createMock(IURLGenerator::class),
			$this->createMock(ITempManager::class)
		);
		$this->service = new InternalScopesService(files: $locator);
	}

	protected function tearDown(): void {
		exec('rm -rf ' . escapeshellarg($this->dir));
		parent::tearDown();
	}

	public function testASetTokenAndAnOverrideProduceExactlyTwoRules(): void {
		$this->store->write('css/tokens/custom-test.css', ":root {\n\t--nldesign-color-primary: #154273;\n\t--nldesign-nc-dp-hover-color: #e8eef5;\n}\n");
		$this->store->write('css/custom-overrides.css', ":root {\n\t--nldesign-nc-plyr-audio-control-background-hover: #f4f1ea !important;\n}\n");

		$css = $this->service->forSet(tokenSet: 'custom-test', designSystemId: 'nldesign', withDark: true);

		$this->assertSame(2, substr_count($css, '{'), $css);
		$this->assertStringContainsString('--dp-hover-color: var(--nldesign-nc-dp-hover-color);', $css);
		$this->assertStringContainsString('.dp__theme_light', $css);
		$this->assertStringContainsString(':is(body)' . InternalScopesService::BUMP . " {\n\t--plyr-audio-control-background-hover: var(--nldesign-nc-plyr-audio-control-background-hover);", $css);
		$this->assertStringNotContainsString('#154273', $css, 'no value from the set reaches the output');
	}

	public function testNothingSetMeansNoRules(): void {
		$this->store->write('css/tokens/custom-test.css', ":root {\n\t--nldesign-color-primary: #154273;\n\t--nldesign-nc-color-mark: #ffe08a;\n}\n");

		$this->assertSame('', $this->service->forSet(tokenSet: 'custom-test', designSystemId: 'nldesign', withDark: true));
		$this->assertSame('', $this->service->forSet(tokenSet: 'missing', designSystemId: 'nldesign', withDark: true));
	}

	public function testAnOverrideSaveIsReflectedOnTheNextBuild(): void {
		$this->store->write('css/tokens/custom-test.css', ":root {\n\t--nldesign-nc-dp-hover-color: #e8eef5;\n}\n");
		$before = $this->service->forSet(tokenSet: 'custom-test', designSystemId: 'nldesign', withDark: true);
		$this->store->write('css/custom-overrides.css', ":root {\n\t--nldesign-cn-kpi-accent: #24578f !important;\n}\n");
		$after = $this->service->forSet(tokenSet: 'custom-test', designSystemId: 'nldesign', withDark: true);

		$this->assertSame(1, substr_count($before, '{'));
		$this->assertSame(2, substr_count($after, '{'));
		$this->assertStringContainsString('--cn-kpi-accent: var(--nldesign-cn-kpi-accent);', $after);
	}

	public function testATokenDeclaredOnlyInDarkAppliesOnlyInTheDarkScopes(): void {
		$this->store->write(
			'css/tokens/dark/custom-test.css',
			"body[data-theme-dark], body[data-themes*=dark] {\n\t--nldesign-nc-dp-hover-color: #333333;\n}\n"
		);

		$css = $this->service->forSet(tokenSet: 'custom-test', designSystemId: 'nldesign', withDark: true);

		$this->assertStringContainsString('@media (prefers-color-scheme: dark)', $css);
		$this->assertStringContainsString(':where(body[data-theme-dark], body[data-themes*=dark]) :is(', $css);
		$this->assertStringNotContainsString(":is(.vue-date-time-picker__wrapper[data-v-02e90461] .dp__theme_light)" . InternalScopesService::BUMP . " {", $css, 'no unscoped rule');
		$this->assertStringNotContainsString(':root', $css);
	}

	public function testADarkVariantThatIsNotInjectedIsNotRead(): void {
		$this->store->write('css/tokens/dark/custom-test.css', "body[data-theme-dark] {\n\t--nldesign-nc-dp-hover-color: #333333;\n}\n");

		$this->assertSame('', $this->service->forSet(tokenSet: 'custom-test', designSystemId: 'nldesign', withDark: false));
	}

	public function testABodyLevelSelectorIsScopedOnTheSameElement(): void {
		$body = array_key_first(array_filter(
			TokenRegistry::getInternalTokens(),
			static fn (array $t): bool => in_array('body', $t['selectors'], true) && count($t['selectors']) === 1
		));
		$this->assertNotNull($body, 'the map holds a token declared on :root');

		$css = $this->service->build(lightNames: [], anyNames: [$body]);

		$this->assertStringContainsString(':is(body):where(body[data-theme-dark], body[data-themes*=dark])' . InternalScopesService::BUMP, $css);
	}

	public function testTheRegistryHoldsEveryInternalTokenAndNoRuntimeOne(): void {
		$internal = TokenRegistry::getInternalTokens();

		$this->assertTrue(TokenRegistry::isEditable('--nldesign-nc-dp-hover-color'));
		$this->assertSame('dp', $internal['--nldesign-nc-dp-hover-color']['owner']);
		$this->assertSame('--cn-kpi-accent', $internal['--nldesign-cn-kpi-accent']['variable']);
		$this->assertNotContains('--systemtag-color', array_column($internal, 'variable'));
		$this->assertFalse(TokenRegistry::isEditable('--nldesign-nc-systemtag-color'));
		$this->assertArrayNotHasKey('--nldesign-nc-dp-hover-color', TokenRegistry::getTokens(), 'kept out of the editor tabs until change 4');
		// Under the Nextcloud name too: the settable theme loader read every
		// settable status entry and listed all 453 as brand rows.
		$this->assertArrayNotHasKey('--dp-hover-color', TokenRegistry::getTokens());
		$this->assertCount(45, TokenRegistry::getSettableTokens(), 'only the 45 theme variables');
		$this->assertNull(TokenRegistry::settableToken('--dp-hover-color'));
		$this->assertSame('--nldesign-nc-color-mark', TokenRegistry::settableToken('--color-mark'));
		$this->assertCount(453, $internal);
	}
}
