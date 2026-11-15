<?php

/**
 * La Suite numérique design stack — manifest resolution, contrast and
 * license-compliance regression test.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Test
 * @package   OCA\Thematiq
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 *
 * @spec openspec/specs/css-architecture/spec.md
 * @spec openspec/specs/token-sets/spec.md
 * @spec openspec/specs/lasuite-parity/spec.md
 * @spec openspec/specs/marianne-font/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit;

use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\DesignSystemService;
use OCA\Thematiq\Service\ShippedTokenSetAuditService;
use OCA\Thematiq\Service\TokenSetAudits;
use OCA\Thematiq\Service\TokenSetFiles;
use OCA\Thematiq\Service\TokenSetRuntime;
use OCA\Thematiq\Service\TokenSetService;
use OCA\Thematiq\Service\TokenSetVocabularyAuditService;
use OCP\App\IAppManager;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Static + service-level regression test for the `lasuite` design system and
 * token set: manifest resolution in declared order, WCAG contrast, and the
 * license-compliance invariants (no Marianne bytes, no bundled logo).
 */
class LasuiteDesignStackTest extends TestCase {

	/**
	 * Repository root, derived from this test file's location.
	 *
	 * @return string
	 */
	private function repoRoot(): string {
		return \dirname(__DIR__, 2);
	}

	/**
	 * Build a DesignSystemService reading the real repository manifests.
	 *
	 * @return DesignSystemService
	 */
	private function designSystemService(): DesignSystemService {
		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('getAppPath')->willReturn($this->repoRoot());

		$config = $this->createMock(originalClassName: IConfig::class);
		$config->method('getAppValue')->willReturn('');

		return new DesignSystemService(appManager: $appManager, config: $config);
	}//end designSystemService()

	/**
	 * Build a TokenSetService reading the real repository manifests (no
	 * custom-set appconfig entries).
	 *
	 * @return TokenSetService
	 */
	private function tokenSetService(): TokenSetService {
		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('getAppPath')->willReturn($this->repoRoot());

		$config = $this->createMock(originalClassName: IConfig::class);
		$config->method('getAppValue')->willReturn('{}');

		$audit = new ShippedTokenSetAuditService(contrast: new ContrastService(), parser: new CssParserService());
		$cacheFactory = $this->createMock(originalClassName: ICacheFactory::class);
		$cacheFactory->method('createDistributed')->willReturn($this->createMock(originalClassName: ICache::class));

		return new TokenSetService(
			setFiles: new TokenSetFiles(
				appManager: $appManager
			),
			runtime: new TokenSetRuntime(
				config: $config,
				cacheFactory: $cacheFactory,
				logger: $this->createMock(originalClassName: LoggerInterface::class)
			),
			audits: new TokenSetAudits(
				audit: $audit,
				vocabularyAudit: new TokenSetVocabularyAuditService(parser: new CssParserService())
			)
		);
	}//end tokenSetService()

	/**
	 * `DesignSystemService::getDesignSystem('lasuite')` resolves the five
	 * stylesheets in the exact declared order (fonts → defaults →
	 * brand-override → bridge → element-overrides).
	 *
	 * @spec openspec/specs/css-architecture/spec.md
	 * @spec openspec/specs/lasuite-parity/spec.md
	 *
	 * @return void
	 */
	public function testLasuiteDesignSystemResolvesFiveStylesheetsInOrder(): void {
		$system = $this->designSystemService()->getDesignSystem('lasuite');

		$this->assertSame(expected: 'lasuite', actual: $system['id']);
		$this->assertSame(
			expected: [
				'systems/lasuite/fonts',
				'systems/lasuite/defaults',
				'systems/lasuite/brand-override',
				'systems/lasuite/bridge',
				'systems/lasuite/element-overrides',
			],
			actual: $system['stylesheets'],
			message: 'The lasuite bundle must declare exactly these five stylesheets, in this order.'
		);
	}//end testLasuiteDesignSystemResolvesFiveStylesheetsInOrder()

	/**
	 * `DesignSystemService::getDesignSystem('cunningham')` resolves the same
	 * shared fonts/defaults/bridge/element-overrides files as lasuite, but
	 * WITHOUT brand-override — so it stays on the published Cunningham blue
	 * base instead of the deployed violet.
	 *
	 * @spec openspec/specs/css-architecture/spec.md
	 * @spec openspec/specs/lasuite-parity/spec.md
	 *
	 * @return void
	 */
	public function testCunninghamDesignSystemResolvesFourStylesheetsWithoutBrandOverride(): void {
		$system = $this->designSystemService()->getDesignSystem('cunningham');

		$this->assertSame(expected: 'cunningham', actual: $system['id']);
		$this->assertSame(
			expected: [
				'systems/lasuite/fonts',
				'systems/lasuite/defaults',
				'systems/lasuite/bridge',
				'systems/lasuite/element-overrides',
			],
			actual: $system['stylesheets'],
			message: 'The cunningham bundle must reuse the shared lasuite files, minus brand-override.'
		);
		$this->assertNotContains(
			needle: 'systems/lasuite/brand-override',
			haystack: $system['stylesheets'],
			message: 'The cunningham bundle must never load the violet brand-override layer.'
		);
	}//end testCunninghamDesignSystemResolvesFourStylesheetsWithoutBrandOverride()

	/**
	 * Every stylesheet declared in the lasuite bundle has a backing CSS file
	 * under css/systems/lasuite/, and the font binaries directory exists.
	 *
	 * @spec openspec/specs/css-architecture/spec.md
	 *
	 * @return void
	 */
	public function testDeclaredStylesheetsHaveBackingFiles(): void {
		$system = $this->designSystemService()->getDesignSystem('lasuite');

		foreach ($system['stylesheets'] as $stylesheet) {
			$path = $this->repoRoot() . '/css/' . $stylesheet . '.css';
			$this->assertFileExists(filename: $path, message: "Declared stylesheet '{$stylesheet}' has no backing CSS file.");
		}

		$this->assertDirectoryExists(
			directory: $this->repoRoot() . '/css/systems/lasuite/fonts',
			message: 'The lasuite font binaries directory must exist.'
		);
		$this->assertFileExists(
			filename: $this->repoRoot() . '/css/tokens/lasuite.css',
			message: 'The lasuite Layer-3 token file must exist.'
		);
	}//end testDeclaredStylesheetsHaveBackingFiles()

	/**
	 * Every stylesheet declared in the cunningham bundle has a backing CSS
	 * file, and the cunningham Layer-3 token file exists.
	 *
	 * @spec openspec/specs/css-architecture/spec.md
	 * @spec openspec/specs/token-sets/spec.md
	 *
	 * @return void
	 */
	public function testCunninghamDeclaredStylesheetsHaveBackingFiles(): void {
		$system = $this->designSystemService()->getDesignSystem('cunningham');

		foreach ($system['stylesheets'] as $stylesheet) {
			$path = $this->repoRoot() . '/css/' . $stylesheet . '.css';
			$this->assertFileExists(filename: $path, message: "Declared stylesheet '{$stylesheet}' has no backing CSS file.");
		}

		$this->assertFileExists(
			filename: $this->repoRoot() . '/css/tokens/cunningham.css',
			message: 'The cunningham Layer-3 token file must exist.'
		);
	}//end testCunninghamDeclaredStylesheetsHaveBackingFiles()

	/**
	 * `token-sets.json`'s `cunningham` entry declares `design_system:
	 * "cunningham"` and the published blue base, distinct from lasuite's
	 * deployed violet. The swatch is brand-650 (#1A509F), not brand-600
	 * (#0659C5): the shared bridge.css/element-overrides.css (reused as-is
	 * from the lasuite bundle) derive `--color-primary` from
	 * `--lasuite-color-brand-650` specifically — the same step that
	 * resolves to lasuite's deployed violet #4844AD — so the swatch must
	 * match what actually renders, not Cunningham's own "-600" named step.
	 *
	 * @spec openspec/specs/token-sets/spec.md
	 * @spec openspec/specs/lasuite-parity/spec.md
	 *
	 * @return void
	 */
	public function testTokenSetMetaDeclaresCunninghamDesignSystem(): void {
		$meta = $this->designSystemService()->getTokenSetMeta('cunningham');

		$this->assertSame(expected: 'cunningham', actual: $meta['design_system'] ?? null);
		$this->assertSame(expected: '#1A509F', actual: $meta['theming']['primary_color'] ?? null);
		// Matches the background cunningham.css actually paints
		// (--nldesign-color-background-dark), corrected in 585ceb7.
		$this->assertSame(expected: '#E1E2E5', actual: $meta['theming']['background_color'] ?? null);
		$this->assertArrayNotHasKey(
			key: 'logo',
			array: $meta['theming'] ?? [],
			message: 'The cunningham theming block must not carry a logo key.'
		);
	}//end testTokenSetMetaDeclaresCunninghamDesignSystem()

	/**
	 * The generated defaults.css defines the published Cunningham BLUE base
	 * (brand-600) — the violet deployment values live only in
	 * brand-override.css, never here.
	 *
	 * @spec openspec/specs/lasuite-parity/spec.md
	 *
	 * @return void
	 */
	public function testGeneratedDefaultsDefineTheBlueBaseNotTheViolet(): void {
		$defaults = (string)file_get_contents($this->repoRoot() . '/css/systems/lasuite/defaults.css');

		$this->assertStringContainsString(
			needle: '--lasuite--globals--colors--brand-600: #0659c5;',
			haystack: $defaults,
			message: 'defaults.css must define the published Cunningham blue brand-600.'
		);
		// Checked as an active DECLARATION VALUE (": #hex;"), not a blanket
		// substring — the file's own provenance header legitimately mentions
		// these violet hex values in PROSE (documenting what brand-override.css
		// changes), which must not trip this guard.
		$this->assertDoesNotMatchRegularExpression(
			pattern: '/:\s*#534fc2\s*;/i',
			string: $defaults,
			message: 'defaults.css must never hard-code the deployed violet brand-600 (#534fc2) as a declaration value — that belongs in brand-override.css.'
		);
		$this->assertDoesNotMatchRegularExpression(
			pattern: '/:\s*#4844ad\s*;/i',
			string: $defaults,
			message: 'defaults.css must never hard-code the deployed violet brand-650/logo (#4844ad) as a declaration value — that belongs in brand-override.css.'
		);
	}//end testGeneratedDefaultsDefineTheBlueBaseNotTheViolet()

	/**
	 * The generated defaults.css carries a provenance header naming the
	 * source package, its version, the token count and the mapping rule.
	 *
	 * @spec openspec/specs/lasuite-parity/spec.md
	 *
	 * @return void
	 */
	public function testGeneratedDefaultsCarryProvenanceHeader(): void {
		$defaults = (string)file_get_contents($this->repoRoot() . '/css/systems/lasuite/defaults.css');

		$this->assertStringContainsString(needle: '@openfun/cunningham-tokens@', haystack: $defaults);
		$this->assertStringContainsString(needle: 'MIT licence', haystack: $defaults);
		$this->assertStringContainsString(needle: 'Token count: 1167', haystack: $defaults);
		$this->assertStringContainsString(needle: '--c--<rest>', haystack: $defaults);
		$this->assertStringContainsString(needle: '--lasuite--<rest>', haystack: $defaults);
	}//end testGeneratedDefaultsCarryProvenanceHeader()

	/**
	 * `brand-override.css` is GENERATED (scripts/generate-lasuite-tokens.mjs
	 * --override) as the colour delta between La Suite's deployed VIOLET
	 * Cunningham build and the published BLUE npm base; it carries a
	 * provenance header naming the vendored deployed source, and loading it
	 * after defaults.css resolves the brand scale, the violet-tinted neutrals,
	 * the vivid semantic palettes and the logo tokens to the deployed violet.
	 *
	 * @spec openspec/specs/lasuite-parity/spec.md
	 *
	 * @return void
	 */
	public function testBrandOverrideIsGeneratedAndResolvesViolet(): void {
		$override = (string)file_get_contents($this->repoRoot() . '/css/systems/lasuite/brand-override.css');

		// Provenance: generated from the vendored deployed source, not hand-authored.
		$this->assertStringContainsString(needle: 'GENERATED', haystack: $override);
		$this->assertStringContainsString(needle: 'suitenumerique/docs', haystack: $override);
		$this->assertStringContainsString(needle: '--override', haystack: $override);
		// Brand ramp — the deployed violet.
		$this->assertStringContainsString(
			needle: '--lasuite--globals--colors--brand-600: #534fc2;',
			haystack: $override,
			message: 'brand-override.css must redeclare brand-600 to the deployed violet.'
		);
		$this->assertStringContainsString(
			needle: '--lasuite--globals--colors--brand-650: #4844ad;',
			haystack: $override,
			message: 'brand-override.css must redeclare brand-650 (also the logo colour) to the deployed violet.'
		);
		// Violet-tinted neutrals — the delta now goes beyond the brand ramp.
		$this->assertStringContainsString(
			needle: '--lasuite--globals--colors--gray-300: #a9a9bf;',
			haystack: $override,
			message: 'brand-override.css must redeclare the gray ramp to the deployed violet-tinted neutrals.'
		);
		// La Suite's vivid semantic palette (distinct hue, not a tint).
		$this->assertStringContainsString(
			needle: '--lasuite--globals--colors--success-500: #1e884a;',
			haystack: $override,
			message: "brand-override.css must redeclare the semantic palettes to La Suite's deployed values."
		);
	}//end testBrandOverrideIsGeneratedAndResolvesViolet()

	/**
	 * The lasuite bridge accounts for every one of the 68 audited Nextcloud
	 * `--color-*` variables (the `nextcloud-variable-mapping` canonical
	 * surface, the same set css/systems/nldesign/overrides.css covers) as
	 * either an active mapping or a commented, reasoned line. Mirrors
	 * `tests/css/check-lasuite-bridge-coverage.js` (the Node drift-style
	 * check `npm run test:lasuite-bridge-coverage` runs) as a PHPUnit
	 * regression so the same invariant is enforced from both toolchains.
	 *
	 * @spec openspec/specs/lasuite-parity/spec.md
	 *
	 * @return void
	 */
	public function testBridgeAccountsForEveryAuditedColorVariable(): void {
		$overrides = (string)file_get_contents($this->repoRoot() . '/css/systems/nldesign/overrides.css');
		$bridge = (string)file_get_contents($this->repoRoot() . '/css/systems/lasuite/bridge.css');

		preg_match_all('/^\s*(--color-[a-zA-Z0-9-]+)\s*:/m', $overrides, $mappedMatches);
		preg_match_all('/\/\*\s*(--color-[a-zA-Z0-9-]+)\s*:/', $overrides, $commentedMatches);
		$audited = array_unique(array_merge($mappedMatches[1], $commentedMatches[1]));

		$this->assertGreaterThanOrEqual(
			expected: 68,
			actual: \count($audited),
			message: 'The audited --color-* surface in overrides.css must be at least 68 variables.'
		);

		preg_match_all('/^\s*(--color-[a-zA-Z0-9-]+)\s*:/m', $bridge, $bridgeMapped);
		preg_match_all('/\/\*\s*(--color-[a-zA-Z0-9-]+)\s*:/', $bridge, $bridgeCommented);
		$bridgeCovered = array_unique(array_merge($bridgeMapped[1], $bridgeCommented[1]));

		$missing = array_values(array_diff($audited, $bridgeCovered));

		$this->assertSame(
			expected: [],
			actual: $missing,
			message: 'Every audited --color-* variable must appear in bridge.css as a mapping or a reasoned comment. Missing: ' . implode(
				', ',
				$missing
			)
		);
	}//end testBridgeAccountsForEveryAuditedColorVariable()

	/**
	 * The shared bridge.css derives `--color-primary` (and every brand-accent
	 * rule in element-overrides.css) from `--lasuite-color-brand-650`
	 * specifically. This is the load-bearing fact behind the brand-650 (not
	 * brand-600) swatch choice for the cunningham sibling — pinned as a
	 * regression guard so a future edit that switches the mapping to a
	 * different scale step does not silently strand token-sets.json's
	 * cunningham/lasuite swatches out of sync with what actually renders.
	 *
	 * @spec openspec/specs/lasuite-parity/spec.md
	 *
	 * @return void
	 */
	public function testBridgeDerivesColorPrimaryFromBrand650(): void {
		$bridge = (string)file_get_contents($this->repoRoot() . '/css/systems/lasuite/bridge.css');

		$this->assertMatchesRegularExpression(
			pattern: '/--color-primary:\s*var\(--lasuite-color-brand-650\)\s*!important;/',
			string: $bridge,
			message: 'bridge.css must derive --color-primary from --lasuite-color-brand-650 (both lasuite and cunningham share this file).'
		);
	}//end testBridgeDerivesColorPrimaryFromBrand650()

	/**
	 * `token-sets.json`'s `lasuite` entry declares `design_system: "lasuite"`
	 * and `DesignSystemService::getTokenSetMeta()` surfaces it unchanged.
	 *
	 * @spec openspec/specs/token-sets/spec.md
	 *
	 * @return void
	 */
	public function testTokenSetMetaDeclaresLasuiteDesignSystem(): void {
		$meta = $this->designSystemService()->getTokenSetMeta('lasuite');

		$this->assertSame(expected: 'lasuite', actual: $meta['design_system'] ?? null);
		$this->assertSame(expected: '#4844AD', actual: $meta['theming']['primary_color'] ?? null);
		$this->assertSame(expected: '#FFFFFF', actual: $meta['theming']['background_color'] ?? null);
	}//end testTokenSetMetaDeclaresLasuiteDesignSystem()

	/**
	 * `TokenSetService::getAvailableTokenSets()` surfaces the `lasuite` entry
	 * with its design system and theming metadata (manifest/CSS-file pairing
	 * intact — the entry only appears because css/tokens/lasuite.css exists).
	 *
	 * @spec openspec/specs/token-sets/spec.md
	 *
	 * @return void
	 */
	public function testTokenSetServiceSurfacesLasuiteEntry(): void {
		$sets = $this->tokenSetService()->getAvailableTokenSets();
		$byId = array_column($sets, null, 'id');

		$this->assertArrayHasKey(key: 'lasuite', array: $byId, message: 'The lasuite token set must be discovered.');
		$this->assertSame(expected: 'lasuite', actual: $byId['lasuite']['design_system']);
		$this->assertSame(expected: '#4844AD', actual: $byId['lasuite']['theming']['primary_color']);
		$this->assertSame(expected: '#FFFFFF', actual: $byId['lasuite']['theming']['background_color']);
		$this->assertArrayNotHasKey(
			key: 'logo',
			array: $byId['lasuite']['theming'],
			message: 'The lasuite theming block must not carry a logo key (no state logos bundled).'
		);
	}//end testTokenSetServiceSurfacesLasuiteEntry()

	/**
	 * An unknown design system id still falls back safely (existing
	 * behaviour) — regression guard that adding `lasuite` did not disturb it.
	 *
	 * @spec openspec/specs/css-architecture/spec.md
	 *
	 * @return void
	 */
	public function testUnknownDesignSystemStillFallsBackSafely(): void {
		$system = $this->designSystemService()->getDesignSystem('__does_not_exist__');

		$this->assertSame(expected: [], actual: $system['stylesheets']);
	}//end testUnknownDesignSystemStillFallsBackSafely()

	/**
	 * The La Suite brand colour (`--lasuite-color-brand-650` / `#4844AD`)
	 * against white text clears WCAG AA (>= 4.5:1) with wide margin, per the
	 * "Brand palette matches Cunningham" scenario.
	 *
	 * @spec openspec/specs/css-architecture/spec.md
	 *
	 * @return void
	 */
	public function testBrandPrimaryTextContrastMeetsAa(): void {
		$contrast = new ContrastService();

		$brand = $contrast->parseColor('#4844AD');
		$white = $contrast->parseColor('#FFFFFF');
		$this->assertNotNull(actual: $brand);
		$this->assertNotNull(actual: $white);

		$ratio = $contrast->ratio($brand, $white);

		$this->assertGreaterThanOrEqual(
			expected: 4.5,
			actual: $ratio,
			message: sprintf('brand-650 (#4844AD) vs white must be >= 4.5:1 for WCAG AA text; got %.2f:1.', $ratio)
		);
	}//end testBrandPrimaryTextContrastMeetsAa()

	/**
	 * The interactive status-fill steps the bridge actually maps onto
	 * `--nldesign-color-{error,warning,success,info}` (Cunningham's own
	 * "-550" background token, not the raw "-500" swatch) carry white text
	 * at WCAG AA. Guards the deliberate -550-over-500 choice documented in
	 * css/systems/lasuite/bridge.css — for BOTH design systems: the
	 * `cunningham` set resolves the blue npm base's "-550" step
	 * (defaults.css), while the `lasuite` set resolves La Suite's DEPLOYED
	 * semantic palette (brand-override.css), which repaints these fills to
	 * distinct, more vivid hues. Both must stay legible with white text.
	 *
	 * @spec openspec/specs/css-architecture/spec.md
	 * @spec openspec/specs/lasuite-parity/spec.md
	 *
	 * @return void
	 */
	public function testStatusFillsCarryWhiteTextAtAa(): void {
		$contrast = new ContrastService();
		$white = $contrast->parseColor('#FFFFFF');
		$this->assertNotNull(actual: $white);

		$fillSets = [
			// cunningham set — the blue npm base (defaults.css, no brand-override).
			'cunningham' => [
				'error-550' => '#D80000',
				'warning-550' => '#836703',
				'success-550' => '#427816',
				'info-550' => '#1167D4',
			],
			// lasuite set — La Suite's deployed semantic palette (brand-override.css).
			'lasuite' => [
				'error-550' => '#d7010e',
				'warning-550' => '#bc4200',
				'success-550' => '#027b3e',
				'info-550' => '#0069cf',
			],
		];

		foreach ($fillSets as $set => $fills) {
			foreach ($fills as $name => $hex) {
				$rgb = $contrast->parseColor($hex);
				$this->assertNotNull(actual: $rgb, message: "Could not parse {$set} {$name} ({$hex}).");

				$ratio = $contrast->ratio($rgb, $white);
				$this->assertGreaterThanOrEqual(
					expected: 4.5,
					actual: $ratio,
					message: sprintf('%s %s (%s) vs white must be >= 4.5:1 for WCAG AA text; got %.2f:1.', $set, $name, $hex, $ratio)
				);
			}
		}
	}//end testStatusFillsCarryWhiteTextAtAa()

	/**
	 * License compliance: css/systems/lasuite/fonts/ ships an OFL.txt licence
	 * file and the expected Inter woff2 weights.
	 *
	 * @spec openspec/specs/css-architecture/spec.md
	 *
	 * @return void
	 */
	public function testFontsDirectoryCarriesOflLicenceAndExpectedWeights(): void {
		$fontsDir = $this->repoRoot() . '/css/systems/lasuite/fonts';

		$this->assertFileExists(filename: $fontsDir . '/OFL.txt', message: 'css/systems/lasuite/fonts/ must contain an OFL.txt licence file.');

		$expected = [
			'inter-latin-400-normal.woff2',
			'inter-latin-400-italic.woff2',
			'inter-latin-500-normal.woff2',
			'inter-latin-600-normal.woff2',
			'inter-latin-700-normal.woff2',
			'inter-latin-700-italic.woff2',
		];
		foreach ($expected as $file) {
			$this->assertFileExists(filename: $fontsDir . '/' . $file, message: "Expected Inter font file missing: {$file}");
		}
	}//end testFontsDirectoryCarriesOflLicenceAndExpectedWeights()

	/**
	 * License compliance (UPDATED by marianne-font-restricted — Marianne MAY
	 * now be bundled, gated, per openspec/specs/marianne-font/spec.md; the
	 * previous blanket "no Marianne file anywhere" assertion is replaced by a
	 * scoped invariant): within the static-asset delivery surface (css/,
	 * img/, js/ — where an accidental extra bundle would actually reach the
	 * browser), a file matching the name Marianne may exist ONLY under the
	 * sanctioned `css/systems/lasuite/fonts/marianne/` directory or as the
	 * single gated stylesheet `css/systems/lasuite/marianne.css`. Governance
	 * docs (MARIANNE-LICENCE.md, AGREEMENT-MARIANNE.md), LICENSES/, l10n/
	 * notice strings, tests/, scripts/, and openspec/ legitimately reference
	 * "Marianne" by name and are out of scope for this asset-delivery check.
	 *
	 * @spec openspec/specs/marianne-font/spec.md
	 *
	 * @return void
	 */
	public function testMarianneAssetsAreScopedToTheGatedDirectoryAndStylesheet(): void {
		$root = $this->repoRoot();

		$sanctionedFontDir = $root . '/css/systems/lasuite/fonts/marianne';
		$sanctionedStylesheet = $root . '/css/systems/lasuite/marianne.css';

		$violations = [];
		foreach (['css', 'img', 'js'] as $dir) {
			$absDir = $root . '/' . $dir;
			if (is_dir($absDir) === false) {
				continue;
			}

			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator($absDir, \FilesystemIterator::SKIP_DOTS)
			);

			foreach ($iterator as $file) {
				/** @var \SplFileInfo $file */
				$path = $file->getPathname();

				if (preg_match('/marianne/i', $file->getFilename()) !== 1) {
					continue;
				}

				if (str_starts_with($path, $sanctionedFontDir . '/') === true || $path === $sanctionedStylesheet) {
					continue;
				}

				$violations[] = str_replace($root . '/', '', $path);
			}
		}

		$this->assertSame(
			expected: [],
			actual: $violations,
			message: 'A Marianne-named file was found outside the sanctioned gated location '
			. '(css/systems/lasuite/fonts/marianne/ or css/systems/lasuite/marianne.css): ' . implode(', ', $violations)
		);
	}//end testMarianneAssetsAreScopedToTheGatedDirectoryAndStylesheet()

	/**
	 * No CSS file other than the gated `css/systems/lasuite/marianne.css`
	 * `url()`-sources anything matching Marianne — so an accidental
	 * un-gated `@font-face` cannot slip into any other stylesheet.
	 *
	 * @spec openspec/specs/marianne-font/spec.md
	 *
	 * @return void
	 */
	public function testNoUrlSourceForMarianneOutsideTheGatedStylesheet(): void {
		$root = $this->repoRoot();
		$cssDir = $root . '/css';
		$sanctioned = $root . '/css/systems/lasuite/marianne.css';

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($cssDir, \FilesystemIterator::SKIP_DOTS)
		);

		foreach ($iterator as $file) {
			/** @var \SplFileInfo $file */
			if ($file->getExtension() !== 'css') {
				continue;
			}

			$path = $file->getPathname();
			if ($path === $sanctioned) {
				continue;
			}

			$contents = (string)file_get_contents($path);
			preg_match_all('/url\([^)]*\)/i', $contents, $matches);
			foreach ($matches[0] as $url) {
				$this->assertDoesNotMatchRegularExpression(
					pattern: '/marianne/i',
					string: $url,
					message: str_replace($root . '/', '', $path) . " must not url()-source Marianne outside the gated marianne.css: {$url}"
				);
			}
		}
	}//end testNoUrlSourceForMarianneOutsideTheGatedStylesheet()

	/**
	 * License compliance: the `lasuite` entry in `token-sets.json` carries no
	 * `logo` key (no La Suite / French-state logos are bundled).
	 *
	 * @spec openspec/specs/token-sets/spec.md
	 *
	 * @return void
	 */
	public function testTokenSetManifestEntryHasNoLogoKey(): void {
		$json = json_decode((string)file_get_contents($this->repoRoot() . '/token-sets.json'), true);
		$this->assertIsArray(actual: $json, message: 'token-sets.json must decode to an array.');

		$byId = array_column($json, null, 'id');
		$this->assertArrayHasKey(key: 'lasuite', array: $byId, message: 'token-sets.json must contain a lasuite entry.');
		$this->assertArrayNotHasKey(
			key: 'logo',
			array: $byId['lasuite']['theming'] ?? [],
			message: 'The lasuite theming block must not declare a logo key.'
		);
	}//end testTokenSetManifestEntryHasNoLogoKey()

	/**
	 * The bridge layer never sets the dark-mode-compatibility variables that
	 * REQ-CSS-007 reserves for Nextcloud's own auto-derivation.
	 *
	 * @spec openspec/specs/css-architecture/spec.md
	 *
	 * @return void
	 */
	public function testBridgeNeverSetsDarkModeCompatibilityVariables(): void {
		$bridge = (string)file_get_contents($this->repoRoot() . '/css/systems/lasuite/bridge.css');

		// Since the bridge-coverage completion (lasuite-parity), these six
		// variables are individually documented as commented, reasoned lines
		// (task 4.3) rather than only mentioned in the file's header prose —
		// so the check below must distinguish an ACTIVE declaration
		// (forbidden) from a commented one (required, and asserted present).
		foreach (
			[
				'--color-main-background',
				'--color-main-background-rgb',
				'--color-main-background-translucent',
				'--color-background-plain',
				'--background-invert-if-dark',
				'--background-invert-if-bright',
			] as $name
		) {
			$this->assertDoesNotMatchRegularExpression(
				pattern: '/^\s*' . preg_quote($name, '/') . '\s*:/m',
				string: $bridge,
				message: "bridge.css must not ACTIVELY set {$name} (REQ-CSS-007 dark-mode compatibility)."
			);
		}
	}//end testBridgeNeverSetsDarkModeCompatibilityVariables()

	/**
	 * The four dark-mode-compat `--color-*` variables that ARE part of the
	 * 68-variable audited surface (`--background-invert-if-*` are not
	 * `--color-*` prefixed, so they fall outside that surface) are present
	 * as commented, reasoned lines — not silently absent.
	 *
	 * @spec openspec/specs/lasuite-parity/spec.md
	 *
	 * @return void
	 */
	public function testBridgeDocumentsDarkModeCompatVariablesWithAReason(): void {
		$bridge = (string)file_get_contents($this->repoRoot() . '/css/systems/lasuite/bridge.css');

		foreach (
			[
				'--color-main-background',
				'--color-main-background-rgb',
				'--color-main-background-translucent',
				'--color-background-plain',
			] as $name
		) {
			$this->assertMatchesRegularExpression(
				pattern: '/\/\*\s*' . preg_quote($name, '/') . '\s*:/',
				string: $bridge,
				message: "bridge.css must document {$name} as a commented, reasoned line."
			);
		}
	}//end testBridgeDocumentsDarkModeCompatVariablesWithAReason()

	/**
	 * The fonts layer never declares a `url()` source for Marianne — only
	 * `local()` — so the browser can never fetch Marianne bytes from this app.
	 *
	 * @spec openspec/specs/css-architecture/spec.md
	 *
	 * @return void
	 */
	public function testFontsLayerNeverUrlSourcesMarianne(): void {
		$fonts = (string)file_get_contents($this->repoRoot() . '/css/systems/lasuite/fonts.css');

		$this->assertMatchesRegularExpression(
			pattern: '/--lasuite-font-family:\s*Marianne,\s*Inter,\s*sans-serif/',
			string: $fonts,
			message: 'fonts.css must define --lasuite-font-family with Marianne first, Inter fallback.'
		);

		// Marianne must never appear inside a url() source.
		preg_match_all('/url\([^)]*\)/i', $fonts, $urlMatches);
		foreach ($urlMatches[0] as $url) {
			$this->assertDoesNotMatchRegularExpression(
				pattern: '/marianne/i',
				string: $url,
				message: "fonts.css must not url()-source Marianne: {$url}"
			);
		}
	}//end testFontsLayerNeverUrlSourcesMarianne()

	/**
	 * REGRESSION GUARD: the element-overrides layer must never force
	 * `position` on `#header`.
	 *
	 * Nextcloud ships `#header` as `position: absolute` (out of flow, z-index
	 * 2000), which is exactly what lets `#content-vue`'s `margin-top: 50px`
	 * clear it — stock Nextcloud therefore has a 0px gap between the header and
	 * the app content. An earlier revision of this layer forced
	 * `position: relative`, putting the header back INTO flow so that margin
	 * stacked on top of it and produced ~54px of dead space that neither
	 * La Suite (measured 0px) nor stock Nextcloud (measured 0px) has.
	 *
	 * @spec openspec/specs/lasuite-stack/spec.md
	 *
	 * @return void
	 */
	public function testElementOverridesNeverForcesHeaderPosition(): void {
		$overrides = (string)file_get_contents($this->repoRoot() . '/css/systems/lasuite/element-overrides.css');

		// Isolate the #header rule block and assert it declares no `position`.
		$matched = preg_match('/^#header,\s*\n\s*header#header\s*\{(.*?)\}/ms', $overrides, $m);
		$this->assertSame(expected: 1, actual: $matched, message: 'Could not locate the #header rule block in element-overrides.css.');

		$this->assertDoesNotMatchRegularExpression(
			pattern: '/^\s*position\s*:/m',
			string: $m[1],
			message: 'element-overrides.css must not set `position` on #header — Nextcloud needs it to stay '
			. '`absolute` (out of flow) or a ~54px gap reappears between the header and app content.'
		);
	}//end testElementOverridesNeverForcesHeaderPosition()

	/**
	 * The La Suite canvas/card inversion: the shell carries the grey ground and
	 * the app content is the white card floated on it.
	 *
	 * Measured on the live La Suite Docs app (2026-07-27): `<main>` is
	 * #f8f8f9 (gray-025) and the content card is #ffffff with a 4px radius.
	 * Nextcloud paints app content white on white, so without this inversion a
	 * list needs a hairline to be legible at all.
	 *
	 * @spec openspec/specs/lasuite-stack/spec.md
	 *
	 * @return void
	 */
	public function testElementOverridesInvertsCanvasAndCard(): void {
		$overrides = (string)file_get_contents($this->repoRoot() . '/css/systems/lasuite/element-overrides.css');

		// The surfaces are read through the CONTEXTUAL tokens, with the raw ramp
		// step kept as the fallback. That indirection is what makes dark mode
		// possible at all: Cunningham's own dark theme does not invert the ramp
		// (`gray-000` is still #fff there) — it remaps the contextual surface
		// tokens. A rule that hardcodes `gray-025` cannot be reached by a dark
		// block, so asserting the literal here would lock out dark support.
		// The fallback is asserted too, so the grey/white intent still holds if
		// a contextual is ever missing.
		//
		// `\s*` sits after every `(` and before every `)` on purpose. The
		// assertion is about the DECLARATION, not its layout: prettier wraps a
		// long `var(a, var(b))` onto its own lines, and this test must not be
		// the reason a stylesheet cannot be formatted.
		$this->assertMatchesRegularExpression(
			pattern: '/#content-vue\.content[^{]*\{[^}]*background-color:\s*var\(\s*'
			. '--lasuite--contextuals--background--surface--tertiary,\s*'
			. 'var\(\s*--lasuite-color-gray-025\s*\)\s*\)/ms',
			string: $overrides,
			message: 'The shell (#content-vue.content) must carry the grey canvas via the '
			. 'contextual surface token, falling back to gray-025.'
		);

		$this->assertMatchesRegularExpression(
			pattern: '/#app-content-vue\s*\{[^}]*background:\s*var\(\s*'
			. '--lasuite--contextuals--background--surface--primary,\s*'
			. 'var\(\s*--lasuite-color-gray-000\s*\)\s*\)/ms',
			string: $overrides,
			message: 'The app content must be the card surface, falling back to gray-000.'
		);
	}//end testElementOverridesInvertsCanvasAndCard()
}//end class
