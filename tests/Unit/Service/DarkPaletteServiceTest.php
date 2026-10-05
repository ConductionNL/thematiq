<?php

/**
 * Unit tests for DarkPaletteService.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/dark-mode/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\DarkPaletteService;
use OCP\App\IAppManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Covers derivation with known color fixtures, the WCAG verification loop,
 * hand-authored precedence, generation idempotence, and the real shipped
 * `rijkshuisstijl`/`amsterdam` token sets (tasks.md#task-5.1).
 */
class DarkPaletteServiceTest extends TestCase {

	/**
	 * The WCAG contrast service (real instance — exact math matters here).
	 *
	 * @var ContrastService
	 */
	private ContrastService $contrast;

	/**
	 * The service under test (constructed per-test against either a temp
	 * fixture dir or the real worktree app path).
	 *
	 * @var DarkPaletteService
	 */
	private DarkPaletteService $service;

	/**
	 * The temp app directory standing in for the nldesign app path.
	 *
	 * @var string
	 */
	private string $appDir;

	/**
	 * Set up a temp app dir with a minimal defaults.css + tokens/ layout.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->contrast = new ContrastService();
		$this->appDir = sys_get_temp_dir() . '/nldesign-dark-test-' . uniqid();
		mkdir($this->appDir . '/css/systems/nldesign', 0777, true);
		mkdir($this->appDir . '/css/tokens', 0777, true);

		file_put_contents(
			$this->appDir . '/css/systems/nldesign/defaults.css',
			":root {\n\t--nldesign-color-primary: #154273;\n\t--nldesign-color-primary-text: #ffffff;\n}\n"
		);

		$this->service = $this->makeService(appDir: $this->appDir);
	}//end setUp()

	/**
	 * Remove the temp app dir after each test.
	 */
	protected function tearDown(): void {
		$this->rrmdir($this->appDir);
		parent::tearDown();
	}//end tearDown()

	/**
	 * Build a DarkPaletteService against a given app dir.
	 *
	 * @param string $appDir The app root path to resolve via the mocked IAppManager.
	 *
	 * @return DarkPaletteService The constructed service.
	 */
	private function makeService(string $appDir): DarkPaletteService {
		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getAppPath')->willReturn($appDir);

		return new DarkPaletteService(
			$this->contrast,
			new CssParserService(),
			$appManager,
			$this->createMock(LoggerInterface::class)
		);
	}//end makeService()

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
	 * Extract the HSL hue (degrees) of a hex colour, for hue-preservation assertions.
	 *
	 * @param string $hex The hex colour.
	 *
	 * @return float The hue in degrees.
	 */
	private function hueOf(string $hex): float {
		$rgb = $this->contrast->parseColor(value: $hex);
		$r = ($rgb[0] / 255);
		$g = ($rgb[1] / 255);
		$b = ($rgb[2] / 255);
		$max = max($r, $g, $b);
		$min = min($r, $g, $b);

		if ($max === $min) {
			return 0.0;
		}

		$delta = ($max - $min);
		if ($max === $r) {
			$h = ((($g - $b) / $delta) + (($g < $b) ? 6 : 0));
		} elseif ($max === $g) {
			$h = ((($b - $r) / $delta) + 2);
		} else {
			$h = ((($r - $g) / $delta) + 4);
		}

		$h = fmod($h * 60, 360);

		return ($h < 0) ? ($h + 360) : $h;
	}//end hueOf()

	/**
	 * Hue is preserved (within 2°) for a background-class token that is NOT
	 * the brand-primary exception token.
	 */
	public function testHuePreservedForBackgroundToken(): void {
		$derived = $this->service->deriveDarkDeclarations(['--nldesign-color-header-background' => '#154273']);

		$sourceHue = $this->hueOf(hex: '#154273');
		$derivedHue = $this->hueOf(hex: $derived['--nldesign-color-header-background']);

		$delta = abs($sourceHue - $derivedHue);
		$delta = min($delta, (360 - $delta));

		$this->assertLessThan(2.0, $delta);
	}//end testHuePreservedForBackgroundToken()

	/**
	 * Thematiq#696: a translucent surface stays translucent in dark mode. The
	 * dark channels come from the opaque colour, the light alpha is kept.
	 */
	public function testTranslucentSurfaceKeepsAlpha(): void {
		$derived = $this->service->deriveDarkDeclarations(['--nldesign-hero-cell-background' => 'rgba(255, 255, 255, 0.08)']);

		$this->assertMatchesRegularExpression('/^#[0-9a-f]{6}14$/i', $derived['--nldesign-hero-cell-background']);
		$opaque = $this->service->deriveDarkDeclarations(['--nldesign-hero-cell-background' => '#ffffff']);
		$this->assertSame(
			strtolower($opaque['--nldesign-hero-cell-background']),
			strtolower(substr($derived['--nldesign-hero-cell-background'], 0, 7))
		);
	}//end testTranslucentSurfaceKeepsAlpha()

	/**
	 * Thematiq#696: an 8-digit hex token is darkened, not skipped, and keeps its alpha.
	 */
	public function testEightDigitHexIsDarkenedAndKeepsAlpha(): void {
		$derived = $this->service->deriveDarkDeclarations(['--nldesign-color-overlay' => '#00000080']);

		$this->assertArrayHasKey('--nldesign-color-overlay', $derived);
		$this->assertMatchesRegularExpression('/^#[0-9a-f]{6}80$/i', $derived['--nldesign-color-overlay']);
	}//end testEightDigitHexIsDarkenedAndKeepsAlpha()

	/**
	 * The brand-primary exception keeps `--nldesign-color-primary` at its
	 * light value (hue delta 0) when the light primary/primary-text pair
	 * already passes AA — the rijkshuisstijl blue stays recognisable.
	 */
	public function testBrandPrimaryExceptionKeepsRecognisableBlue(): void {
		$derived = $this->service->deriveDarkDeclarations(
			[
				'--nldesign-color-primary' => '#154273',
				'--nldesign-color-primary-text' => '#ffffff',
			]
		);

		$this->assertSame('#154273', $derived['--nldesign-color-primary']);
	}//end testBrandPrimaryExceptionKeepsRecognisableBlue()

	/**
	 * A failing brand primary (light pair below AA) is NOT exempted — it
	 * gets the normal background-class dark transform instead of being kept as-is.
	 */
	public function testFailingBrandPrimaryIsNotExempted(): void {
		$derived = $this->service->deriveDarkDeclarations(
			[
				// Pale yellow vs white text: fails AA badly in light mode too.
				'--nldesign-color-primary' => '#ffff99',
				'--nldesign-color-primary-text' => '#ffffff',
			]
		);

		$this->assertNotSame('#ffff99', $derived['--nldesign-color-primary']);
	}//end testFailingBrandPrimaryIsNotExempted()

	/**
	 * A light background token (L≈0.96) derives to a dark lightness in [0.08, 0.16].
	 */
	public function testLightBackgroundDerivesToDarkLightness(): void {
		$derived = $this->service->deriveDarkDeclarations(['--nldesign-color-background' => '#F5F6F7']);

		$l = $this->lightnessOf(hex: $derived['--nldesign-color-background']);
		$this->assertGreaterThanOrEqual(0.08, $l);
		$this->assertLessThanOrEqual(0.16, $l);
	}//end testLightBackgroundDerivesToDarkLightness()

	/**
	 * A dark-ish text token (L≈0.2) derives to a light lightness in [0.62, 0.92].
	 */
	public function testDarkTextDerivesToLightLightness(): void {
		$derived = $this->service->deriveDarkDeclarations(['--nldesign-color-text' => '#333333']);

		$l = $this->lightnessOf(hex: $derived['--nldesign-color-text']);
		$this->assertGreaterThanOrEqual(0.62, $l);
		$this->assertLessThanOrEqual(0.92, $l);
	}//end testDarkTextDerivesToLightLightness()

	/**
	 * Lightness helper for the fixture assertions above.
	 *
	 * @param string $hex The hex colour.
	 *
	 * @return float The HSL lightness in [0, 1].
	 */
	private function lightnessOf(string $hex): float {
		$rgb = $this->contrast->parseColor(value: $hex);

		return ((max($rgb) + min($rgb)) / 2 / 255);
	}//end lightnessOf()

	/**
	 * Non-color tokens (size, font-family) never appear in the dark output.
	 */
	public function testNonColorTokensPassThroughUntouched(): void {
		$derived = $this->service->deriveDarkDeclarations(
			[
				'--nldesign-size-lint' => '48px',
				'--nldesign-font-family' => "'Fira Sans', sans-serif",
			]
		);

		$this->assertArrayNotHasKey('--nldesign-size-lint', $derived);
		$this->assertArrayNotHasKey('--nldesign-font-family', $derived);
	}//end testNonColorTokensPassThroughUntouched()

	/**
	 * A DANGLING alias and a gradient are unresolvable and are skipped, no exception.
	 *
	 * The alias here points at a token the set does not declare, so there is no
	 * literal to derive from. An alias whose target IS declared is a different
	 * case entirely — see {@see self::testAnAliasIsResolvedToTheLiteralItPointsAt()}.
	 */
	public function testUnparseableValuesAreSkipped(): void {
		$derived = $this->service->deriveDarkDeclarations(
			[
				'--nldesign-component-button-color' => 'var(--nldesign-color-primary)',
				'--nldesign-color-weird-gradient' => 'linear-gradient(90deg, #fff, #000)',
			]
		);

		$this->assertSame([], $derived);
	}//end testUnparseableValuesAreSkipped()

	/**
	 * An alias is resolved to the literal it points at, and darkened.
	 *
	 * THIS IS THE WHOLE POINT OF v2. `--utrecht-document-color:
	 * var(--tilburg-color-black-txt)` is declared on `:root`, so the browser
	 * substitutes it AT `:root` using the light value. The generated dark block
	 * scopes to `body`, a descendant, so darkening the TARGET there cannot
	 * reach an alias already resolved one level up — body inherits the light
	 * literal and the text never changes.
	 *
	 * Measured on a live portal before this fix: dark mode repainted 51% of the
	 * page while every text token stayed light-mode dark, leaving a heading at
	 * contrast 1.06 against its own band.
	 */
	public function testAnAliasIsResolvedToTheLiteralItPointsAt(): void {
		$derived = $this->service->deriveDarkDeclarations(
			[
				'--tilburg-color-black-txt' => '#333333',
				'--utrecht-document-color' => 'var(--tilburg-color-black-txt)',
			]
		);

		$this->assertArrayHasKey('--utrecht-document-color', $derived);
		// A LITERAL, not an alias: the emitted value must not depend on where
		// the block sits in the cascade.
		$this->assertStringStartsWith('#', $derived['--utrecht-document-color']);
		// And it must land light, because it is text.
		$this->assertGreaterThanOrEqual(0.62, $this->lightnessOf(hex: $derived['--utrecht-document-color']));
	}//end testAnAliasIsResolvedToTheLiteralItPointsAt()

	/**
	 * An alias chain is followed to the end.
	 */
	public function testAnAliasChainIsFollowedToItsLiteral(): void {
		$derived = $this->service->deriveDarkDeclarations(
			[
				'--vng-color-ink' => '#FFFFFF',
				'--tilburg-surface' => 'var(--vng-color-ink)',
				'--utrecht-document-background-color' => 'var(--tilburg-surface)',
			]
		);

		// White surface -> near-black.
		$this->assertLessThanOrEqual(0.16, $this->lightnessOf(hex: $derived['--utrecht-document-background-color']));
	}//end testAnAliasChainIsFollowedToItsLiteral()

	/**
	 * A CYCLIC alias terminates instead of recursing forever.
	 *
	 * A token set is authored input; two tokens pointing at each other must
	 * cost a bounded number of hops, not the process.
	 */
	public function testACyclicAliasTerminates(): void {
		$derived = $this->service->deriveDarkDeclarations(
			[
				'--a-color' => 'var(--b-color)',
				'--b-color' => 'var(--a-color)',
			]
		);

		$this->assertSame([], $derived);
	}//end testACyclicAliasTerminates()

	/**
	 * A `var()` FALLBACK is used only when the target is absent — which is what
	 * the browser does with it.
	 */
	public function testAnAliasFallbackIsUsedOnlyWhenTheTargetIsAbsent(): void {
		// The two sources are chosen so the OUTCOMES differ: #FFFFFF derives to
		// the text clamp's floor (0.62) and #111111 to near its ceiling (0.92).
		// Sources that happened to derive to the same place would let this pass
		// whichever branch ran.
		$absent = $this->service->deriveDarkDeclarations(['--utrecht-heading-1-color' => 'var(--nope, #FFFFFF)']);
		$this->assertLessThanOrEqual(0.7, $this->lightnessOf(hex: $absent['--utrecht-heading-1-color']));

		$present = $this->service->deriveDarkDeclarations(
			[
				'--present-color' => '#111111',
				'--utrecht-heading-1-color' => 'var(--present-color, #FFFFFF)',
			]
		);
		// Derived from #111111, NOT from the ignored #FFFFFF fallback.
		$this->assertGreaterThanOrEqual(0.85, $this->lightnessOf(hex: $present['--utrecht-heading-1-color']));
	}//end testAnAliasFallbackIsUsedOnlyWhenTheTargetIsAbsent()

	/**
	 * A value that merely CONTAINS a `var()` is left alone.
	 *
	 * Half-substituting a shorthand would corrupt it, and it is not a colour
	 * this service can derive in the first place.
	 */
	public function testACompoundValueContainingAVarIsNotResolved(): void {
		$derived = $this->service->deriveDarkDeclarations(
			[
				'--x-color' => '#333333',
				'--utrecht-border' => '1px solid var(--x-color)',
			]
		);

		$this->assertArrayNotHasKey('--utrecht-border', $derived);
	}//end testACompoundValueContainingAVarIsNotResolved()

	/**
	 * The utrecht convention: `-color` is the TEXT, `-background-color` is the
	 * surface behind it.
	 *
	 * Classifying by the word "text" alone put `--utrecht-document-color` and
	 * every `--utrecht-heading-N-color` in the background class, so the tokens
	 * that paint body copy were darkened towards black exactly like the
	 * surfaces they sit on.
	 */
	public function testTheUtrechtColorConventionSplitsTextFromSurface(): void {
		// A MID-TONE source, deliberately. The two classes use different
		// formulas — text clamps to [0.62, 0.92], a surface takes
		// 0.08 + 0.84 * inverted — and at L≈0.5 they disagree (0.62 vs 0.50).
		// At #333333 or #FFFFFF they happen to agree, so a test built on those
		// passes under either classification and proves nothing.
		$derived = $this->service->deriveDarkDeclarations(
			[
				'--utrecht-document-color' => '#808080',
				'--utrecht-document-background-color' => '#FFFFFF',
				'--utrecht-focus-outline-color' => '#808080',
			]
		);

		// Text takes the clamp...
		$this->assertGreaterThanOrEqual(0.60, $this->lightnessOf(hex: $derived['--utrecht-document-color']));
		// ...its surface goes dark...
		$this->assertLessThanOrEqual(0.16, $this->lightnessOf(hex: $derived['--utrecht-document-background-color']));
		// ...and an outline is not the glyphs, so it must NOT be pulled into
		// the text class just because its name ends in `-color`.
		$this->assertLessThan(0.60, $this->lightnessOf(hex: $derived['--utrecht-focus-outline-color']));
	}//end testTheUtrechtColorConventionSplitsTextFromSurface()

	/**
	 * A `textbox` token is a control, not text: its surfaces darken (thematiq#938).
	 *
	 * "text" used to be matched as a substring, so a white
	 * `--nldesign-component-textbox-background-color` took the text clamp and
	 * shipped as mid-grey #9e9e9e under light #d5ddf0 input text (1.96:1).
	 */
	public function testATextboxSurfaceIsNotTextClass(): void {
		$derived = $this->service->deriveDarkDeclarations(
			[
				'--nldesign-component-textbox-background-color' => '#FFFFFF',
				'--nldesign-component-textbox-border-color' => '#808080',
				'--nldesign-component-textbox-color' => '#222222',
				'--nldesign-color-button-primary-text' => '#222222',
			]
		);

		$this->assertLessThanOrEqual(0.16, $this->lightnessOf(hex: $derived['--nldesign-component-textbox-background-color']));
		$this->assertLessThan(0.60, $this->lightnessOf(hex: $derived['--nldesign-component-textbox-border-color']));
		// The typed text and a `-text` token stay text-class.
		$this->assertGreaterThanOrEqual(0.60, $this->lightnessOf(hex: $derived['--nldesign-component-textbox-color']));
		$this->assertGreaterThanOrEqual(0.60, $this->lightnessOf(hex: $derived['--nldesign-color-button-primary-text']));
	}//end testATextboxSurfaceIsNotTextClass()

	/**
	 * The repair loop also covers the labels on control fills (thematiq#938).
	 *
	 * These are the dark values development shipped for rijkshuisstijl: a
	 * white label clamped to #9e9e9e on the derived light-blue primary fill.
	 */
	public function testTheRepairLoopFixesControlLabels(): void {
		$result = $this->service->verifyAndRepair(
			[
				'--nldesign-color-background' => '#191c1f',
				'--nldesign-color-button-primary-background' => '#81afe2',
				'--nldesign-color-button-primary-hover' => '#9fbbe0',
				'--nldesign-color-button-primary-text' => '#9e9e9e',
				'--nldesign-component-button-primary-action-background-color' => '#81afe2',
				'--nldesign-component-button-primary-action-color' => '#9e9e9e',
				'--nldesign-component-button-primary-action-hover-background-color' => '#9fbbe0',
				'--nldesign-component-button-primary-action-hover-color' => '#9e9e9e',
				'--nldesign-component-textbox-background-color' => '#9e9e9e',
				'--nldesign-component-textbox-color' => '#d5ddf0',
			]
		);

		$this->assertSame([], $result['warnings']);
		$d = $result['declarations'];
		foreach ([
			['--nldesign-color-button-primary-text', '--nldesign-color-button-primary-background'],
			['--nldesign-component-button-primary-action-color', '--nldesign-component-button-primary-action-background-color'],
			['--nldesign-component-button-primary-action-hover-color', '--nldesign-component-button-primary-action-hover-background-color'],
			['--nldesign-component-textbox-color', '--nldesign-component-textbox-background-color'],
		] as [$fg, $bg]) {
			$this->assertGreaterThanOrEqual(4.5, $this->contrast->measure(foreground: $d[$fg], background: $d[$bg]), $fg . ' on ' . $bg);
		}
	}//end testTheRepairLoopFixesControlLabels()

	/**
	 * The body text reads on the selected navigation entry's wash (thematiq#1051).
	 *
	 * Nextcloud 34+ paints the selected entry as 16% of the primary colour
	 * over its dark navigation (#171717), 22% hovered, with the label in the
	 * main text colour. These are conduction-new's dark values: #7499de read
	 * 6.4:1 on the page but 4.37:1 on the wash, and 3.70:1 hovered.
	 */
	public function testTheRepairLoopFixesTheTextOnTheSelectedNavigationWash(): void {
		$result = $this->service->verifyAndRepair(
			[
				'--nldesign-color-background' => '#141414',
				'--nldesign-color-primary' => '#bcceef',
				'--nldesign-color-text' => '#7499de',
			]
		);

		$this->assertSame([], $result['warnings']);
		$text = $result['declarations']['--nldesign-color-text'];
		foreach (['16%' => '#31343a', '22%' => '#3b3f47'] as $share => $wash) {
			$this->assertGreaterThanOrEqual(4.5, $this->contrast->measure(foreground: $text, background: $wash), $text . ' on the ' . $share . ' wash ' . $wash);
		}
	}//end testTheRepairLoopFixesTheTextOnTheSelectedNavigationWash()

	/**
	 * A mid-tone page background still derives to a dark page (thematiq#952).
	 *
	 * vng's theming background_color #0277BD stood in for the page background
	 * and inverted to #42b1f3. The page keeps its hue but lands dark.
	 */
	public function testAMidTonePageBackgroundDerivesDark(): void {
		$derived = $this->service->deriveDarkDeclarations(['--nldesign-color-background' => '#0277BD']);

		$page = $derived['--nldesign-color-background'];
		$this->assertLessThanOrEqual(0.125, $this->lightnessOf(hex: $page));
		$this->assertEqualsWithDelta($this->hueOf(hex: '#0277BD'), $this->hueOf(hex: $page), 2.0);
		// Light body text still reads on it.
		$this->assertGreaterThanOrEqual(4.5, $this->contrast->measure(foreground: '#d5ddf0', background: $page));
	}//end testAMidTonePageBackgroundDerivesDark()

	/**
	 * `-rgb` companion tokens are regenerated from their derived base token.
	 */
	public function testRgbCompanionsMatchDerivedBase(): void {
		$derived = $this->service->deriveDarkDeclarations(
			[
				'--nldesign-color-error' => '#d52b1e',
				'--nldesign-color-error-rgb' => '213, 43, 30',
			]
		);

		$expectedRgb = $this->contrast->parseColor(value: $derived['--nldesign-color-error']);
		$this->assertSame(implode(', ', $expectedRgb), $derived['--nldesign-color-error-rgb']);
	}//end testRgbCompanionsMatchDerivedBase()

	/**
	 * A pathological pair (bright yellow bg / white fg) is repaired by the
	 * verification loop to a passing pair (snap fallback).
	 */
	public function testPathologicalPairIsRepairedToPassing(): void {
		$result = $this->service->verifyAndRepair(
			[
				'--nldesign-color-primary' => '#FFFF00',
				'--nldesign-color-primary-text' => '#FFFFFF',
			]
		);

		$this->assertSame([], $this->contrast->check(declarations: $result['declarations']));
		$this->assertNotSame('#FFFFFF', $result['declarations']['--nldesign-color-primary-text']);
	}//end testPathologicalPairIsRepairedToPassing()

	/**
	 * A hand-authored (protected) failing pair is emitted unchanged and
	 * produces a warning — never rewritten by the loop.
	 */
	public function testHandAuthoredFailurePreservedAndWarned(): void {
		// Identical fg/bg -> ratio exactly 1:1, unambiguously below the 4.5:1
		// threshold (mirrors the spec scenario's "measures 3.2:1" example).
		$declarations = [
			'--nldesign-color-primary' => '#4844AD',
			'--nldesign-color-primary-text' => '#4844AD',
		];

		$result = $this->service->verifyAndRepair(
			declarations: $declarations,
			protectedTokens: ['--nldesign-color-primary', '--nldesign-color-primary-text']
		);

		$this->assertSame('#4844AD', $result['declarations']['--nldesign-color-primary']);
		$this->assertSame('#4844AD', $result['declarations']['--nldesign-color-primary-text']);
		$this->assertNotEmpty($result['warnings']);
	}//end testHandAuthoredFailurePreservedAndWarned()

	/**
	 * A passing pair produces no warnings and is left untouched.
	 */
	public function testPassingPairProducesNoWarnings(): void {
		$result = $this->service->verifyAndRepair(
			[
				'--nldesign-color-primary' => '#154273',
				'--nldesign-color-primary-text' => '#ffffff',
			]
		);

		$this->assertSame([], $result['warnings']);
	}//end testPassingPairProducesNoWarnings()

	/**
	 * `renderDarkCss()` emits the exact dual-scope selector shape from
	 * design.md §2: the four `:not()` exclusions and the explicit
	 * `body[data-theme-dark], body[data-themes*=dark]` block.
	 */
	public function testRenderDarkCssSelectorShape(): void {
		$css = $this->service->renderDarkCss(
			setId: 'amsterdam',
			declarations: ['--nldesign-color-primary' => '#4844ad'],
			sourceHash: 'sha256:deadbeef'
		);

		$this->assertStringContainsString('@media (prefers-color-scheme: dark) {', $css);
		$this->assertStringContainsString(
			'body:not([data-theme-light]):not([data-theme-dark]):not([data-theme-light-highcontrast]):not([data-theme-dark-highcontrast]) {',
			$css
		);
		$this->assertStringContainsString('body[data-theme-dark],', $css);
		$this->assertStringContainsString('body[data-themes*=dark] {', $css);
		$this->assertStringContainsString('--nldesign-color-primary: #4844ad;', $css);
		$this->assertStringContainsString('sha256:deadbeef', $css);
		$this->assertStringNotContainsString('!important', $css);
	}//end testRenderDarkCssSelectorShape()

	/**
	 * The header names the GENERATOR VERSION, and the freshness check reads it.
	 *
	 * The version was stamped into every header from the start and read by
	 * nothing: {@see DarkPaletteService::isFresh()} compared only the source
	 * hash. So a generator fix shipped to an installation whose token files had
	 * not changed found every set "fresh", regenerated none of them, and left
	 * on disk exactly the artefacts it was written to replace — a silent no-op
	 * that prints the same output as a successful run. Measured: 41 of 41 sets
	 * reported `skipped (fresh)` after the algorithm changed underneath them.
	 *
	 * Asserted through the PUBLIC surface — the header text a real run writes
	 * and a real freshness check reads — rather than by reflecting on the
	 * private method, so it fails if either half drifts from the other.
	 */
	public function testAnArtefactFromAnOlderGeneratorIsNotFresh(): void {
		$tokenCss = ":root {\n\t--nldesign-color-primary: #154273;\n}\n";
		file_put_contents($this->appDir . '/css/tokens/aset.css', $tokenCss);

		$sourceHash = 'sha256:' . hash('sha256', $tokenCss);
		mkdir($this->appDir . '/css/tokens/dark', 0777, true);
		$darkFile = $this->appDir . '/css/tokens/dark/aset.css';

		// An artefact from an EARLIER generator, whose source has NOT changed —
		// the exact state a shipped algorithm fix arrives in. The source hash
		// matches, so a hash-only freshness check calls this fresh and skips.
		$stale = '/* GENERATED by nldesign DarkPaletteService v0 from tokens/aset.css (' . $sourceHash . ') — do not edit */';
		file_put_contents($darkFile, $stale);

		$result = $this->service->generateAndWrite(setId: 'aset');

		$this->assertTrue($result['written'], 'A v0 artefact must be regenerated: ' . $result['reason']);
		$this->assertStringContainsString(
			'DarkPaletteService v' . DarkPaletteService::GENERATOR_VERSION . ' ',
			(string)file_get_contents($darkFile)
		);

		// And the other direction, or the check would be satisfied by never
		// considering anything fresh: the file it just wrote IS fresh.
		$second = $this->service->generateAndWrite(setId: 'aset');
		$this->assertFalse($second['written']);
		$this->assertSame('fresh', $second['reason']);
	}//end testAnArtefactFromAnOlderGeneratorIsNotFresh()

	/**
	 * `!important` is never used — body-level scope already out-specifies the
	 * light `:root` layer without it.
	 */
	public function testGeneratedCssNeverUsesImportant(): void {
		$css = $this->service->renderDarkCss(setId: 'x', declarations: ['--nldesign-color-primary' => '#000000'], sourceHash: 'sha256:abc');
		$this->assertStringNotContainsString('!important', $css);
	}//end testGeneratedCssNeverUsesImportant()

	/**
	 * Eligibility: `none` and `high-contrast` design systems are excluded;
	 * everything else is eligible.
	 */
	public function testEligibility(): void {
		$this->assertFalse($this->service->isEligible(designSystemId: 'none'));
		$this->assertFalse($this->service->isEligible(designSystemId: 'high-contrast'));
		$this->assertTrue($this->service->isEligible(designSystemId: 'nldesign'));
		$this->assertTrue($this->service->isEligible(designSystemId: 'lasuite'));
	}//end testEligibility()

	/**
	 * `generateForSet()` merges hand-authored dark-block overrides over the
	 * derived declarations — the override wins.
	 */
	public function testGenerateForSetHandAuthoredOverrideWins(): void {
		file_put_contents(
			$this->appDir . '/token-sets.json',
			json_encode([['id' => 'example', 'design_system' => 'nldesign']])
		);
		file_put_contents(
			$this->appDir . '/css/tokens/example.css',
			":root {\n\t--nldesign-color-primary: #154273;\n\t--nldesign-color-primary-text: #ffffff;\n}\n\n"
			. "@media (prefers-color-scheme: dark) {\n\t:root {\n\t\t--nldesign-color-primary: #4844AD;\n\t}\n}\n"
		);

		$generated = $this->service->generateForSet(setId: 'example');

		$this->assertNotNull($generated);
		$this->assertStringContainsString('--nldesign-color-primary: #4844AD;', $generated['css']);
	}//end testGenerateForSetHandAuthoredOverrideWins()

	/**
	 * The dark variant gives the error label a colour that reads on the
	 * derived error fill (thematiq#1027). error-contrast.css paints the error
	 * chip and button label from `--nldesign-component-button-error-color`,
	 * falling back to white; white on the lighter red a dark variant derives
	 * from #d70d0d (#e72e2e) is 4.35:1, and so is near-black #111111.
	 */
	public function testGenerateForSetGivesTheErrorLabelAReadableColour(): void {
		file_put_contents(
			$this->appDir . '/token-sets.json',
			json_encode([['id' => 'example', 'design_system' => 'nldesign']])
		);
		file_put_contents(
			$this->appDir . '/css/tokens/example.css',
			":root {\n\t--nldesign-color-background: #ffffff;\n\t--nldesign-color-error: #d70d0d;\n}\n"
		);

		$generated = $this->service->generateForSet(setId: 'example');

		$this->assertNotNull($generated);
		$fill = [];
		$label = [];
		$this->assertSame(1, preg_match('/--nldesign-color-error: (#[0-9a-f]{6});/', $generated['css'], $fill));
		$this->assertSame(1, preg_match('/--nldesign-component-button-error-color: (#[0-9a-f]{6});/', $generated['css'], $label), 'no error label in the dark variant');
		$this->assertGreaterThanOrEqual(4.5, $this->contrast->measure(foreground: $label[1], background: $fill[1]));
	}//end testGenerateForSetGivesTheErrorLabelAReadableColour()

	/**
	 * A label the set chooses itself is kept when it reads, never replaced.
	 */
	public function testGenerateForSetKeepsAReadableHandAuthoredErrorLabel(): void {
		file_put_contents(
			$this->appDir . '/token-sets.json',
			json_encode([['id' => 'example', 'design_system' => 'nldesign']])
		);
		file_put_contents(
			$this->appDir . '/css/tokens/example.css',
			":root {\n\t--nldesign-color-error: #d70d0d;\n}\n\n"
			. "@media (prefers-color-scheme: dark) {\n\t:root {\n\t\t--nldesign-color-error: #ff9999;\n\t\t--nldesign-component-button-error-color: #1a0000;\n\t}\n}\n"
		);

		$generated = $this->service->generateForSet(setId: 'example');

		$this->assertNotNull($generated);
		$this->assertStringContainsString('--nldesign-component-button-error-color: #1a0000;', $generated['css']);
	}//end testGenerateForSetKeepsAReadableHandAuthoredErrorLabel()

	/**
	 * `generateForSet()` returns null for an ineligible design system
	 * (`none`) — no dark output is built at all.
	 */
	public function testGenerateForSetSkipsIneligibleDesignSystem(): void {
		file_put_contents(
			$this->appDir . '/token-sets.json',
			json_encode([['id' => 'stock', 'design_system' => 'none']])
		);
		file_put_contents($this->appDir . '/css/tokens/stock.css', ":root {\n\t--nldesign-color-primary: #0082c9;\n}\n");

		$this->assertNull($this->service->generateForSet(setId: 'stock'));
	}//end testGenerateForSetSkipsIneligibleDesignSystem()

	/**
	 * `generateAndWrite()` writes the file, and a second call without
	 * `--force` is a no-op (fresh-file skip) — idempotence.
	 */
	public function testGenerateAndWriteIsIdempotentWithoutForce(): void {
		file_put_contents(
			$this->appDir . '/token-sets.json',
			json_encode([['id' => 'idem', 'design_system' => 'nldesign']])
		);
		file_put_contents($this->appDir . '/css/tokens/idem.css', ":root {\n\t--nldesign-color-primary: #154273;\n}\n");

		$first = $this->service->generateAndWrite(setId: 'idem');
		$this->assertTrue($first['written']);

		$darkFile = $this->appDir . '/css/tokens/dark/idem.css';
		$firstWritten = file_get_contents($darkFile);

		$second = $this->service->generateAndWrite(setId: 'idem');
		$this->assertFalse($second['written']);
		$this->assertTrue($second['skipped']);
		$this->assertSame('fresh', $second['reason']);
		$this->assertSame($firstWritten, file_get_contents($darkFile));
	}//end testGenerateAndWriteIsIdempotentWithoutForce()

	/**
	 * `--force` rewrites despite a fresh hash.
	 */
	public function testGenerateAndWriteForceRewrites(): void {
		file_put_contents(
			$this->appDir . '/token-sets.json',
			json_encode([['id' => 'idem', 'design_system' => 'nldesign']])
		);
		file_put_contents($this->appDir . '/css/tokens/idem.css', ":root {\n\t--nldesign-color-primary: #154273;\n}\n");

		$this->service->generateAndWrite(setId: 'idem');
		$result = $this->service->generateAndWrite(setId: 'idem', force: true);

		$this->assertTrue($result['written']);
	}//end testGenerateAndWriteForceRewrites()

	/**
	 * `discoverAllSetIds()` finds every `.css` file directly under
	 * `css/tokens/` (not the `dark/` subdirectory itself).
	 */
	public function testDiscoverAllSetIds(): void {
		file_put_contents($this->appDir . '/css/tokens/one.css', ':root { --nldesign-color-primary: #000; }');
		file_put_contents($this->appDir . '/css/tokens/two.css', ':root { --nldesign-color-primary: #000; }');
		mkdir($this->appDir . '/css/tokens/dark', 0777, true);

		$ids = $this->service->discoverAllSetIds();

		$this->assertContains('one', $ids);
		$this->assertContains('two', $ids);
		$this->assertNotContains('dark', $ids);
	}//end testDiscoverAllSetIds()

	/**
	 * Deleting a custom set's dark variant removes the file (best-effort, no
	 * exception when it never existed).
	 */
	public function testDeleteDarkVariant(): void {
		mkdir($this->appDir . '/css/tokens/dark', 0777, true);
		file_put_contents($this->appDir . '/css/tokens/dark/custom-x.css', '/* generated */');

		$this->service->deleteDarkVariant(setId: 'custom-x');
		$this->assertFileDoesNotExist($this->appDir . '/css/tokens/dark/custom-x.css');

		// Deleting again (already gone) must not throw.
		$this->service->deleteDarkVariant(setId: 'custom-x');
		$this->addToAssertionCount(1);
	}//end testDeleteDarkVariant()

	/**
	 * `logo_dark` theming metadata is emitted as a dark-scoped
	 * `--nldesign-logo-url` override, relative to `css/tokens/dark/`.
	 */
	public function testLogoDarkEmitsRelativeLogoUrlOverride(): void {
		file_put_contents(
			$this->appDir . '/token-sets.json',
			json_encode(
				[
					[
						'id' => 'withlogo',
						'design_system' => 'nldesign',
						'theming' => ['logo_dark' => 'img/logos/withlogo-dark.svg'],
					],
				]
			)
		);
		file_put_contents($this->appDir . '/css/tokens/withlogo.css', ":root {\n\t--nldesign-color-primary: #154273;\n}\n");

		$generated = $this->service->generateForSet(setId: 'withlogo');

		$this->assertNotNull($generated);
		$this->assertStringContainsString(
			"--nldesign-logo-url: url('../../../img/logos/withlogo-dark.svg');",
			$generated['css']
		);
	}//end testLogoDarkEmitsRelativeLogoUrlOverride()

	/**
	 * A band that is already dark in light mode is left out of the dark
	 * variant, surface and text together, so the light values keep applying
	 * (thematiq#1021). Inverted, La Frankendesk's #1b1b23 footer became
	 * #cbcbd6 under a #9e9e9e label.
	 */
	public function testAnAlreadyDarkFooterKeepsItsLightValues(): void {
		$derived = $this->service->deriveDarkDeclarations(
			[
				'--nldesign-color-background' => '#ffffff',
				'--nldesign-color-footer-background' => '#1b1b23',
				'--nldesign-color-footer-text' => '#ffffff',
				'--frankendesk-footer-link-color' => '#c5c6d5',
				'--frankendesk-footer-brand-color' => 'rgba(255, 255, 255, 0.75)',
			]
		);

		$this->assertArrayHasKey('--nldesign-color-background', $derived);
		foreach (['--nldesign-color-footer-background', '--nldesign-color-footer-text', '--frankendesk-footer-link-color', '--frankendesk-footer-brand-color'] as $token) {
			$this->assertArrayNotHasKey($token, $derived, $token . ' belongs to a dark footer and must keep its light value');
		}
	}//end testAnAlreadyDarkFooterKeepsItsLightValues()

	/**
	 * A light footer still derives dark, and an alias to a dark literal
	 * counts as dark (conduction-new's `var(--c-cobalt-900)`).
	 */
	public function testOnlyAnAlreadyDarkFooterIsLeftAlone(): void {
		$light = $this->service->deriveDarkDeclarations(
			[
				'--nldesign-color-footer-background' => '#f3f3f3',
				'--nldesign-color-footer-text' => '#333333',
			]
		);
		$this->assertArrayHasKey('--nldesign-color-footer-background', $light);
		$this->assertArrayHasKey('--nldesign-color-footer-text', $light);

		$aliased = $this->service->deriveDarkDeclarations(
			[
				'--c-cobalt-900' => '#0a172f',
				'--nldesign-color-footer-background' => 'var(--c-cobalt-900)',
				'--nldesign-color-footer-text' => '#ffffff',
			]
		);
		$this->assertArrayNotHasKey('--nldesign-color-footer-background', $aliased);
		$this->assertArrayNotHasKey('--nldesign-color-footer-text', $aliased);
	}//end testOnlyAnAlreadyDarkFooterIsLeftAlone()

	/**
	 * The hero band's foregrounds are repaired against the primary fill they
	 * sit on, a light footer's text against its derived surface, and the
	 * semantic link colour and its hover against the page.
	 */
	public function testTheRepairLoopFixesTheHeroAndFooterForegrounds(): void {
		$result = $this->service->verifyAndRepair(
			[
				'--nldesign-color-background' => '#141414',
				'--nldesign-color-primary' => '#9c99d7',
				'--nldesign-color-primary-text' => '#111111',
				'--nldesign-hero-title-color' => '#9e9e9e',
				'--nldesign-hero-body-color' => '#6984d3',
				'--nldesign-color-footer-background' => '#cbcbd6',
				'--nldesign-color-footer-text' => '#9e9e9e',
				'--nldesign-color-link' => '#5956b5',
				'--nldesign-color-link-hover' => '#460ce1',
			]
		);

		$this->assertSame([], $result['warnings']);
		$d = $result['declarations'];
		foreach ([
			['--nldesign-hero-title-color', '--nldesign-color-primary'],
			['--nldesign-hero-body-color', '--nldesign-color-primary'],
			['--nldesign-color-footer-text', '--nldesign-color-footer-background'],
			['--nldesign-color-link', '--nldesign-color-background'],
			['--nldesign-color-link-hover', '--nldesign-color-background'],
		] as [$fg, $bg]) {
			$this->assertGreaterThanOrEqual(4.5, $this->contrast->measure(foreground: $d[$fg], background: $d[$bg]), $fg . ' on ' . $bg);
		}
	}//end testTheRepairLoopFixesTheHeroAndFooterForegrounds()

	/**
	 * La Frankendesk's own bands read in its generated dark variant: the
	 * footer foregrounds on the footer, the hero foregrounds on the primary
	 * fill, its links on the page (thematiq#1021). Measured against the real tree, with the light
	 * declarations underneath for every token the variant leaves out.
	 *
	 * @spec openspec/specs/frankendesk-token-set/spec.md
	 */
	public function testFrankendeskBandsReadInItsGeneratedDarkVariant(): void {
		$realAppRoot = dirname(__DIR__, 3);
		$service = $this->makeService(appDir: $realAppRoot);
		$generated = $service->generateForSet(setId: 'frankendesk');
		$this->assertNotNull($generated);

		$parser = new CssParserService();
		$light = ($parser->parseDeclarations(content: (string)file_get_contents($realAppRoot . '/css/tokens/frankendesk.css')) ?? []);
		$this->assertSame(1, preg_match('/body\[data-theme-dark\],\s*body\[data-themes\*=dark\]\s*\{([^}]*)\}/', $generated['css'], $block));
		$dark = array_merge($light, ($parser->parseDeclarations(content: $block[1]) ?? []));

		$pairs = [
			['--nldesign-color-footer-text', '--nldesign-color-footer-background'],
			['--frankendesk-footer-wordmark-color', '--nldesign-color-footer-background'],
			['--frankendesk-footer-brand-color', '--nldesign-color-footer-background'],
			['--frankendesk-footer-heading-color', '--nldesign-color-footer-background'],
			['--frankendesk-footer-link-color', '--nldesign-color-footer-background'],
			['--frankendesk-footer-legal-color', '--nldesign-color-footer-background'],
			['--frankendesk-footer-legal-link-color', '--nldesign-color-footer-background'],
			['--nldesign-hero-title-color', '--nldesign-color-primary'],
			['--nldesign-hero-body-color', '--nldesign-color-primary'],
			['--nldesign-color-link', '--nldesign-color-background'],
			['--nldesign-color-link-hover', '--nldesign-color-background'],
		];
		foreach ($pairs as [$fg, $bg]) {
			$ratio = $this->contrast->measure(foreground: $dark[$fg], background: $dark[$bg]);
			$this->assertNotNull($ratio, $fg);
			$this->assertGreaterThanOrEqual(4.5, $ratio, $fg . ' ' . $dark[$fg] . ' on ' . $bg . ' ' . $dark[$bg]);
		}
	}//end testFrankendeskBandsReadInItsGeneratedDarkVariant()

	/**
	 * Real shipped-set integration: `rijkshuisstijl` and `amsterdam`'s
	 * generated dark declarations pass every evaluable ContrastService pair
	 * at its AA threshold — the verification-loop contract, exercised
	 * against the actual worktree files (not synthetic fixtures).
	 *
	 * @dataProvider shippedSetProvider
	 *
	 * @param string $setId The shipped token set id.
	 */
	public function testRealShippedSetsPassContrastAfterGeneration(string $setId): void {
		$realAppRoot = dirname(__DIR__, 3);
		if (is_file($realAppRoot . '/css/tokens/' . $setId . '.css') === false) {
			$this->markTestSkipped('Real app tree not available at ' . $realAppRoot);
		}

		$service = $this->makeService(appDir: $realAppRoot);
		$generated = $service->generateForSet(setId: $setId);

		$this->assertNotNull($generated);

		foreach ($generated['warnings'] as $warning) {
			$this->assertTrue(
				($warning['unevaluated'] ?? false) === true,
				'Unexpected non-unevaluated contrast warning for ' . $setId . ': ' . $warning['pair'] . ' = ' . ($warning['ratio'] ?? 'n/a')
			);
		}
	}//end testRealShippedSetsPassContrastAfterGeneration()

	/**
	 * Data provider for {@see self::testRealShippedSetsPassContrastAfterGeneration()}.
	 *
	 * @return array<string, array{0: string}> The shipped set ids to exercise.
	 */
	public static function shippedSetProvider(): array {
		return [
			'rijkshuisstijl' => ['rijkshuisstijl'],
			'amsterdam' => ['amsterdam'],
			'frankendesk' => ['frankendesk'],
			'conduction-new' => ['conduction-new'],
		];
	}//end shippedSetProvider()
}//end class
