<?php

/**
 * Token-Set Vocabulary Audit Service.
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
 * @spec openspec/specs/token-sets/spec.md#requirement-shipped-token-set-vocabulary-completeness
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * Audits whether a shipped token set actually defines the vocabulary the
 * design system reads.
 *
 * The contrast audit (`ShippedTokenSetAuditService`) answers "are this set's
 * colours legible?". This service answers the prior question: "does this set
 * define anything the theme reads at all?" — the failure mode that makes 41 of
 * the 48 shipped sets render as Rijkshuisstijl instead of their own brand,
 * because the cascade falls straight through `css/systems/nldesign/defaults.css`.
 *
 * Three mechanical rules, none of them a colour judgement:
 *
 * 1. `missingRequired` — the required semantic tokens (`self::REQUIRED_TOKENS`)
 *    the set file itself does not declare. Deliberately evaluated against the
 *    set file ALONE, not layered over `defaults.css`: layering is exactly what
 *    hides the defect, since every token then resolves to a Rijkshuisstijl
 *    value. This is why it does not reuse
 *    `ShippedTokenSetAuditService::resolveDeclarations()`, which layers by
 *    design because a contrast ratio has to be computed on the value that
 *    actually renders.
 * 2. `foreignNldesignNames` — `--nldesign-*` names the set declares that no CSS
 *    layer in the app declares a default for or reads. Raw upstream palette
 *    steps must live under the brand prefix (`--zwolle-color-blue-40`), never
 *    under `--nldesign-`, where they masquerade as app vocabulary while nothing
 *    can consume them.
 * 3. `primaryMismatch` — `--nldesign-color-primary` disagrees with the set's
 *    `token-sets.json` `theming.primary_color`. One value, one source of truth:
 *    the CSS paints the app, the manifest value is pushed into Nextcloud core
 *    theming, and a divergence means the two halves of the same theme disagree.
 *
 * Given an explicit app-root path (not an `IAppManager`) for the same reason
 * `ShippedTokenSetAuditService` is: the runtime passes the resolved app path
 * and the standalone PHPUnit gate passes the repository root, neither needing a
 * Nextcloud server.
 *
 * @phpstan-type VocabularyAuditResult array{
 *     id: string,
 *     designSystem: string,
 *     auditable: bool,
 *     missingRequired: array<int, string>,
 *     foreignNldesignNames: array<int, string>,
 *     primaryMismatch: bool,
 *     declaredPrimary: string|null,
 *     cssPrimary: string|null,
 *     complete: bool
 * }
 * @psalm-type   VocabularyAuditResult array{
 *     id: string,
 *     designSystem: string,
 *     auditable: bool,
 *     missingRequired: array<int, string>,
 *     foreignNldesignNames: array<int, string>,
 *     primaryMismatch: bool,
 *     declaredPrimary: string|null,
 *     cssPrimary: string|null,
 *     complete: bool
 * }
 *
 * @spec openspec/specs/token-sets/spec.md#requirement-shipped-token-set-vocabulary-completeness
 *
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity) - three independent audit rules plus the CSS walk they read from; each rule is simple
 *   and they are kept together because the PHPUnit gate and the Node CLI must apply exactly the same set.
 */
class TokenSetVocabularyAuditService {

	/**
	 * The required semantic vocabulary a correct shipped set declares itself.
	 *
	 * Kept byte-identical to `scripts/audit-token-sets.mjs`'s
	 * `REQUIRED_TOKENS` — the Node CLI is a mirror of this service, so the two
	 * lists change together or not at all.
	 *
	 * @var array<int, string>
	 */
	public const REQUIRED_TOKENS = [
		'--nldesign-color-primary',
		'--nldesign-color-primary-text',
		'--nldesign-color-primary-hover',
		'--nldesign-color-primary-light',
		'--nldesign-color-primary-light-hover',
		'--nldesign-color-header-background',
		'--nldesign-color-header-text',
		'--nldesign-color-nav-background',
		'--nldesign-color-text',
		'--nldesign-color-text-muted',
		'--nldesign-color-border',
		'--nldesign-color-border-dark',
		'--nldesign-color-link',
		'--nldesign-color-link-hover',
		'--nldesign-color-error',
		'--nldesign-color-error-rgb',
		'--nldesign-color-warning',
		'--nldesign-color-warning-rgb',
		'--nldesign-color-success',
		'--nldesign-color-success-rgb',
		'--nldesign-color-info',
		'--nldesign-color-info-rgb',
		'--nldesign-font-family',
		'--nldesign-border-radius',
		'--nldesign-border-radius-small',
		'--nldesign-border-radius-large',
	];

	/**
	 * Design systems that read their OWN token vocabulary instead of
	 * `--nldesign-*`, keyed by design-system id: the prefix their stylesheets
	 * read and the token that carries the brand primary.
	 *
	 * A set of such a system is audited against that vocabulary (thematiq#1022):
	 * there is no defaults layer under it, so every name its stylesheets read
	 * and no layer declares is required, and a declared name nothing reads is
	 * dead weight. Kept byte-identical to `scripts/audit-token-sets.mjs`'s
	 * `OWN_VOCABULARIES`.
	 *
	 * @var array<string, array{prefix: string, primary: string}>
	 */
	public const OWN_VOCABULARIES = [
		'summer-breeze' => ['prefix' => '--summer-', 'primary' => '--summer-color-primary'],
	];

	/**
	 * The CSS custom-property parser (shared with the contrast audit and the
	 * token editor, so "what counts as a declaration" has one definition).
	 *
	 * @var CssParserService
	 */
	private CssParserService $parser;

	/**
	 * Memoised per-app-root vocabulary, so auditing all 48 sets walks the CSS
	 * tree once rather than 48 times.
	 *
	 * @var array<string, array<int, string>>
	 */
	private array $vocabularyCache = [];

	/**
	 * Memoised per-app-root set of design-system ids that read `--nldesign-*`.
	 *
	 * @var array<string, array<int, string>>
	 */
	private array $consumingCache = [];

	/**
	 * Memoised per-app-root, per-prefix names the non-token CSS layers read
	 * (`var()`) and declare, for the own-vocabulary systems.
	 *
	 * @var array<string, array{read: array<int, string>, declared: array<int, string>}>
	 */
	private array $ownVocabularyCache = [];

	/**
	 * Constructor.
	 *
	 * @param CssParserService $parser The CSS custom-property parser.
	 */
	public function __construct(CssParserService $parser) {
		$this->parser = $parser;
	}//end __construct()

	/**
	 * Audit one shipped token set.
	 *
	 * @param string $appPath The app root path.
	 * @param string $id The token set id.
	 * @param array<string, mixed> $meta The set's manifest entry (design_system, theming).
	 *
	 * The per-set vocabulary verdict.
	 *
	 * @return VocabularyAuditResult
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-shipped-token-set-vocabulary-completeness
	 */
	public function auditSet(string $appPath, string $id, array $meta): array {
		$set = new VocabularySet(id: $id, meta: $meta);
		if (isset(self::OWN_VOCABULARIES[$set->designSystem()]) === true) {
			return $this->auditOwnVocabulary(appPath: $appPath, set: $set);
		}

		$auditable = in_array($set->designSystem(), $this->nldesignConsumingSystems(appPath: $appPath), true);
		$declarations = $this->declarationsOf(filePath: $appPath . '/css/tokens/' . $id . '.css');
		$verdict = $this->vocabularyFindings(appPath: $appPath, declarations: $declarations)
			+ $this->primaryFindings(meta: $meta, cssValue: ($declarations['--nldesign-color-primary'] ?? null));

		// A set whose design system never reads the --nldesign-* vocabulary has
		// no findings to report, only an absent verdict. Leaving the computed
		// lists in place said "26 required tokens are missing" about a set that
		// was never judged, which reads as a defect rather than as a set the
		// audit does not apply to.
		if ($auditable === false) {
			$verdict = array_merge($verdict, ['missingRequired' => [], 'foreignNldesignNames' => [], 'primaryMismatch' => false]);
		}

		return $this->verdictOf(head: ['id' => $id, 'designSystem' => $set->designSystem(), 'auditable' => $auditable], verdict: $verdict);
	}//end auditSet()

	/**
	 * A set's verdict: who it is, its findings, and whether it is complete.
	 *
	 * @param array{id: string, designSystem: string, auditable: bool} $head The set id, its design system and whether it is audited.
	 * @param array<string, mixed> $verdict The findings.
	 *
	 * @return VocabularyAuditResult
	 */
	private function verdictOf(array $head, array $verdict): array {
		return $head + $verdict
			+ ['complete' => ($verdict['missingRequired'] === [] && $verdict['foreignNldesignNames'] === [] && $verdict['primaryMismatch'] === false)];
	}//end verdictOf()

	/**
	 * The required tokens a set file leaves out, and the names it declares outside the vocabulary.
	 *
	 * @param string $appPath The app root path.
	 * @param array<string, string> $declarations The set file's --nldesign-* declarations.
	 *
	 * @return array{missingRequired: array<int, string>, foreignNldesignNames: array<int, string>}
	 */
	private function vocabularyFindings(string $appPath, array $declarations): array {
		$declaredNames = array_keys($declarations);

		$missingRequired = array_values(array_filter(
			self::REQUIRED_TOKENS,
			static fn (string $token): bool => in_array($token, $declaredNames, true) === false
		));

		$vocabulary = $this->declaredVocabulary(appPath: $appPath);
		$foreign = array_values(array_filter(
			$declaredNames,
			static fn (string $name): bool => in_array($name, $vocabulary, true) === false
		));
		sort($foreign);

		return ['missingRequired' => $missingRequired, 'foreignNldesignNames' => $foreign];
	}//end vocabularyFindings()

	/**
	 * Whether the manifest's primary colour and the set file's disagree, with both values.
	 *
	 * A missing or non-literal --nldesign-color-primary is already reported as a missing required
	 * token; only a genuine disagreement between two resolvable values is a mismatch, so the two
	 * findings never double-count the same defect.
	 *
	 * @param array<string, mixed> $meta The set's manifest entry.
	 * @param mixed $cssValue The value the set file gives its primary token, null when absent.
	 *
	 * @return array{primaryMismatch: bool, declaredPrimary: string|null, cssPrimary: string|null}
	 */
	private function primaryFindings(array $meta, mixed $cssValue): array {
		$declaredPrimary = $this->normaliseHex(value: ($meta['theming']['primary_color'] ?? null));
		$cssPrimary = $this->normaliseHex(value: $cssValue);

		return [
			'primaryMismatch' => ($declaredPrimary !== null && $cssPrimary !== null && $declaredPrimary !== $cssPrimary),
			'declaredPrimary' => $declaredPrimary,
			'cssPrimary' => $cssPrimary,
		];
	}//end primaryFindings()

	/**
	 * Audit a set of a design system that reads its own vocabulary
	 * ({@see self::OWN_VOCABULARIES}), with the same three rules:
	 *
	 * 1. `missingRequired`: every own-prefix name a non-token CSS layer reads
	 *    through `var()` and no such layer declares, that the set file does
	 *    not declare. Nothing sits under such a set, so a missing name leaves
	 *    the property it feeds invalid.
	 * 2. `foreignNldesignNames`: own-prefix names the set declares that no
	 *    non-token CSS layer declares or reads. The key keeps its name so the
	 *    admin UI reads one warning shape for every design system.
	 * 3. `primaryMismatch`: the system's primary token disagrees with the
	 *    set's `token-sets.json` `theming.primary_color`.
	 *
	 * @param string $appPath The app root path.
	 * @param VocabularySet $set The set; its design system is a key of OWN_VOCABULARIES.
	 *
	 * The per-set vocabulary verdict.
	 *
	 * @return VocabularyAuditResult
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-a-design-system-with-its-own-vocabulary-is-audited-against-it
	 */
	private function auditOwnVocabulary(string $appPath, VocabularySet $set): array {
		$vocabulary = self::OWN_VOCABULARIES[$set->designSystem()];

		$declarations = $this->declarationsOf(filePath: $appPath . '/css/tokens/' . $set->id . '.css', prefix: $vocabulary['prefix']);
		$layers = $this->ownVocabulary(appPath: $appPath, prefix: $vocabulary['prefix']);
		$verdict = $this->ownVocabularyFindings(declaredNames: array_keys($declarations), layers: $layers)
			+ $this->primaryFindings(meta: $set->meta, cssValue: ($declarations[$vocabulary['primary']] ?? null));

		return $this->verdictOf(head: ['id' => $set->id, 'designSystem' => $set->designSystem(), 'auditable' => true], verdict: $verdict);
	}//end auditOwnVocabulary()

	/**
	 * The names the layers read and nobody declares that the set leaves out,
	 * and the names the set declares that no layer knows.
	 *
	 * @param array<int, string> $declaredNames The own-prefix names the set file declares.
	 * @param array{read: array<int, string>, declared: array<int, string>} $layers The names the layers read and declare.
	 *
	 * @return array{missingRequired: array<int, string>, foreignNldesignNames: array<int, string>}
	 */
	private function ownVocabularyFindings(array $declaredNames, array $layers): array {
		$required = array_values(array_diff($layers['read'], $layers['declared']));
		$missingRequired = array_values(array_diff($required, $declaredNames));
		sort($missingRequired);

		$known = array_merge($layers['read'], $layers['declared']);
		$foreign = array_values(array_diff($declaredNames, $known));
		sort($foreign);

		return ['missingRequired' => $missingRequired, 'foreignNldesignNames' => $foreign];
	}//end ownVocabularyFindings()

	/**
	 * The own-prefix names the non-token CSS layers read through `var()`, and
	 * the ones they declare, each sorted and unique.
	 *
	 * Walks the same files as {@see self::declaredVocabulary()}: everything
	 * under `css/` except `css/tokens/` and the runtime admin files.
	 *
	 * @param string $appPath The app root path.
	 * @param string $prefix The token prefix, e.g. `--summer-`.
	 *
	 * @return array{read: array<int, string>, declared: array<int, string>} The names.
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-a-design-system-with-its-own-vocabulary-is-audited-against-it
	 */
	public function ownVocabulary(string $appPath, string $prefix): array {
		$key = $appPath . '|' . $prefix;
		if (isset($this->ownVocabularyCache[$key]) === true) {
			return $this->ownVocabularyCache[$key];
		}

		$this->ownVocabularyCache[$key] = $this->scanOwnVocabulary(appPath: $appPath, prefix: $prefix);

		return $this->ownVocabularyCache[$key];
	}//end ownVocabulary()

	/**
	 * Read every non-token CSS layer for the own-prefix names it reads and declares.
	 *
	 * @param string $appPath The app root path.
	 * @param string $prefix The token prefix, e.g. `--summer-`.
	 *
	 * @return array{read: array<int, string>, declared: array<int, string>} The names, each sorted.
	 */
	private function scanOwnVocabulary(string $appPath, string $prefix): array {
		$quoted = preg_quote($prefix, '/');
		$read = [];
		$declared = [];
		foreach ((new LayerCssFiles())->under(directory: $appPath . '/css') as $file) {
			$css = $this->stripComments(css: $this->readFile(filePath: $file));
			$read += $this->matchedNames(css: $css, pattern: '/var\(\s*(' . $quoted . '[A-Za-z0-9_-]+)/');
			$declared += $this->matchedNames(css: $css, pattern: '/(' . $quoted . '[A-Za-z0-9_-]+)\s*:/');
		}

		return ['read' => $this->sortedNames(names: $read), 'declared' => $this->sortedNames(names: $declared)];
	}//end scanOwnVocabulary()

	/**
	 * The names a pattern's first group captures in some CSS, as a set.
	 *
	 * @param string $css The CSS, comments stripped.
	 * @param string $pattern The pattern; its first group is the name.
	 *
	 * @return array<string, true> The names, in the order first found.
	 */
	private function matchedNames(string $css, string $pattern): array {
		if (preg_match_all($pattern, $css, $matches) > 0) {
			return array_fill_keys($matches[1], true);
		}

		return [];
	}//end matchedNames()

	/**
	 * The names of a set, sorted.
	 *
	 * @param array<string, true> $names The names.
	 *
	 * @return array<int, string> The names, sorted.
	 */
	private function sortedNames(array $names): array {
		$sorted = array_keys($names);
		sort($sorted);

		return $sorted;
	}//end sortedNames()

	/**
	 * Audit every shipped token set in `css/tokens/`, ordered by id.
	 *
	 * The manifest is read for metadata only — the filesystem stays the source
	 * of truth for which sets exist, exactly as `TokenSetService` discovery
	 * does.
	 *
	 * @param string $appPath The app root path.
	 *
	 * One verdict per shipped set, ordered deterministically by id.
	 *
	 * @return array<int, VocabularyAuditResult>
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-shipped-token-set-vocabulary-completeness
	 */
	public function auditAll(string $appPath): array {
		$manifest = $this->readManifest(appPath: $appPath);

		$files = glob($appPath . '/css/tokens/*.css');
		if (is_array($files) === false) {
			return [];
		}

		$results = [];
		foreach ($files as $file) {
			$id = basename($file, '.css');
			$results[] = $this->auditSet(appPath: $appPath, id: $id, meta: ($manifest[$id] ?? []));
		}

		usort($results, static fn (array $a, array $b): int => strcmp($a['id'], $b['id']));

		return $results;
	}//end auditAll()

	/**
	 * Compute the non-blocking "incomplete set" warning for one shipped set, in
	 * the exact shape `TokenSetService::applyWarnings()` already passes to the
	 * admin UI (`{pair, ...}`-style associative entries), so the dropdown badge
	 * and the apply dialog need no second warnings channel.
	 *
	 * Returns an empty array for a complete set, a set whose design system does
	 * not read `--nldesign-*` tokens, and a custom upload (whose warnings are
	 * uploader-supplied).
	 *
	 * @param string $appPath The app root path.
	 * @param string $id The token set id.
	 * @param array<string, mixed> $meta The set's manifest entry.
	 *
	 * The warning entries (empty when the set is complete).
	 *
	 * @return array<int, array<string, mixed>>
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-incomplete-sets-are-surfaced-in-the-admin-dropdown
	 */
	public function warningsFor(string $appPath, string $id, array $meta): array {
		$result = $this->auditSet(appPath: $appPath, id: $id, meta: $meta);
		if ($result['complete'] === true) {
			return [];
		}

		return [
			[
				'kind' => 'incomplete',
				'missing' => $result['missingRequired'],
				'foreign' => $result['foreignNldesignNames'],
				'primaryMismatch' => $result['primaryMismatch'],
				'declaredPrimary' => $result['declaredPrimary'],
				'cssPrimary' => $result['cssPrimary'],
			],
		];
	}//end warningsFor()

	/**
	 * The declared `--nldesign-*` vocabulary: every name any non-token-set CSS
	 * layer in the app declares a default for or reads.
	 *
	 * Wider than `css/systems/nldesign/defaults.css` and `utrecht-bridge.css`
	 * on purpose:
	 * `theme.css`, `overrides.css`, `element-overrides.css`, `public-bridge.css`
	 * and the non-nldesign systems' own layers read names those two files never
	 * mention (`--nldesign-logo-url`, `--nldesign-color-background`,
	 * `--nldesign-color-nav-text`, ...), all of which hand-authored sets
	 * legitimately set. A name is in the vocabulary iff SOMETHING can consume
	 * it; anything else is dead weight, which is precisely what rule 2 is
	 * looking for.
	 *
	 * @param string $appPath The app root path.
	 *
	 * @return array<int, string> The vocabulary token names.
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-shipped-token-set-vocabulary-completeness
	 */
	public function declaredVocabulary(string $appPath): array {
		if (isset($this->vocabularyCache[$appPath]) === true) {
			return $this->vocabularyCache[$appPath];
		}

		$names = [];
		foreach ((new LayerCssFiles())->under(directory: $appPath . '/css') as $file) {
			foreach ($this->nldesignNames(css: $this->readFile(filePath: $file)) as $name) {
				$names[$name] = true;
			}
		}

		$vocabulary = array_keys($names);
		sort($vocabulary);
		$this->vocabularyCache[$appPath] = $vocabulary;

		return $vocabulary;
	}//end declaredVocabulary()

	/**
	 * The design-system ids whose own stylesheet stack reads `--nldesign-*`
	 * tokens at all.
	 *
	 * A set belonging to any other system cannot be judged against this
	 * vocabulary: `none` (stock Nextcloud, loads no stylesheet) is reported as
	 * not auditable, and `summer-breeze` (its `theme.css` and
	 * `element-overrides.css` reference no `--nldesign-*` name) is audited
	 * against its own `--summer-*` vocabulary instead
	 * ({@see self::OWN_VOCABULARIES}). `high-contrast`, `lasuite` and
	 * `cunningham` DO read the vocabulary through their bridge layers, so
	 * their sets are audited like any nldesign set.
	 *
	 * @param string $appPath The app root path.
	 *
	 * @return array<int, string> The consuming design-system ids.
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-shipped-token-set-vocabulary-completeness
	 */
	public function nldesignConsumingSystems(string $appPath): array {
		if (isset($this->consumingCache[$appPath]) === true) {
			return $this->consumingCache[$appPath];
		}

		$systems = [];
		foreach ($this->readDesignSystems(appPath: $appPath) as $system) {
			if ($this->readsNldesign(appPath: $appPath, system: $system) === true) {
				$systems[] = $system['id'];
			}
		}

		sort($systems);
		$this->consumingCache[$appPath] = $systems;

		return $systems;
	}//end nldesignConsumingSystems()

	/**
	 * Whether a design system's stylesheets read the --nldesign-* vocabulary.
	 *
	 * @param string $appPath The app root path.
	 * @param mixed $system The design-system entry from design-systems.json.
	 *
	 * @return bool True when one of its stylesheets names a --nldesign-* token.
	 */
	private function readsNldesign(string $appPath, mixed $system): bool {
		if ($this->isDesignSystem(system: $system) === false) {
			return false;
		}

		$stylesheets = ($system['stylesheets'] ?? []);
		if (is_array($stylesheets) === false) {
			return false;
		}

		return $this->anyStylesheetReadsNldesign(appPath: $appPath, stylesheets: $stylesheets);
	}//end readsNldesign()

	/**
	 * Whether a design-system entry is a map with a string id.
	 *
	 * @param mixed $system The entry.
	 *
	 * @return bool True when it is.
	 */
	private function isDesignSystem(mixed $system): bool {
		return is_array($system) === true && is_string($system['id'] ?? null) === true;
	}//end isDesignSystem()

	/**
	 * Whether any of the stylesheets, in order, exists and names a --nldesign-* token.
	 *
	 * @param string $appPath The app root path.
	 * @param array<mixed> $stylesheets The stylesheet names, relative to `css/` without `.css`.
	 *
	 * @return bool True at the first one that does.
	 */
	private function anyStylesheetReadsNldesign(string $appPath, array $stylesheets): bool {
		foreach ($stylesheets as $stylesheet) {
			if (is_string($stylesheet) === false) {
				continue;
			}

			$path = $appPath . '/css/' . $stylesheet . '.css';
			if (is_file($path) === true && $this->nldesignNames(css: $this->readFile(filePath: $path)) !== []) {
				return true;
			}
		}

		return false;
	}//end anyStylesheetReadsNldesign()

	/**
	 * Every `--nldesign-*` name appearing anywhere in the given CSS —
	 * declarations AND `var()` references alike, since a name a layer reads is
	 * just as much part of the vocabulary as a name a layer defaults.
	 *
	 * @param string $css The CSS source.
	 *
	 * @return array<int, string> The token names (may contain duplicates).
	 */
	private function nldesignNames(string $css): array {
		// Same character class CssParserService::parseDeclarations() uses
		// (`[\w-]+`), so a camelCase name is scanned into the vocabulary exactly
		// as it is parsed out of a token set. Narrowing this to lowercase made
		// nijmegen's upstream `--nldesign-tokenSetOrder-0` metadata leak read as
		// "declared but unknown" for the wrong reason.
		$stripped = $this->stripComments(css: $css);
		if (preg_match_all('/--nldesign-[A-Za-z0-9_-]+/', $stripped, $matches) === 0) {
			return [];
		}

		return $matches[0];
	}//end nldesignNames()

	/**
	 * The custom properties under one prefix (`--nldesign-` unless told
	 * otherwise) a CSS file DECLARES, name => value.
	 *
	 * @param string $filePath The absolute file path.
	 * @param string $prefix The token prefix to keep.
	 *
	 * @return array<string, string> The declarations (empty when absent).
	 */
	private function declarationsOf(string $filePath, string $prefix = '--nldesign-'): array {
		// Comments are stripped BEFORE parsing: CssParserService::parseDeclarations()
		// does not strip them, so a commented-out `--nldesign-color-primary: red;`
		// would otherwise count as a declaration and hide a missing token. The Node
		// mirror (scripts/audit-token-sets.mjs) strips them for the same reason.
		$css = $this->stripComments(css: $this->readFile(filePath: $filePath));
		$declarations = ($this->parser->parseDeclarations(content: $css) ?? []);

		return array_filter(
			$declarations,
			static fn (string $name): bool => str_starts_with($name, $prefix),
			ARRAY_FILTER_USE_KEY
		);
	}//end declarationsOf()

	/**
	 * Strip CSS comments so a commented-out token never counts (same approach
	 * as `TokenCssShapeTest` and `CustomTokenSetValidator`).
	 *
	 * @param string $css The CSS source.
	 *
	 * @return string The comment-free CSS.
	 */
	private function stripComments(string $css): string {
		return (string)preg_replace('#/\*.*?\*/#s', '', $css);
	}//end stripComments()

	/**
	 * Read a file, returning an empty string when it is absent or unreadable.
	 *
	 * @param string $filePath The absolute file path.
	 *
	 * @return string The file contents.
	 */
	private function readFile(string $filePath): string {
		if (is_file($filePath) === false) {
			return '';
		}

		$content = file_get_contents($filePath);
		if ($content === false) {
			return '';
		}

		return $content;
	}//end readFile()

	/**
	 * Normalise a hex colour to lowercase 6-digit form.
	 *
	 * @param mixed $value The candidate colour value.
	 *
	 * @return string|null The normalised hex, or null when not a hex literal.
	 */
	private function normaliseHex(mixed $value): ?string {
		if (is_string($value) === false) {
			return null;
		}

		$candidate = strtolower(trim($value));
		if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/', $candidate, $matches) === 0) {
			return null;
		}

		$digits = $matches[1];
		if (strlen($digits) === 3) {
			$digits = $digits[0] . $digits[0] . $digits[1] . $digits[1] . $digits[2] . $digits[2];
		}

		return '#' . $digits;
	}//end normaliseHex()

	/**
	 * `token-sets.json` indexed by id (empty when the manifest is unusable —
	 * the filesystem, not the manifest, decides which sets exist).
	 *
	 * @param string $appPath The app root path.
	 *
	 * @return array<string, array<string, mixed>> The manifest entries by id.
	 */
	private function readManifest(string $appPath): array {
		$manifest = json_decode($this->readFile(filePath: $appPath . '/token-sets.json'), true);
		if (is_array($manifest) === false) {
			return [];
		}

		$byId = [];
		foreach ($manifest as $set) {
			if (is_array($set) === false || is_string($set['id'] ?? null) === false) {
				continue;
			}

			$byId[$set['id']] = $set;
		}

		return $byId;
	}//end readManifest()

	/**
	 * `design-systems.json` as a list (empty when the file is unusable).
	 *
	 * @param string $appPath The app root path.
	 *
	 * @return array<int, mixed> The design-system entries.
	 */
	private function readDesignSystems(string $appPath): array {
		$systems = json_decode($this->readFile(filePath: $appPath . '/design-systems.json'), true);
		if (is_array($systems) === false) {
			return [];
		}

		return array_values($systems);
	}//end readDesignSystems()
}//end class
