<?php

/**
 * The theme vocabulary is complete: every Nextcloud theme variable outside
 * icons is mapped or settable, and a settable one is stored as its token.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/theme-vocabulary-complete/tasks.md#task-1.2
 * @spec openspec/changes/theme-vocabulary-complete/tasks.md#task-1.3
 * @spec openspec/changes/theme-vocabulary-complete/tasks.md#task-1.4
 * @spec openspec/changes/theme-vocabulary-complete/tasks.md#task-1.5
 * @spec openspec/changes/theme-vocabulary-complete/tasks.md#task-3.1
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit;

use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\CustomOverridesService;
use OCA\Thematiq\Service\DarkPaletteService;
use OCA\Thematiq\Service\ShippedTokenSetAuditService;
use OCA\Thematiq\Service\TokenRegistry;
use OCP\App\IAppManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Registry, overrides writer and contrast pairs for the settable theme variables.
 */
class ThemeVocabularyCompleteTest extends TestCase {

	/**
	 * A temporary app root with a css/ directory.
	 *
	 * @var string
	 */
	private string $appDir;

	/**
	 * The overrides writer under test.
	 *
	 * @var CustomOverridesService
	 */
	private CustomOverridesService $overrides;

	/**
	 * Build a writer on a temporary app root.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->appDir = sys_get_temp_dir() . '/thematiq-796-' . bin2hex(random_bytes(4));
		mkdir($this->appDir . '/css', 0777, true);

		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getAppPath')->willReturn($this->appDir);
		$parser = new CssParserService();
		$darkPalette = new DarkPaletteService(new ContrastService(), $parser, $appManager, $this->createMock(LoggerInterface::class));
		$this->overrides = new CustomOverridesService($appManager, $parser, $darkPalette);
	}//end setUp()

	/**
	 * Remove the temporary app root.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		foreach (glob($this->appDir . '/css/*') ?: [] as $file) {
			unlink($file);
		}

		rmdir($this->appDir . '/css');
		rmdir($this->appDir);
		parent::tearDown();
	}//end tearDown()

	/**
	 * The search highlight and the header height are editable.
	 *
	 * @return void
	 */
	public function testFormerlyExcludedVariablesAreEditable(): void {
		$this->assertTrue(TokenRegistry::isEditable(tokenName: '--color-mark'));
		$this->assertTrue(TokenRegistry::isEditable(tokenName: '--header-height'));
		$this->assertTrue(TokenRegistry::isEditable(tokenName: '--color-main-background'));
		$this->assertSame('--nldesign-color-mark', TokenRegistry::settableToken(name: '--color-mark'));
	}//end testFormerlyExcludedVariablesAreEditable()

	/**
	 * Icons and runtime variables stay out of the registry.
	 *
	 * @return void
	 */
	public function testIconAndRuntimeVariablesStayUnregistered(): void {
		$this->assertFalse(TokenRegistry::isEditable(tokenName: '--icon-download-dark'));
		$this->assertFalse(TokenRegistry::isEditable(tokenName: '--systemtag-color'));
		$this->assertSame(['--icon-download-dark' => 'not an editable token'], $this->overrides->findRejected(tokens: ['--icon-download-dark' => 'url(x.svg)']));
	}//end testIconAndRuntimeVariablesStayUnregistered()

	/**
	 * Exactly the 13 structural variables carry the advanced flag.
	 *
	 * @return void
	 */
	public function testThirteenStructuralVariablesAreAdvanced(): void {
		$advanced = array_keys(array_filter(TokenRegistry::getTokens(), static fn (array $meta): bool => ($meta['advanced'] ?? false) === true));
		sort($advanced);

		$this->assertSame(
			[
				'--body-container-margin', '--body-height', '--breakpoint-mobile', '--clickable-area-large',
				'--clickable-area-small', '--default-clickable-area', '--default-grid-baseline', '--filter-background-blur',
				'--header-height', '--header-menu-item-height', '--navigation-width', '--sidebar-max-width', '--sidebar-min-width',
			],
			$advanced
		);
		$this->assertTrue(TokenRegistry::getTokens()['--header-height']['advanced']);
	}//end testThirteenStructuralVariablesAreAdvanced()

	/**
	 * A settable override is written as its token, light only, and read back under its Nextcloud name.
	 *
	 * @return void
	 */
	public function testASettableOverrideIsStoredAsItsTokenWithNoDarkValue(): void {
		$this->overrides->write(tokens: ['--color-main-background' => '#fdfcf8', '--color-primary' => '#24578f']);
		$css = $this->overrides->getRawContent();

		$this->assertStringContainsString('--nldesign-color-main-background: #fdfcf8 !important;', $css);
		$this->assertStringNotContainsString('  --color-main-background:', $css);

		$dark = substr($css, (int)strpos($css, '@media (prefers-color-scheme: dark)'));
		$this->assertStringNotContainsString('main-background', $dark, 'a settable variable gets no derived dark value');
		$this->assertStringContainsString('--color-primary:', $dark, 'a brand override keeps its derived dark value');

		$this->assertSame(['--color-main-background' => '#fdfcf8', '--color-primary' => '#24578f'], $this->overrides->read());
	}//end testASettableOverrideIsStoredAsItsTokenWithNoDarkValue()

	/**
	 * Build the audit service from its real collaborators.
	 *
	 * @return ShippedTokenSetAuditService
	 */
	private function audit(): ShippedTokenSetAuditService {
		return new ShippedTokenSetAuditService(new ContrastService(), new CssParserService());
	}//end audit()

	/**
	 * A set that only moves the selection wash is audited against Nextcloud's selected text.
	 *
	 * @return void
	 */
	public function testASetThatOnlyMovesTheSelectionWashIsAudited(): void {
		$pairs = $this->audit()->auditThemePairs(declarations: ['--nldesign-color-background-selection' => '#1a1a1a']);

		$this->assertCount(1, $pairs);
		$this->assertSame('selection', $pairs[0]['pair']);
		$this->assertSame('--nldesign-color-text-selection', $pairs[0]['foreground']);
		$this->assertFalse($pairs[0]['pass'], '#222222 text on #1a1a1a is far below 4.5:1');
	}//end testASetThatOnlyMovesTheSelectionWashIsAudited()

	/**
	 * A dark highlight under dark text fails and names both tokens.
	 *
	 * @return void
	 */
	public function testAPaleHighlightUnderDarkTextFails(): void {
		$pairs = $this->audit()->auditThemePairs(declarations: ['--nldesign-color-mark' => '#222222', '--nldesign-color-text' => '#1a1a1a']);

		$this->assertSame('highlight', $pairs[0]['pair']);
		$this->assertSame('--nldesign-color-text', $pairs[0]['foreground']);
		$this->assertSame('--nldesign-color-mark', $pairs[0]['background']);
		$this->assertLessThan(4.5, $pairs[0]['ratio']);
		$this->assertFalse($pairs[0]['pass']);
	}//end testAPaleHighlightUnderDarkTextFails()

	/**
	 * A set that declares neither side is not reported.
	 *
	 * @return void
	 */
	public function testASetThatDeclaresNeitherSideIsNotReported(): void {
		$this->assertSame([], $this->audit()->auditThemePairs(declarations: ['--nldesign-color-text' => '#1a1a1a', '--nldesign-color-primary' => '#154273']));
	}//end testASetThatDeclaresNeitherSideIsNotReported()
}//end class
