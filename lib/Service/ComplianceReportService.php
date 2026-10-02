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
use OCP\App\IAppManager;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;
use OCP\IURLGenerator;

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
	 * The WCAG contrast math service (relative luminance, colour parsing).
	 *
	 * @var ContrastService
	 */
	private ContrastService $contrast;

	/**
	 * The shared CSS custom-property parser.
	 *
	 * @var CssParserService
	 */
	private CssParserService $cssParser;

	/**
	 * The custom-overrides read service (top cascade layer).
	 *
	 * @var CustomOverridesService
	 */
	private CustomOverridesService $overridesService;

	/**
	 * The design-system / shipped token-set metadata service.
	 *
	 * @var DesignSystemService
	 */
	private DesignSystemService $designSystemService;

	/**
	 * The custom (admin-uploaded) token-set manifest service.
	 *
	 * @var CustomTokenSetService
	 */
	private CustomTokenSetService $customSetService;

	/**
	 * The app manager for resolving the app's on-disk CSS files.
	 *
	 * @var IAppManager
	 */
	private IAppManager $appManager;

	/**
	 * The config service for the active token-set setting and instance id.
	 *
	 * @var IConfig
	 */
	private IConfig $config;

	/**
	 * The URL generator for the instance base URL.
	 *
	 * @var IURLGenerator
	 */
	private IURLGenerator $urlGenerator;

	/**
	 * The injectable clock for the deterministic generation timestamp.
	 *
	 * @var ITimeFactory
	 */
	private ITimeFactory $timeFactory;

	/**
	 * Constructor.
	 *
	 * @param ContrastService $contrast The WCAG contrast math service.
	 * @param CssParserService $cssParser The shared CSS custom-property parser.
	 * @param CustomOverridesService $overridesService The custom-overrides read service.
	 * @param DesignSystemService $designSystemService The shipped token-set metadata service.
	 * @param CustomTokenSetService $customSetService The custom token-set manifest service.
	 * @param IAppManager $appManager The app manager.
	 * @param IConfig $config The config service.
	 * @param IURLGenerator $urlGenerator The URL generator.
	 * @param ITimeFactory $timeFactory The injectable clock.
	 */
	public function __construct(
		ContrastService $contrast,
		CssParserService $cssParser,
		CustomOverridesService $overridesService,
		DesignSystemService $designSystemService,
		CustomTokenSetService $customSetService,
		IAppManager $appManager,
		IConfig $config,
		IURLGenerator $urlGenerator,
		ITimeFactory $timeFactory,
	) {
		$this->contrast = $contrast;
		$this->cssParser = $cssParser;
		$this->overridesService = $overridesService;
		$this->designSystemService = $designSystemService;
		$this->customSetService = $customSetService;
		$this->appManager = $appManager;
		$this->config = $config;
		$this->urlGenerator = $urlGenerator;
		$this->timeFactory = $timeFactory;
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
		$appPath = $this->appManager->getAppPath('thematiq');

		$activeTokenSetId = $this->config->getAppValue(Application::APP_ID, 'token_set', 'nextcloud');
		$tokenSetMeta = $this->resolveTokenSetMeta(tokenSetId: $activeTokenSetId);
		$designSystemId = ($tokenSetMeta['design_system'] ?? 'nldesign');

		// The runtime (Application::injectThemeCSS()) only loads the
		// --nldesign-* design-system layers when the design system is not
		// "none" — mirror that gate exactly so a stock-Nextcloud active set
		// is never scored against values the browser never received.
		$declarations = [];
		if ($designSystemId !== 'none') {
			$declarations = array_merge(
				$this->parseCssFile(path: $appPath . '/css/systems/' . $designSystemId . '/defaults.css'),
				($this->cssParser->parseDeclarations(content: (string)$this->setCss(appPath: $appPath, name: 'css/tokens/' . $activeTokenSetId . '.css')) ?? [])
			);
		}

		$mapping = $this->parseCssFile(path: $appPath . '/css/systems/nldesign/overrides.css');
		$customOverrides = $this->overridesService->read();

		$pairs = [];
		foreach (self::PAIR_MATRIX as $pairDef) {
			$pairs[] = $this->evaluatePair(
				pairDef: $pairDef,
				customOverrides: $customOverrides,
				mapping: $mapping,
				declarations: $declarations,
				tokenSetMeta: $tokenSetMeta,
				designSystemId: $designSystemId
			);
		}

		return [
			'scope' => self::SCOPE_STATEMENT,
			'metadata' => $this->buildMetadata(
				tokenSetId: $activeTokenSetId,
				tokenSetMeta: $tokenSetMeta,
				designSystemId: $designSystemId,
				customOverrides: $customOverrides
			),
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
		$meta = $data['metadata'];

		$lines = [];
		$lines[] = '<!-- GENERATED by ComplianceReportService::renderMarkdown() — do not edit by hand. -->';
		$lines[] = '';
		$lines[] = '# NL Design Compliance Evidence Report';
		$lines[] = '';
		$lines[] = self::SCOPE_STATEMENT;
		$lines[] = '';
		$lines[] = '## Metadata';
		$lines[] = '';
		$lines[] = '- Instance id: ' . $meta['instanceId'];
		$lines[] = '- Instance URL: ' . $meta['instanceUrl'];
		$lines[] = '- nldesign app version: ' . $meta['appVersion'];
		$lines[] = '- Nextcloud version: ' . $meta['nextcloudVersion'];
		$lines[] = '- Active token set: ' . $meta['tokenSet']['id']
			. ' ("' . $meta['tokenSet']['name'] . '", version '
			. $meta['tokenSet']['version'] . ')';
		$lines[] = '- Design system: ' . $meta['designSystem'];
		$lines[] = '- Generated at: ' . $meta['generatedAt'];
		$lines[] = '- Custom overrides SHA-256: ' . $meta['overridesHash'];
		$lines[] = '';
		$lines[] = '## Pair matrix (' . count($data['pairs']) . ' pairs)';
		$lines[] = '';
		$lines[] = '| # | Foreground | Background | FG value | BG value | Ratio | Threshold | Basis | Verdict |';
		$lines[] = '|---|------------|------------|----------|----------|------:|----------:|-------|:-------:|';

		$notes = [];
		foreach ($data['pairs'] as $index => $pair) {
			$lines[] = sprintf(
				'| %d | `%s` | `%s` | %s | %s | %s | %s | %s | %s |',
				($index + 1),
				$pair['foreground'],
				$pair['background'],
				($pair['foregroundValue'] ?? '—'),
				($pair['backgroundValue'] ?? '—'),
				$this->formatRatio(ratio: $pair['ratio']),
				$this->formatThreshold(threshold: $pair['threshold']),
				$pair['basis'],
				$pair['verdict']
			);

			if ($pair['note'] !== null) {
				$notes[] = '- Pair ' . ($index + 1) . ': ' . $pair['note'];
			}
		}

		$lines[] = '';

		if (empty($notes) === false) {
			$lines[] = '### Notes';
			$lines[] = '';
			array_push($lines, ...$notes);
			$lines[] = '';
		}

		$summary = $data['summary'];
		$lines[] = '## Summary';
		$lines[] = '';
		$lines[] = '- Passed: ' . $summary['passed'];
		$lines[] = '- Failed: ' . $summary['failed'];
		$lines[] = '- Unevaluated: ' . $summary['unevaluated'];
		$lines[] = '- Overall verdict: ' . $summary['verdict'];
		$lines[] = '';

		return implode("\n", $lines) . "\n";
	}//end renderMarkdown()

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
		$shipped = $this->designSystemService->getTokenSetMeta(tokenSetId: $tokenSetId);
		if (empty($shipped) === false) {
			return $shipped;
		}

		$custom = $this->customSetService->getManifest();

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

		return ($this->cssParser->parseDeclarations(content: $content) ?? []);
	}//end parseCssFile()

	/**
	 * Evaluate a single pair-matrix entry into its full report row.
	 *
	 * @param array{fg: string, bg: string, threshold: float, basis: string} $pairDef The matrix entry.
	 * @param array<string, string> $customOverrides The custom-overrides layer.
	 * @param array<string, string> $mapping The --color-* => --nldesign-* mapping.
	 * @param array<string, string> $declarations The layered --nldesign-* declarations.
	 * @param array<string, mixed> $tokenSetMeta The active token set's metadata.
	 * @param string $designSystemId The active design system id.
	 *
	 * @return array<string, mixed> The evaluated pair row.
	 *
	 * @spec openspec/specs/compliance-evidence/spec.md
	 */
	private function evaluatePair(
		array $pairDef,
		array $customOverrides,
		array $mapping,
		array $declarations,
		array $tokenSetMeta,
		string $designSystemId,
	): array {
		$fgResolved = $this->resolveColorToken(
			token: $pairDef['fg'],
			customOverrides: $customOverrides,
			mapping: $mapping,
			declarations: $declarations,
			designSystemId: $designSystemId
		);

		$bgResolved = $this->resolveColorToken(
			token: $pairDef['bg'],
			customOverrides: $customOverrides,
			mapping: $mapping,
			declarations: $declarations,
			designSystemId: $designSystemId
		);
		if ($pairDef['bg'] === self::MAIN_BACKGROUND_TOKEN) {
			$bgResolved = $this->resolveMainBackground(
				customOverrides: $customOverrides,
				declarations: $declarations,
				tokenSetMeta: $tokenSetMeta
			);
		}

		$fgColor = null;
		if ($fgResolved['value'] !== null) {
			$fgColor = $this->contrast->parseColor(value: $fgResolved['value']);
		}

		$bgColor = null;
		if ($bgResolved['value'] !== null) {
			$bgColor = $this->contrast->parseColor(value: $bgResolved['value']);
		}

		$entry = [
			'foreground' => $pairDef['fg'],
			'background' => $pairDef['bg'],
			'foregroundValue' => $fgResolved['value'],
			'backgroundValue' => $bgResolved['value'],
			'ratio' => null,
			'threshold' => $pairDef['threshold'],
			'basis' => $pairDef['basis'],
			'verdict' => 'unevaluated',
			'note' => null,
		];

		if ($fgColor === null || $bgColor === null) {
			$entry['note'] = $this->describeUnresolvedPair(
				fgToken: $pairDef['fg'],
				fgResolved: $fgResolved,
				fgColor: $fgColor,
				bgToken: $pairDef['bg'],
				bgResolved: $bgResolved,
				bgColor: $bgColor
			);

			return $entry;
		}

		// Measured as it renders, so a translucent colour is blended first. Both
		// values parsed above, so measure() cannot answer null here.
		$ratio = round(($this->contrast->measure(foreground: (string)$fgResolved['value'], background: (string)$bgResolved['value']) ?? 0.0), 2);

		$entry['ratio'] = $ratio;
		$entry['verdict'] = 'fail';
		if ($ratio >= $pairDef['threshold']) {
			$entry['verdict'] = 'pass';
		}

		return $entry;
	}//end evaluatePair()

	/**
	 * Resolve a --color-* pair token: custom override first, else the mapped
	 * --nldesign-* source token (per css/systems/nldesign/overrides.css),
	 * resolved transitively against the layered declarations.
	 *
	 * @param string $token The --color-* token name.
	 * @param array<string, string> $customOverrides The custom-overrides layer.
	 * @param array<string, string> $mapping The --color-* => --nldesign-* mapping.
	 * @param array<string, string> $declarations The layered --nldesign-* declarations.
	 * @param string $designSystemId The active design system id (for the diagnostic note).
	 *
	 * @return array{value: string|null, unresolved: string|null} The resolution result.
	 *
	 * @spec openspec/specs/compliance-evidence/spec.md
	 */
	private function resolveColorToken(
		string $token,
		array $customOverrides,
		array $mapping,
		array $declarations,
		string $designSystemId,
	): array {
		if (array_key_exists($token, $customOverrides) === true) {
			return ['value' => $customOverrides[$token], 'unresolved' => null];
		}

		if (array_key_exists($token, $mapping) === false) {
			return [
				'value' => null,
				'unresolved' => $token . ' (no nldesign token mapping for design system "' . $designSystemId . '")',
			];
		}

		return $this->resolveVarChain(value: $mapping[$token], declarations: $declarations);
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
	 * @param string $fgToken The foreground token name.
	 * @param array{value: string|null, unresolved: string|null} $fgResolved The foreground resolution result.
	 * @param array{0: int, 1: int, 2: int}|null $fgColor The parsed foreground colour, or null.
	 * @param string $bgToken The background token name.
	 * @param array{value: string|null, unresolved: string|null} $bgResolved The background resolution result.
	 * @param array{0: int, 1: int, 2: int}|null $bgColor The parsed background colour, or null.
	 *
	 * @return string The human-readable diagnostic note.
	 *
	 * @spec openspec/specs/compliance-evidence/spec.md
	 */
	private function describeUnresolvedPair(
		string $fgToken,
		array $fgResolved,
		?array $fgColor,
		string $bgToken,
		array $bgResolved,
		?array $bgColor,
	): string {
		$problems = [];

		if ($fgColor === null) {
			$problems[] = $this->describeUnresolvedSide(token: $fgToken, resolved: $fgResolved);
		}

		if ($bgColor === null) {
			$problems[] = $this->describeUnresolvedSide(token: $bgToken, resolved: $bgResolved);
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
	 * @param string $tokenSetId The active token set id.
	 * @param array<string, mixed> $tokenSetMeta The active token set's metadata.
	 * @param string $designSystemId The active design system id.
	 * @param array<string, string> $customOverrides The custom-overrides layer.
	 *
	 * @return array<string, mixed> The metadata block.
	 *
	 * @spec openspec/specs/compliance-evidence/spec.md
	 */
	private function buildMetadata(string $tokenSetId, array $tokenSetMeta, string $designSystemId, array $customOverrides): array {
		return [
			'instanceId' => $this->config->getSystemValue('instanceid', ''),
			'instanceUrl' => $this->urlGenerator->getBaseUrl(),
			'appVersion' => $this->appManager->getAppVersion('thematiq'),
			'nextcloudVersion' => $this->resolveNextcloudVersion(),
			'tokenSet' => [
				'id' => $tokenSetId,
				'name' => ($tokenSetMeta['name'] ?? $tokenSetId),
				'version' => ($tokenSetMeta['version'] ?? 'unversioned'),
			],
			'designSystem' => $designSystemId,
			'generatedAt' => gmdate('Y-m-d\TH:i:s\Z', $this->timeFactory->getTime()),
			'overridesHash' => $this->computeOverridesHash(overrides: $customOverrides),
		];
	}//end buildMetadata()

	/**
	 * Resolve the Nextcloud server version string.
	 *
	 * Uses the deprecated array-returning \OCP\Util::getVersion() rather than
	 * the newer \OCP\ServerVersion (NC 31.0.0+) because this app supports NC
	 * back to 28 (appinfo/info.xml) and a type-hinted ServerVersion
	 * constructor dependency would break DI autowiring on 28-30. Falls back
	 * to "unknown" when no live Nextcloud server container is present (e.g. a
	 * standalone PHPUnit run without a full NC bootstrap) — that never
	 * happens for the real endpoint/occ command, only in isolated unit tests.
	 *
	 * @return string The Nextcloud version (e.g. "34.0.0.5"), or "unknown".
	 *
	 * @SuppressWarnings(PHPMD.StaticAccess) - see rationale above.
	 *
	 * @spec openspec/specs/compliance-evidence/spec.md
	 */
	private function resolveNextcloudVersion(): string {
		try {
			return implode('.', \OCP\Util::getVersion());
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
	 * Format a ratio for Markdown (2 decimals, em dash when unevaluated).
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
	 * Format a threshold for Markdown.
	 *
	 * @param float $threshold The threshold.
	 *
	 * @return string The formatted cell value.
	 */
	private function formatThreshold(float $threshold): string {
		return number_format($threshold, 1, '.', '') . ':1';
	}//end formatThreshold()

	/**
	 * A set file's CSS: an uploaded (`custom-`) set's from its service, a shipped one's from the release.
	 *
	 * @param string $appPath The app directory.
	 * @param string $name    The app-relative name, such as `css/tokens/utrecht.css`.
	 *
	 * @return string|null The CSS, or null when the file does not exist.
	 *
	 * @spec openspec/changes/runtime-files-in-appdata/specs/runtime-file-storage/spec.md
	 */
	private function setCss(string $appPath, string $name): ?string {
		// An uploaded set lives in app data; its own service reads it.
		if (preg_match('#^css/tokens/(custom-[a-z0-9-]+)\.css$#', $name, $match) === 1) {
			return $this->customSetService->getRawContent(id: $match[1]);
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
