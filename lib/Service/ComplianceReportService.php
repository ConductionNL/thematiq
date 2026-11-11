<?php

/**
 * NL Design Compliance Evidence Report Service.
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
 * @spec openspec/specs/compliance-evidence/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCA\Thematiq\AppInfo\Application;
use OCP\ServerVersion;

/**
 * Computes the active-configuration WCAG contrast compliance evidence report.
 *
 * Unlike {@see ShippedTokenSetAuditService} (which audits every SHIPPED token
 * set in isolation over two fixed pairs), this service evaluates the ACTIVE
 * token set plus the admin's custom overrides — the configuration actually
 * rendered to end users — over the full 18-pair matrix enumerated in the
 * `compliance-evidence` spec. Both services share one contrast contract:
 * `ContrastService::ratio()`/`parseColor()` and the `unevaluated`-never-passes
 * rule (see `openspec/specs/token-set-contrast-audit/spec.md`).
 *
 * Resolution mirrors the runtime cascade in
 * `AppInfo\Application::injectThemeCSS()`: the `--nldesign-*` design-system
 * layers (defaults.css + tokens/{id}.css) are only consulted when the active
 * token set's design system is not `none` — for `none` (stock Nextcloud), the
 * runtime never loads them either, so the affected pairs are honestly
 * reported `unevaluated` rather than scored against values that were never
 * actually served to a browser. `css/custom-overrides.css` always wins,
 * because the runtime always loads it last regardless of design system.
 *
 * This report is color-contrast evidence for theme tokens ONLY — see
 * {@see self::SCOPE_STATEMENT} — never a WCAG-EM audit or a full WCAG
 * evaluation (claim-accuracy discipline, `openspec/specs/claim-accuracy/spec.md`).
 *
 * @phpstan-type CssMap array<string, string>
 * @phpstan-type Resolution array{value: string|null, unresolved: string|null}
 * @phpstan-type ColourLayers array{customOverrides: CssMap, mapping: CssMap, declarations: CssMap, designSystemId: string}
 * @phpstan-type PairSide array{token: string, resolved: Resolution, color: array{0: int, 1: int, 2: int}|null}
 * @psalm-type   CssMap array<string, string>
 * @psalm-type   Resolution array{value: string|null, unresolved: string|null}
 * @psalm-type   ColourLayers array{customOverrides: CssMap, mapping: CssMap, declarations: CssMap, designSystemId: string}
 * @psalm-type   PairSide array{token: string, resolved: Resolution, color: array{0: int, 1: int, 2: int}|null}
 *
 * @spec openspec/specs/compliance-evidence/spec.md
 *
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity) - The complexity is the 18-pair WCAG
 * contrast matrix: each pair resolves fg+bg through the same layered cascade the runtime uses
 * (defaults → active set → theming.background_color → #ffffff → custom-overrides) and classifies
 * against AA thresholds with an unevaluated-caps-the-verdict rule. The evidence contract is the
 * source of the branching, not accidental structure.
 */
class ComplianceReportService {

	/**
	 * Sentinel background identifier for the pairs measured against the
	 * "effective main background" rather than a specific --color-* token.
	 *
	 * @var string
	 */
	private const MAIN_BACKGROUND_TOKEN = '--color-main-background';

	/**
	 * The honest-scope statement embedded verbatim in every rendered report
	 * (Markdown prose and the JSON `scope` field). Deliberately never contains
	 * the literal phrases "WCAG compliant" or "voldoet aan WCAG" — this
	 * report must never be read as certifying overall WCAG conformance.
	 *
	 * @var string
	 */
	private const SCOPE_STATEMENT = 'Scope of this report: color-contrast of NL Design theme tokens only, '
		. 'providing evidence toward WCAG 2.2 Success Criteria 1.4.3 (Contrast Minimum) and 1.4.11 '
		. '(Non-text Contrast). This is NOT a WCAG-EM audit and NOT a full WCAG 2.2 evaluation — it does '
		. 'not assess content, keyboard operability, semantics, screen-reader behaviour, or any non-color '
		. 'criterion. It is supporting evidence for a toegankelijkheidsverklaring; an expert WCAG-EM '
		. 'evaluation by a qualified auditor remains required. This report, whatever its verdict, makes no '
		. 'claim about the overall accessibility conformance of this Nextcloud instance.';

	/**
	 * The normatively-enumerated 18-pair compliance matrix (see
	 * `compliance-evidence` spec, Requirement: Compliance Pair Matrix).
	 * Order is significant: it is echoed verbatim in every rendered report.
	 *
	 * @var array<int, array{fg: string, bg: string, threshold: float, basis: string}>
	 */
	private const PAIR_MATRIX = [
		// Text pairs — 4.5:1 normal-text threshold (SC 1.4.3).
		['fg' => '--color-primary-text', 'bg' => '--color-primary', 'threshold' => 4.5, 'basis' => 'normal-text'],
		['fg' => '--color-primary-element-text', 'bg' => '--color-primary-element', 'threshold' => 4.5, 'basis' => 'normal-text'],
		['fg' => '--color-primary-light-text', 'bg' => '--color-primary-light', 'threshold' => 4.5, 'basis' => 'normal-text'],
		['fg' => '--color-primary-element-light-text', 'bg' => '--color-primary-element-light', 'threshold' => 4.5, 'basis' => 'normal-text'],
		['fg' => '--color-main-text', 'bg' => self::MAIN_BACKGROUND_TOKEN, 'threshold' => 4.5, 'basis' => 'normal-text'],
		['fg' => '--color-text-maxcontrast', 'bg' => self::MAIN_BACKGROUND_TOKEN, 'threshold' => 4.5, 'basis' => 'normal-text'],
		['fg' => '--color-text-error', 'bg' => self::MAIN_BACKGROUND_TOKEN, 'threshold' => 4.5, 'basis' => 'normal-text'],
		['fg' => '--color-text-success', 'bg' => self::MAIN_BACKGROUND_TOKEN, 'threshold' => 4.5, 'basis' => 'normal-text'],
		['fg' => '--color-text-warning', 'bg' => self::MAIN_BACKGROUND_TOKEN, 'threshold' => 4.5, 'basis' => 'normal-text'],
		// Non-text / UI-component pairs — 3:1 threshold (SC 1.4.11 / large-text floor of SC 1.4.3).
		['fg' => '--color-primary', 'bg' => self::MAIN_BACKGROUND_TOKEN, 'threshold' => 3.0, 'basis' => 'ui-component'],
		['fg' => '--color-primary-element', 'bg' => self::MAIN_BACKGROUND_TOKEN, 'threshold' => 3.0, 'basis' => 'ui-component'],
		['fg' => '--color-error', 'bg' => self::MAIN_BACKGROUND_TOKEN, 'threshold' => 3.0, 'basis' => 'ui-component'],
		['fg' => '--color-warning', 'bg' => self::MAIN_BACKGROUND_TOKEN, 'threshold' => 3.0, 'basis' => 'ui-component'],
		['fg' => '--color-success', 'bg' => self::MAIN_BACKGROUND_TOKEN, 'threshold' => 3.0, 'basis' => 'ui-component'],
		['fg' => '--color-info', 'bg' => self::MAIN_BACKGROUND_TOKEN, 'threshold' => 3.0, 'basis' => 'ui-component'],
		['fg' => '--color-border-maxcontrast', 'bg' => self::MAIN_BACKGROUND_TOKEN, 'threshold' => 3.0, 'basis' => 'ui-component'],
		['fg' => '--color-border-error', 'bg' => self::MAIN_BACKGROUND_TOKEN, 'threshold' => 3.0, 'basis' => 'ui-component'],
		['fg' => '--color-border-success', 'bg' => self::MAIN_BACKGROUND_TOKEN, 'threshold' => 3.0, 'basis' => 'ui-component'],
	];

	/**
	 * The running server's version (public since NC 31, below this app's floor of 32).
	 *
	 * Null only when constructed without it (isolated unit tests).
	 *
	 * @var ServerVersion|null
	 */
	private ?ServerVersion $serverVersion;

	/**
	 * Constructor.
	 *
	 * @param ContrastTools $measuring The contrast maths and the custom property parser.
	 * @param ThemeSelectionSources $theme The custom overrides, the shipped design systems and the custom token sets.
	 * @param InstanceReportContext $instance The app manager, the config, the URL generator and the clock.
	 * @param ServerVersion|null $serverVersion The running server's version.
	 */
	public function __construct(
		private readonly ContrastTools $measuring,
		private readonly ThemeSelectionSources $theme,
		private readonly InstanceReportContext $instance,
		?ServerVersion $serverVersion = null,
	) {
		$this->serverVersion = $serverVersion;
	}//end __construct()

	/**
	 * Generate the full compliance report data structure.
	 *
	 * The report, ready for either renderer.
	 *
	 * @return array{scope: string, metadata: array<string, mixed>, pairs: array<int, array<string, mixed>>, summary: array<string, mixed>}
	 *
	 * @spec openspec/specs/compliance-evidence/spec.md
	 */
	public function generate(): array {
		$appPath = $this->instance->appManager->getAppPath('thematiq');

		$activeTokenSetId = $this->instance->config->getAppValue(Application::APP_ID, 'token_set', 'nextcloud');
		$set = new ReportedTokenSet(id: $activeTokenSetId, meta: $this->resolveTokenSetMeta(tokenSetId: $activeTokenSetId));
		$designSystemId = $set->designSystem();

		// The runtime (Application::injectThemeCSS()) only loads the
		// --nldesign-* design-system layers when the design system is not
		// "none" — mirror that gate exactly so a stock-Nextcloud active set
		// is never scored against values the browser never received.
		$declarations = [];
		if ($designSystemId !== 'none') {
			$setCss = (string)$this->setCss(appPath: $appPath, name: 'css/tokens/' . $activeTokenSetId . '.css');
			$declarations = array_merge(
				$this->parseCssFile(path: $appPath . '/css/systems/' . $designSystemId . '/defaults.css'),
				($this->measuring->cssParser->parseDeclarations(content: $setCss) ?? [])
			);
		}

		$mapping = $this->parseCssFile(path: $appPath . '/css/systems/nldesign/overrides.css');
		$customOverrides = $this->theme->overrides->read();

		$layers = ['customOverrides' => $customOverrides, 'mapping' => $mapping, 'declarations' => $declarations, 'designSystemId' => $designSystemId];
		$pairs = [];
		foreach (self::PAIR_MATRIX as $pairDef) {
			$pairs[] = $this->evaluatePair(pairDef: $pairDef, layers: $layers, tokenSetMeta: $set->meta);
		}

		return [
			'scope' => self::SCOPE_STATEMENT,
			'metadata' => $this->buildMetadata(set: $set, customOverrides: $customOverrides),
			'pairs' => $pairs,
			'summary' => $this->classifySummary(pairs: $pairs),
		];
	}//end generate()

	/**
	 * Render the report as pretty-printed, deterministic JSON.
	 *
	 * @return string The JSON document (trailing newline included).
	 *
	 * @spec openspec/specs/compliance-evidence/spec.md
	 */
	public function renderJson(): string {
		$data = $this->generate();

		return json_encode($data, (JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) . "\n";
	}//end renderJson()

	/**
	 * Render the report as deterministic, human-readable Markdown.
	 *
	 * @return string The Markdown document (trailing newline included).
	 *
	 * @spec openspec/specs/compliance-evidence/spec.md
	 */
	public function renderMarkdown(): string {
		$data = $this->generate();
		$lines = [
			'<!-- GENERATED by ComplianceReportService::renderMarkdown() — do not edit by hand. -->',
			'',
			'# NL Design Compliance Evidence Report',
			'',
			self::SCOPE_STATEMENT,
			'',
		];
		array_push($lines, ...$this->metadataLines(meta: $data['metadata']));
		array_push($lines, ...$this->pairLines(pairs: $data['pairs']));
		array_push($lines, ...$this->summaryLines(summary: $data['summary']));

		return implode("\n", $lines) . "\n";
	}//end renderMarkdown()

	/**
	 * The Markdown metadata section.
	 *
	 * @param array<string, mixed> $meta The report metadata.
	 *
	 * @return array<int, string>
	 */
	private function metadataLines(array $meta): array {
		return [
			'## Metadata',
			'',
			'- Instance id: ' . $meta['instanceId'],
			'- Instance URL: ' . $meta['instanceUrl'],
			'- nldesign app version: ' . $meta['appVersion'],
			'- Nextcloud version: ' . $meta['nextcloudVersion'],
			'- Active token set: ' . $meta['tokenSet']['id'] . ' ("' . $meta['tokenSet']['name'] . '", version ' . $meta['tokenSet']['version'] . ')',
			'- Design system: ' . $meta['designSystem'],
			'- Generated at: ' . $meta['generatedAt'],
			'- Custom overrides SHA-256: ' . $meta['overridesHash'],
			'',
		];
	}//end metadataLines()

	/**
	 * The Markdown pair matrix, followed by the notes of the pairs that carry one.
	 *
	 * @param array<int, array<string, mixed>> $pairs The evaluated pairs.
	 *
	 * @return array<int, string>
	 */
	private function pairLines(array $pairs): array {
		$lines = [
			'## Pair matrix (' . count($pairs) . ' pairs)',
			'',
			'| # | Foreground | Background | FG value | BG value | Ratio | Threshold | Basis | Verdict |',
			'|---|------------|------------|----------|----------|------:|----------:|-------|:-------:|',
		];
		$notes = [];
		foreach ($pairs as $index => $pair) {
			$lines[] = $this->pairRow(number: ($index + 1), pair: $pair);
			if ($pair['note'] !== null) {
				$notes[] = '- Pair ' . ($index + 1) . ': ' . $pair['note'];
			}
		}

		$lines[] = '';
		if ($notes !== []) {
			array_push($lines, '### Notes', '', ...$notes);
			$lines[] = '';
		}

		return $lines;
	}//end pairLines()

	/**
	 * One row of the Markdown pair matrix.
	 *
	 * @param int $number The pair's number, from 1.
	 * @param array<string, mixed> $pair The evaluated pair.
	 *
	 * @return string
	 */
	private function pairRow(int $number, array $pair): string {
		return sprintf(
			'| %d | `%s` | `%s` | %s | %s | %s | %s | %s | %s |',
			$number,
			$pair['foreground'],
			$pair['background'],
			($pair['foregroundValue'] ?? '—'),
			($pair['backgroundValue'] ?? '—'),
			(new ContrastReportCells())->ratio(ratio: $pair['ratio']),
			(new ContrastReportCells())->threshold(threshold: $pair['threshold']),
			$pair['basis'],
			$pair['verdict']
		);
	}//end pairRow()

	/**
	 * The Markdown summary section.
	 *
	 * @param array<string, mixed> $summary The report summary.
	 *
	 * @return array<int, string>
	 */
	private function summaryLines(array $summary): array {
		return [
			'## Summary',
			'',
			'- Passed: ' . $summary['passed'],
			'- Failed: ' . $summary['failed'],
			'- Unevaluated: ' . $summary['unevaluated'],
			'- Overall verdict: ' . $summary['verdict'],
			'',
		];
	}//end summaryLines()

	/**
	 * Resolve a token set's metadata: shipped (token-sets.json) first, falling
	 * back to the custom-set manifest (admin-uploaded `custom-*` sets).
	 *
	 * @param string $tokenSetId The active token set id.
	 *
	 * @return array<string, mixed> The token set metadata (empty when unknown).
	 *
	 * @spec openspec/specs/compliance-evidence/spec.md
	 */
	private function resolveTokenSetMeta(string $tokenSetId): array {
		$shipped = $this->theme->designSystems->getTokenSetMeta(tokenSetId: $tokenSetId);
		if (empty($shipped) === false) {
			return $shipped;
		}

		$custom = $this->theme->customSets->getManifest();

		return ($custom[$tokenSetId] ?? []);
	}//end resolveTokenSetMeta()

	/**
	 * Parse a CSS file's custom-property declarations, tolerating absence.
	 *
	 * @param string $path The absolute file path.
	 *
	 * @return array<string, string> The parsed declarations (empty when the file is absent/unreadable).
	 *
	 * @spec openspec/specs/compliance-evidence/spec.md
	 */
	private function parseCssFile(string $path): array {
		if (is_file($path) === false) {
			return [];
		}

		$content = file_get_contents($path);
		if ($content === false) {
			return [];
		}

		return ($this->measuring->cssParser->parseDeclarations(content: $content) ?? []);
	}//end parseCssFile()

	/**
	 * Evaluate a single pair-matrix entry into its full report row.
	 *
	 * @param array{fg: string, bg: string, threshold: float, basis: string} $pairDef The matrix entry.
	 * @param ColourLayers $layers The custom-overrides layer, the --color-* => --nldesign-* mapping, the layered
	 *                             --nldesign-* declarations and the active design system id.
	 * @param array<string, mixed> $tokenSetMeta The active token set's metadata.
	 *
	 * @return array<string, mixed> The evaluated pair row.
	 *
	 * @spec openspec/specs/compliance-evidence/spec.md
	 */
	private function evaluatePair(array $pairDef, array $layers, array $tokenSetMeta): array {
		$fgResolved = $this->resolveColorToken(token: $pairDef['fg'], layers: $layers);
		$bgResolved = $this->resolveBackground(token: $pairDef['bg'], context: ['layers' => $layers, 'tokenSetMeta' => $tokenSetMeta]);
		$fgColor = $this->parsedColor(resolved: $fgResolved);
		$bgColor = $this->parsedColor(resolved: $bgResolved);
		$entry = $this->pairEntry(pairDef: $pairDef, values: ['fg' => $fgResolved['value'], 'bg' => $bgResolved['value']]);

		if ($fgColor === null || $bgColor === null) {
			$entry['note'] = $this->describeUnresolvedPair(
				foreground: ['token' => $pairDef['fg'], 'resolved' => $fgResolved, 'color' => $fgColor],
				background: ['token' => $pairDef['bg'], 'resolved' => $bgResolved, 'color' => $bgColor]
			);

			return $entry;
		}

		return $this->withRatio(entry: $entry);
	}//end evaluatePair()

	/**
	 * Resolve a pair's background token; the main background has a resolution of its own.
	 *
	 * @param string $token The background token.
	 * @param array{layers: ColourLayers, tokenSetMeta: array<string, mixed>} $context The colour layers and the active token set's metadata.
	 *
	 * @return array<string, mixed> The resolution, as resolveColorToken() answers it.
	 */
	private function resolveBackground(string $token, array $context): array {
		$resolved = $this->resolveColorToken(token: $token, layers: $context['layers']);
		if ($token === self::MAIN_BACKGROUND_TOKEN) {
			$resolved = $this->resolveMainBackground(
				customOverrides: $context['layers']['customOverrides'],
				declarations: $context['layers']['declarations'],
				tokenSetMeta: $context['tokenSetMeta']
			);
		}

		return $resolved;
	}//end resolveBackground()

	/**
	 * The parsed colour of a resolved token, or null when it resolved to nothing or does not parse.
	 *
	 * @param array<string, mixed> $resolved The resolution.
	 *
	 * @return array{0: int, 1: int, 2: int}|null
	 */
	private function parsedColor(array $resolved): ?array {
		if ($resolved['value'] === null) {
			return null;
		}

		return $this->measuring->contrast->parseColor(value: $resolved['value']);
	}//end parsedColor()

	/**
	 * A pair's report row before it is measured.
	 *
	 * @param array{fg: string, bg: string, threshold: float, basis: string} $pairDef The matrix entry.
	 * @param array{fg: string|null, bg: string|null} $values The resolved foreground and background values.
	 *
	 * @return array<string, mixed>
	 */
	private function pairEntry(array $pairDef, array $values): array {
		return [
			'foreground' => $pairDef['fg'],
			'background' => $pairDef['bg'],
			'foregroundValue' => $values['fg'],
			'backgroundValue' => $values['bg'],
			'ratio' => null,
			'threshold' => $pairDef['threshold'],
			'basis' => $pairDef['basis'],
			'verdict' => 'unevaluated',
			'note' => null,
		];
	}//end pairEntry()

	/**
	 * Measure a row whose two colours both parse, and give it its verdict.
	 *
	 * Measured as it renders, so a translucent colour is blended first. Both
	 * values parse, so measure() cannot answer null here.
	 *
	 * @param array<string, mixed> $entry The row from pairEntry().
	 *
	 * @return array<string, mixed>
	 */
	private function withRatio(array $entry): array {
		$measured = $this->measuring->contrast->measure(foreground: (string)$entry['foregroundValue'], background: (string)$entry['backgroundValue']);
		$ratio = round(($measured ?? 0.0), 2);
		$entry['ratio'] = $ratio;
		$entry['verdict'] = 'fail';
		if ($ratio >= $entry['threshold']) {
			$entry['verdict'] = 'pass';
		}

		return $entry;
	}//end withRatio()

	/**
	 * Resolve a --color-* pair token: custom override first, else the mapped
	 * --nldesign-* source token (per css/systems/nldesign/overrides.css),
	 * resolved transitively against the layered declarations.
	 *
	 * @param string $token The --color-* token name.
	 * @param ColourLayers $layers The custom-overrides layer, the --color-* => --nldesign-* mapping, the layered
	 *                             --nldesign-* declarations and the active design system id (for the diagnostic note).
	 *
	 * @return array{value: string|null, unresolved: string|null} The resolution result.
	 *
	 * @spec openspec/specs/compliance-evidence/spec.md
	 */
	private function resolveColorToken(string $token, array $layers): array {
		if (array_key_exists($token, $layers['customOverrides']) === true) {
			return ['value' => $layers['customOverrides'][$token], 'unresolved' => null];
		}

		if (array_key_exists($token, $layers['mapping']) === false) {
			return [
				'value' => null,
				'unresolved' => $token . ' (no nldesign token mapping for design system "' . $layers['designSystemId'] . '")',
			];
		}

		return $this->resolveVarChain(value: $layers['mapping'][$token], declarations: $layers['declarations']);
	}//end resolveColorToken()

	/**
	 * Resolve the "effective main background": custom-overridden
	 * --color-main-background when set, else the active set's
	 * --nldesign-color-background, else the set's theming.background_color,
	 * else #ffffff (last resort — this path never leaves the pair unevaluated).
	 *
	 * @param array<string, string> $customOverrides The custom-overrides layer.
	 * @param array<string, string> $declarations The layered --nldesign-* declarations.
	 * @param array<string, mixed> $tokenSetMeta The active token set's metadata.
	 *
	 * @return array{value: string|null, unresolved: string|null} The resolution result.
	 *
	 * @spec openspec/specs/compliance-evidence/spec.md
	 */
	private function resolveMainBackground(array $customOverrides, array $declarations, array $tokenSetMeta): array {
		if (array_key_exists(self::MAIN_BACKGROUND_TOKEN, $customOverrides) === true) {
			return ['value' => $customOverrides[self::MAIN_BACKGROUND_TOKEN], 'unresolved' => null];
		}

		if (array_key_exists('--nldesign-color-background', $declarations) === true) {
			return $this->resolveVarChain(value: $declarations['--nldesign-color-background'], declarations: $declarations);
		}

		$theming = ($tokenSetMeta['theming'] ?? []);
		$fallback = ($theming['background_color'] ?? null);
		if (is_string($fallback) === true && $fallback !== '') {
			return ['value' => $fallback, 'unresolved' => null];
		}

		return ['value' => '#ffffff', 'unresolved' => null];
	}//end resolveMainBackground()

	/**
	 * Resolve a value transitively through var(--token) indirections.
	 *
	 * @param string $value The raw value (may be a var() reference).
	 * @param array<string, string> $declarations The declaration map to resolve against.
	 * @param int $depth The current recursion depth (internal).
	 *
	 * @return array{value: string|null, unresolved: string|null} The resolved literal, or the unresolved token name.
	 *
	 * @spec openspec/specs/compliance-evidence/spec.md
	 */
	private function resolveVarChain(string $value, array $declarations, int $depth = 0): array {
		$trimmed = trim($value);

		if (preg_match('/^var\(\s*(--[\w-]+)\s*\)$/', $trimmed, $matches) !== 1) {
			// Not a var() indirection — a literal (parseable or not).
			return ['value' => $trimmed, 'unresolved' => null];
		}

		$reference = $matches[1];

		// Depth cap: at least 4 levels of var() indirection are resolved.
		if ($depth >= 4 || array_key_exists($reference, $declarations) === false) {
			return ['value' => null, 'unresolved' => $reference];
		}

		return $this->resolveVarChain(value: $declarations[$reference], declarations: $declarations, depth: ($depth + 1));
	}//end resolveVarChain()

	/**
	 * Build the diagnostic note for a pair with an unresolved side.
	 *
	 * @param PairSide $foreground The foreground token name, its resolution result and its parsed colour (null when unparsed).
	 * @param PairSide $background The background token name, its resolution result and its parsed colour (null when unparsed).
	 *
	 * @return string The human-readable diagnostic note.
	 *
	 * @spec openspec/specs/compliance-evidence/spec.md
	 */
	private function describeUnresolvedPair(array $foreground, array $background): string {
		$problems = [];

		if ($foreground['color'] === null) {
			$problems[] = $this->describeUnresolvedSide(token: $foreground['token'], resolved: $foreground['resolved']);
		}

		if ($background['color'] === null) {
			$problems[] = $this->describeUnresolvedSide(token: $background['token'], resolved: $background['resolved']);
		}

		return implode('; ', $problems);
	}//end describeUnresolvedPair()

	/**
	 * Describe why one side of a pair could not be resolved to a colour.
	 *
	 * @param string $token The token name.
	 * @param array{value: string|null, unresolved: string|null} $resolved The resolution result.
	 *
	 * @return string The human-readable diagnostic fragment.
	 *
	 * @spec openspec/specs/compliance-evidence/spec.md
	 */
	private function describeUnresolvedSide(string $token, array $resolved): string {
		if ($resolved['unresolved'] !== null) {
			return $token . ': unresolved reference ' . $resolved['unresolved'];
		}

		if ($resolved['value'] !== null) {
			return $token . ': value "' . $resolved['value'] . '" is not a parseable colour literal';
		}

		return $token . ': no effective value could be resolved';
	}//end describeUnresolvedSide()

	/**
	 * Classify the pair set into pass/fail/unevaluated totals and the overall verdict.
	 *
	 * @param array<int, array<string, mixed>> $pairs The evaluated pairs.
	 *
	 * @return array{passed: int, failed: int, unevaluated: int, verdict: string} The summary.
	 *
	 * @spec openspec/specs/compliance-evidence/spec.md
	 */
	private function classifySummary(array $pairs): array {
		$passed = 0;
		$failed = 0;
		$unevaluated = 0;

		foreach ($pairs as $pair) {
			if ($pair['verdict'] === 'pass') {
				$passed++;
				continue;
			}

			if ($pair['verdict'] === 'fail') {
				$failed++;
				continue;
			}

			$unevaluated++;
		}

		// Any unevaluated pair caps the overall verdict at "incomplete" — a
		// clean pass requires zero failures AND zero unevaluated pairs.
		$verdict = 'pass';
		if ($failed > 0) {
			$verdict = 'fail';
		} elseif ($unevaluated > 0) {
			$verdict = 'incomplete';
		}

		return [
			'passed' => $passed,
			'failed' => $failed,
			'unevaluated' => $unevaluated,
			'verdict' => $verdict,
		];
	}//end classifySummary()

	/**
	 * Assemble the report metadata block (identical shape for both formats).
	 *
	 * @param ReportedTokenSet $set The active token set and its metadata.
	 * @param array<string, string> $customOverrides The custom-overrides layer.
	 *
	 * @return array<string, mixed> The metadata block.
	 *
	 * @spec openspec/specs/compliance-evidence/spec.md
	 */
	private function buildMetadata(ReportedTokenSet $set, array $customOverrides): array {
		$tokenSetId = $set->id;

		return [
			'instanceId' => $this->instance->config->getSystemValue('instanceid', ''),
			'instanceUrl' => $this->instance->urlGenerator->getBaseUrl(),
			'appVersion' => $this->instance->appManager->getAppVersion('thematiq'),
			'nextcloudVersion' => $this->resolveNextcloudVersion(),
			'tokenSet' => [
				'id' => $tokenSetId,
				'name' => ($set->meta['name'] ?? $tokenSetId),
				'version' => ($set->meta['version'] ?? 'unversioned'),
			],
			'designSystem' => $set->designSystem(),
			'generatedAt' => gmdate('Y-m-d\TH:i:s\Z', $this->instance->timeFactory->getTime()),
			'overridesHash' => $this->computeOverridesHash(overrides: $customOverrides),
		];
	}//end buildMetadata()

	/**
	 * Resolve the Nextcloud server version string.
	 *
	 * Uses the injected \OCP\ServerVersion (public since NC 31.0.0, below
	 * this app's floor of 32 in appinfo/info.xml) instead of the
	 * \OCP\Util::getVersion() deprecated since 31. Falls back to "unknown"
	 * when it was not injected (e.g. a standalone PHPUnit run without a full
	 * NC bootstrap) — that never happens for the real endpoint/occ command,
	 * only in isolated unit tests.
	 *
	 * @return string The Nextcloud version (e.g. "34.0.0.5"), or "unknown".
	 *
	 * @spec openspec/specs/compliance-evidence/spec.md
	 */
	private function resolveNextcloudVersion(): string {
		if ($this->serverVersion === null) {
			return 'unknown';
		}

		try {
			return implode('.', $this->serverVersion->getVersion());
		} catch (\Throwable $e) {
			return 'unknown';
		}
	}//end resolveNextcloudVersion()

	/**
	 * Compute the SHA-256 hex digest of the canonicalized custom-overrides
	 * declaration list (sorted `name: value` lines; empty overrides hash the
	 * empty canonical form).
	 *
	 * @param array<string, string> $overrides The custom-overrides token map.
	 *
	 * @return string The SHA-256 hex digest.
	 *
	 * @spec openspec/specs/compliance-evidence/spec.md
	 */
	private function computeOverridesHash(array $overrides): string {
		ksort($overrides);

		$lines = [];
		foreach ($overrides as $name => $value) {
			$lines[] = $name . ': ' . $value;
		}

		return hash('sha256', implode("\n", $lines));
	}//end computeOverridesHash()

	/**
	 * A set file's CSS: an uploaded (`custom-`) set's from its service, a shipped one's from the release.
	 *
	 * @param string $appPath The app directory.
	 * @param string $name The app-relative name, such as `css/tokens/utrecht.css`.
	 *
	 * @return string|null The CSS, or null when the file does not exist.
	 *
	 * @spec openspec/specs/runtime-file-storage/spec.md
	 */
	private function setCss(string $appPath, string $name): ?string {
		// An uploaded set lives in app data; its own service reads it.
		if (preg_match('#^css/tokens/(custom-[a-z0-9-]+)\.css$#', $name, $match) === 1) {
			return $this->theme->customSets->getRawContent(id: $match[1]);
		}

		$path = $appPath . '/' . $name;
		if (is_file($path) === false) {
			return null;
		}

		$css = file_get_contents($path);
		if ($css === false) {
			return null;
		}

		return $css;
	}//end setCss()
}//end class
