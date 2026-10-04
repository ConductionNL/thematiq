<?php

/**
 * NL Design Shipped Token-Set Contrast Audit Service.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Service
 * @package   OCA\Thematiq
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 *
 * @spec openspec/specs/token-set-contrast-audit/spec.md#requirement-automated-contrast-audit-over-all-shipped-token-sets
 * @spec openspec/specs/token-set-contrast-audit/spec.md#requirement-reproducible-contrast-report
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCA\Thematiq\Service\RuntimeFile\RuntimeFileStore;
use OCA\Thematiq\Service\RuntimeFile\SetFileReader;
use OCP\ICache;

/**
 * Audits the shipped token sets for WCAG contrast, reusing the existing
 * ContrastService WCAG relative-luminance math.
 *
 * The same "layer defaults.css under tokens/{id}.css" resolution used by the
 * runtime (TokenSetPreviewService) is applied here, so shipped-set auditing and
 * the runtime apply-dialog warning share one contrast contract. The service is
 * given an explicit app-root path (not an IAppManager) so both the runtime
 * (which passes the resolved app path) and the standalone PHPUnit inventory
 * gate (which passes the repository root) can call it without a Nextcloud
 * server.
 *
 * @spec openspec/specs/token-set-contrast-audit/spec.md#requirement-automated-contrast-audit-over-all-shipped-token-sets
 */
class ShippedTokenSetAuditService {

	/**
	 * WCAG AA text-contrast threshold (primary vs primary-text).
	 */
	public const AA_TEXT = 4.5;

	/**
	 * WCAG AA non-text/UI-contrast threshold (primary vs background).
	 */
	public const AA_UI = 3.0;

	/**
	 * WCAG AAA text-contrast threshold (used for high-contrast sets).
	 */
	public const AAA_TEXT = 7.0;

	/**
	 * WCAG AAA non-text/UI-contrast threshold (used for high-contrast sets).
	 */
	public const AAA_UI = 4.5;

	/**
	 * TTL (seconds) for a cached per-set WCAG level — matches
	 * `Capabilities::WCAG_CACHE_TTL` exactly so a set that is both the active
	 * theme and a catalogue entry shares one cache entry rather than two.
	 */
	public const WCAG_CACHE_TTL = 3600;

	/**
	 * Own-vocabulary tokens that stand for the audited `--nldesign-*` names.
	 *
	 * Summer Breeze's stack reads `--summer-*` and never `--nldesign-*`, so
	 * layering its set over `defaults.css` measured the Rijkshuisstijl
	 * defaults (10.20:1) instead of the colours the page paints
	 * (thematiq#1022). A set that declares one of these tokens, and not the
	 * `--nldesign-*` name itself, is measured on its own value.
	 *
	 * @var array<string, string>
	 */
	private const OWN_VOCABULARY_ALIASES = [
		'--summer-color-primary' => '--nldesign-color-primary',
		'--summer-color-primary-text' => '--nldesign-color-primary-text',
		'--summer-color-background-plain' => '--nldesign-color-background',
	];

	/**
	 * The WCAG contrast service (relative-luminance math).
	 *
	 * @var ContrastService
	 */
	private ContrastService $contrast;

	/**
	 * The CSS custom-property parser.
	 *
	 * @var CssParserService
	 */
	private CssParserService $parser;

	/**
	 * Constructor.
	 *
	 * @param ContrastService $contrast The WCAG contrast service.
	 * @param CssParserService $parser The CSS custom-property parser.
	 * @param RuntimeFileStore|null $store Where uploaded sets are kept.
	 * @param SetFileReader $files Reads a set's file from the release or the store.
	 * @param SettableContrastPairs $settablePairs Measures the selection and highlight pairs a set moves.
	 * @param DenhaagContrastPairs $denhaagPairs Measures the text pairs of the Den Haag components.
	 */
	public function __construct(
		ContrastService $contrast,
		CssParserService $parser,
		private readonly ?RuntimeFileStore $store = null,
		private readonly SetFileReader $files = new SetFileReader(),
		private readonly SettableContrastPairs $settablePairs = new SettableContrastPairs(),
		private readonly DenhaagContrastPairs $denhaagPairs = new DenhaagContrastPairs(),
	) {
		$this->contrast = $contrast;
		$this->parser = $parser;
	}//end __construct()

	/**
	 * Resolve the fixed --nldesign-* colour declarations for a token set.
	 *
	 * Layers css/tokens/{id}.css over css/systems/nldesign/defaults.css (the same
	 * order the runtime uses), measures an own-vocabulary set on its own primary,
	 * label and page colours ({@see self::OWN_VOCABULARY_ALIASES}) and, when the
	 * token CSS omits an explicit background, falls back to the set's
	 * theming.background_color.
	 *
	 * @param string $appPath The app root path.
	 * @param string $id The token set id.
	 * @param array<string, mixed> $theming The set's theming block from token-sets.json.
	 *
	 * @return array<string, string> Map of --nldesign-* token name => literal value.
	 *
	 * @spec openspec/specs/token-set-contrast-audit/spec.md#requirement-automated-contrast-audit-over-all-shipped-token-sets
	 */
	public function resolveDeclarations(string $appPath, string $id, array $theming): array {
		$declarations = $this->parseFile(filePath: $appPath . '/css/systems/nldesign/defaults.css');

		$tokenCss = $this->files->read(store: $this->store, appPath: $appPath, name: 'css/tokens/' . $id . '.css');
		if ($tokenCss !== null) {
			$own = ($this->parser->parseDeclarations(content: $tokenCss) ?? []);
			$declarations = array_merge($declarations, $own);
			foreach (self::OWN_VOCABULARY_ALIASES as $ownToken => $nldesignToken) {
				if (isset($own[$ownToken]) === true && isset($own[$nldesignToken]) === false) {
					$declarations[$nldesignToken] = $own[$ownToken];
				}
			}
		}

		// Background is managed by Nextcloud theming for many sets, so it is
		// frequently absent from the token CSS. Fall back to the declared
		// theming.background_color so the primary/background pair can evaluate.
		if (isset($declarations['--nldesign-color-background']) === false
			&& isset($theming['background_color']) === true
			&& is_string($theming['background_color']) === true
		) {
			$declarations['--nldesign-color-background'] = $theming['background_color'];
		}

		return $declarations;
	}//end resolveDeclarations()

	/**
	 * Compute the audit verdict for a single token set at the given level.
	 *
	 * @param string $appPath The app root path.
	 * @param string $id The token set id.
	 * @param array<string, mixed> $theming The set's theming block.
	 * @param string $level The WCAG threshold profile: 'AA' (default) or 'AAA' (7:1 / 4.5:1).
	 *
	 * The per-set audit result.
	 *
	 * @return array{
	 *     id: string,
	 *     textRatio: float|null,
	 *     uiRatio: float|null,
	 *     textThreshold: float,
	 *     uiThreshold: float,
	 *     verdict: string,
	 *     pairs?: array<int, array<string, mixed>>,
	 *     denhaag?: array<string, array<string, mixed>>
	 * }
	 *
	 * @spec openspec/specs/token-set-contrast-audit/spec.md#requirement-automated-contrast-audit-over-all-shipped-token-sets
	 * @spec openspec/changes/denhaag-component-tokens/specs/token-set-contrast-audit/spec.md
	 */
	public function auditSet(string $appPath, string $id, array $theming, string $level = 'AA'): array {
		$declarations = $this->resolveDeclarations(appPath: $appPath, id: $id, theming: $theming);
		$textThreshold = self::AA_TEXT;
		$uiThreshold = self::AA_UI;
		if ($level === 'AAA') {
			$textThreshold = self::AAA_TEXT;
			$uiThreshold = self::AAA_UI;
		}

		$textRatio = $this->pairRatio(
			declarations: $declarations,
			foreground: '--nldesign-color-primary-text',
			background: '--nldesign-color-primary'
		);
		$uiRatio = $this->pairRatio(
			declarations: $declarations,
			foreground: '--nldesign-color-primary',
			background: '--nldesign-color-background'
		);

		$verdict = $this->classify(
			textRatio: $textRatio,
			uiRatio: $uiRatio,
			textThreshold: $textThreshold,
			uiThreshold: $uiThreshold
		);

		return [
			'id' => $id,
			'textRatio' => $textRatio,
			'uiRatio' => $uiRatio,
			'textThreshold' => $textThreshold,
			'uiThreshold' => $uiThreshold,
			'verdict' => $verdict,
			'pairs' => $this->settablePairs->pairs(declarations: $declarations),
			// Reported, not part of the verdict: the Den Haag pairs judge what a
			// portal draws, and the verdict above stays what the apply dialog
			// and the capabilities document have always meant by it.
			'denhaag' => $this->denhaagPairs->pairs(
				declarations: $this->portalCascade(appPath: $appPath, id: $id)
			),
		];
	}//end auditSet()

	/**
	 * The declarations a portal sees for a set: defaults, the public bridge, the set.
	 *
	 * The bridge is linked before the set, so a set's own value wins over the
	 * bridge, and the bridge's over the defaults.
	 *
	 * @param string $appPath The app root path.
	 * @param string $id The token set id.
	 *
	 * @return array<string, string> The merged declarations.
	 *
	 * @spec openspec/changes/denhaag-component-tokens/specs/token-set-contrast-audit/spec.md
	 */
	public function portalCascade(string $appPath, string $id): array {
		$declarations = array_merge(
			$this->parseFile(filePath: $appPath . '/css/systems/nldesign/defaults.css'),
			$this->parseFile(filePath: $appPath . '/css/public-bridge.css')
		);

		// An unknown set has no file: an empty one parses to no declarations.
		$tokenCss = ($this->files->read(store: $this->store, appPath: $appPath, name: 'css/tokens/' . $id . '.css') ?? '');
		$declarations = array_merge($declarations, ($this->parser->parseDeclarations(content: $tokenCss) ?? []));

		// NOT theming.background_color, which resolveDeclarations() falls back
		// to: that is Nextcloud's login background (vng's is #0277BD), and a
		// portal never paints its page with it. The bridge paints the page from
		// --nldesign-color-background with a white fallback, so that is what
		// a component sits on.
		return $declarations + ['--nldesign-color-background' => '#ffffff'];
	}//end portalCascade()

	/**
	 * Compute and cache the WCAG level for one token set, sharing the exact
	 * cache namespace/key/TTL `Capabilities::computeWcagLevel()` uses for the
	 * active set (`ICache` prefix `thematiq_wcag_level`, key `level-<id>`,
	 * TTL 3600s), so a set that is both the active theme (warmed by
	 * `Capabilities` on every capabilities-document read) and a catalogue
	 * entry (read by the public catalogue endpoint) hits one cache entry
	 * rather than computing the audit twice. `Capabilities.php` itself is
	 * unmodified by this change — its own private `computeWcagLevel()`/
	 * `auditWcagLevel()` pair keeps working exactly as before, resolving the
	 * same cache key this method writes.
	 *
	 * Null for a stock (`none` design system) or custom/unknown set — the
	 * audit has nothing to evaluate and MUST NOT fabricate a conformance
	 * claim, matching `Capabilities`' own null case exactly.
	 *
	 * @param ICache $cache Distributed cache created with prefix `thematiq_wcag_level`.
	 * @param string $appPath The app root path.
	 * @param string $tokenSetId The token set id.
	 * @param array<string, mixed> $tokenSetMeta The set's manifest entry (design_system, theming, contrast_level).
	 *
	 * @return string|null One of `AAA`, `AA`, `fail`, or null.
	 *
	 * @spec openspec/specs/app-token-set-selection/spec.md
	 */
	public function computeCachedWcagLevel(ICache $cache, string $appPath, string $tokenSetId, array $tokenSetMeta): ?string {
		$designSystemId = ($tokenSetMeta['design_system'] ?? null);
		if (empty($tokenSetMeta) === true || $designSystemId === 'none') {
			return null;
		}

		$cacheKey = 'level-' . $tokenSetId;
		$cached = $cache->get(key: $cacheKey);
		if (is_string($cached) === true) {
			return $cached;
		}

		$theming = ($tokenSetMeta['theming'] ?? []);
		$declaresAaa = ($designSystemId === 'high-contrast' || ($tokenSetMeta['contrast_level'] ?? null) === 'AAA');

		$passesAa = $this->auditSet(appPath: $appPath, id: $tokenSetId, theming: $theming, level: 'AA')['verdict'] === 'pass';

		$level = 'fail';
		if ($declaresAaa === true) {
			$passesAaa = $this->auditSet(appPath: $appPath, id: $tokenSetId, theming: $theming, level: 'AAA')['verdict'] === 'pass';
			if ($passesAaa === true) {
				$level = 'AAA';
			}
		}

		if ($level !== 'AAA' && $passesAa === true) {
			$level = 'AA';
		}

		$cache->set(key: $cacheKey, value: $level, ttl: self::WCAG_CACHE_TTL);

		return $level;
	}//end computeCachedWcagLevel()

	/**
	 * Audit every shipped token set with a non-`none` design system.
	 *
	 * @param string $appPath The app root path.
	 *
	 * One audit result per audited set, ordered deterministically by id.
	 *
	 * @return array<int, array{
	 *     id: string,
	 *     textRatio: float|null,
	 *     uiRatio: float|null,
	 *     textThreshold: float,
	 *     uiThreshold: float,
	 *     verdict: string,
	 *     pairs?: array<int, array<string, mixed>>,
	 *     denhaag?: array<string, array<string, mixed>>
	 * }>
	 *
	 * @spec openspec/specs/token-set-contrast-audit/spec.md#requirement-reproducible-contrast-report
	 */
	public function auditAll(string $appPath): array {
		$results = [];
		foreach ($this->auditableSets(appPath: $appPath) as $set) {
			$results[] = $this->auditSet(
				appPath: $appPath,
				id: $set['id'],
				theming: ($set['theming'] ?? []),
				level: $this->requiredLevel(set: $set)
			);
		}

		usort($results, static fn (array $a, array $b): int => strcmp($a['id'], $b['id']));

		return $results;
	}//end auditAll()

	/**
	 * Compute the runtime, non-blocking contrast warnings for a shipped set.
	 *
	 * Reuses ContrastService::check() so the shipped-set apply dialog raises the
	 * exact same warning shape as a custom upload. Returns an empty array for a
	 * compliant set.
	 *
	 * @param string $appPath The app root path.
	 * @param string $id The token set id.
	 * @param string $designSystem The set's design system id.
	 * @param array<string, mixed> $theming The set's theming block.
	 *
	 * @return array<int, array<string, mixed>> The contrast warnings (empty when compliant).
	 *
	 * @spec openspec/specs/token-set-contrast-audit/spec.md#requirement-non-compliant-sets-are-surfaced-in-the-apply-dialog
	 */
	public function warningsFor(string $appPath, string $id, string $designSystem, array $theming): array {
		if ($designSystem === 'none') {
			return [];
		}

		$declarations = $this->resolveDeclarations(appPath: $appPath, id: $id, theming: $theming);

		return $this->contrast->check(declarations: $declarations);
	}//end warningsFor()

	/**
	 * Render the deterministic Markdown contrast report for all audited sets.
	 *
	 * @param string $appPath The app root path.
	 *
	 * @return string The report Markdown (trailing newline included).
	 *
	 * @spec openspec/specs/token-set-contrast-audit/spec.md#requirement-reproducible-contrast-report
	 */
	public function renderReport(string $appPath): string {
		$rows = $this->auditAll(appPath: $appPath);

		$lines = [];
		$lines[] = '<!-- GENERATED by ShippedTokenSetAuditService::renderReport() — do not edit by hand.';
		$lines[] = '     Regenerate with: composer docs:contrast-report -->';
		$lines[] = '';
		$lines[] = '# Shipped Token-Set Contrast Report';
		$lines[] = '';
		$lines[] = 'WCAG 2.1 relative-luminance contrast for every shipped token set whose design';
		$lines[] = 'system reads `--nldesign-*` tokens, computed by `ContrastService` over';
		$lines[] = '`css/tokens/{id}.css` layered on `css/systems/nldesign/defaults.css`.';
		$lines[] = '';
		$lines[] = '- **primary/text** = `--nldesign-color-primary` vs `--nldesign-color-primary-text` (AA text threshold 4.5:1)';
		$lines[] = '- **primary/bg** = `--nldesign-color-primary` vs the set background (AA UI threshold 3.0:1)';
		$lines[] = '- `unevaluated` = a pair whose colours are not literal (e.g. `var()`); never treated as passing.';
		$lines[] = '';
		$lines[] = '| Token set | primary/text | text ≥ | primary/bg | bg ≥ | Verdict |';
		$lines[] = '|-----------|-------------:|:------:|-----------:|:----:|:-------:|';

		foreach ($rows as $row) {
			$lines[] = sprintf(
				'| %s | %s | %s | %s | %s | %s |',
				$row['id'],
				$this->formatRatio(ratio: $row['textRatio']),
				$this->formatThreshold(threshold: $row['textThreshold']),
				$this->formatRatio(ratio: $row['uiRatio']),
				$this->formatThreshold(threshold: $row['uiThreshold']),
				$row['verdict']
			);
		}

		$lines[] = '';

		return implode("\n", array_merge($lines, $this->denhaagPairs->reportLines(rows: $rows))) . "\n";
	}//end renderReport()

	/**
	 * The shipped token sets with a non-`none` design system.
	 *
	 * @param string $appPath The app root path.
	 *
	 * @return array<int, array<string, mixed>> The auditable set metadata entries.
	 */
	private function auditableSets(string $appPath): array {
		$manifestPath = $appPath . '/token-sets.json';
		if (is_readable($manifestPath) === false) {
			return [];
		}

		$manifest = json_decode((string)file_get_contents($manifestPath), true);
		if (is_array($manifest) === false) {
			return [];
		}

		$sets = [];
		foreach ($manifest as $set) {
			if (is_array($set) === false || isset($set['id']) === false) {
				continue;
			}

			$designSystem = ($set['design_system'] ?? 'nldesign');
			if ($designSystem === 'none') {
				continue;
			}

			$sets[] = $set;
		}

		return $sets;
	}//end auditableSets()

	/**
	 * Whether a set must be held to the AAA threshold (high-contrast sets).
	 *
	 * @param array<string, mixed> $set The set metadata.
	 *
	 * @return string 'AAA' when the set is a high-contrast set, 'AA' otherwise.
	 */
	private function requiredLevel(array $set): string {
		$isHighContrast = (($set['design_system'] ?? '') === 'high-contrast')
			|| (($set['contrast_level'] ?? '') === 'AAA');
		if ($isHighContrast === true) {
			return 'AAA';
		}

		return 'AA';
	}//end requiredLevel()

	/**
	 * Parse a CSS file into a --token => value map (empty when absent).
	 *
	 * @param string $filePath The absolute file path.
	 *
	 * @return array<string, string> The parsed declarations.
	 */
	private function parseFile(string $filePath): array {
		if (is_file($filePath) === false) {
			return [];
		}

		$content = file_get_contents($filePath);
		if ($content === false) {
			return [];
		}

		return ($this->parser->parseDeclarations(content: $content) ?? []);
	}//end parseFile()

	/**
	 * Compute the WCAG ratio for one foreground/background token pair.
	 *
	 * @param array<string, string> $declarations The resolved declarations.
	 * @param string $foreground The foreground token name.
	 * @param string $background The background token name.
	 *
	 * @return float|null The ratio rounded to 2 decimals, or null when unevaluated.
	 */
	private function pairRatio(array $declarations, string $foreground, string $background): ?float {
		$fgValue = ($declarations[$foreground] ?? null);
		$bgValue = ($declarations[$background] ?? null);
		if ($fgValue === null || $bgValue === null) {
			return null;
		}

		// Measured as it renders, so a translucent colour is blended first.
		$ratio = $this->contrast->measure(
			foreground: $fgValue,
			background: $bgValue,
			page: ($declarations['--nldesign-color-background'] ?? null)
		);
		if ($ratio === null) {
			return null;
		}

		return round($ratio, 2);
	}//end pairRatio()

	/**
	 * Classify a set from its two pair ratios and thresholds.
	 *
	 * @param float|null $textRatio The primary/text ratio.
	 * @param float|null $uiRatio The primary/background ratio.
	 * @param float $textThreshold The applicable text threshold.
	 * @param float $uiThreshold The applicable UI threshold.
	 *
	 * @return string One of `pass`, `fail`, `unevaluated`.
	 */
	private function classify(?float $textRatio, ?float $uiRatio, float $textThreshold, float $uiThreshold): string {
		if ($textRatio === null || $uiRatio === null) {
			return 'unevaluated';
		}

		if ($textRatio >= $textThreshold && $uiRatio >= $uiThreshold) {
			return 'pass';
		}

		return 'fail';
	}//end classify()

	/**
	 * Format a ratio for the report (2 decimals or an em dash when null).
	 *
	 * @param float|null $ratio The ratio.
	 *
	 * @return string The formatted cell value.
	 */
	private function formatRatio(?float $ratio): string {
		if ($ratio === null) {
			return '—';
		}

		return number_format($ratio, 2, '.', '') . ':1';
	}//end formatRatio()

	/**
	 * Format a threshold for the report.
	 *
	 * @param float $threshold The threshold.
	 *
	 * @return string The formatted cell value.
	 */
	private function formatThreshold(float $threshold): string {
		return number_format($threshold, 1, '.', '') . ':1';
	}//end formatThreshold()

}//end class
