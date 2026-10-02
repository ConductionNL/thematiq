<?php

/**
 * NL Design W3C Design Tokens Mapper.
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
 * @spec openspec/specs/custom-token-sets/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * Maps a W3C Design Tokens Community Group (DTCG) JSON document — Format
 * Module v2025.10 — onto the `--nldesign-*` CSS custom-property vocabulary.
 *
 * The v2025.10 format nests `$value` / `$type` leaves inside groups, with
 * `$type` inheritable from the nearest ancestor group, `$value` aliases of
 * the form `{token.path}`, typed object value shapes (`color`, `dimension`)
 * and composite values (`typography`). This mapper implements that full
 * contract: it flattens the tree into dotted-path leaves while tracking
 * inherited types, resolves aliases transitively (with cycle/dangling/depth
 * guards), serialises each recognised type to a CSS literal, and reports
 * every non-imported token as a structured, actionable diagnostic. A single
 * token's failure never aborts the import — only malformed JSON (handled by
 * the caller) or a zero-yield result is a hard failure.
 *
 * @spec openspec/specs/custom-token-sets/spec.md
 *
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity) - This class implements the full W3C DTCG
 * v2025.10 serialisation contract: one branch per recognised token $type (color object/string,
 * dimension, fontFamily, fontWeight, composite typography, shadow, …) plus $extensions/$deprecated
 * handling. The complexity is essential to the spec surface, not accidental; the transitive alias
 * resolution — the one genuinely separable concern — already lives in DesignTokensAliasResolver.
 */
class DesignTokensMapper {

	/**
	 * The thematiq extension key a token carries its CSS name under.
	 *
	 * @var string
	 */
	private const EXTENSION = 'nl.conduction.thematiq';

	/**
	 * The v2025.10 `fontWeight` keyword => numeric CSS value table.
	 *
	 * @var array<string, int>
	 */
	private const FONT_WEIGHT_KEYWORDS = [
		'thin' => 100,
		'hairline' => 100,
		'extra-light' => 200,
		'ultra-light' => 200,
		'light' => 300,
		'normal' => 400,
		'regular' => 400,
		'book' => 400,
		'medium' => 500,
		'semi-bold' => 600,
		'demi-bold' => 600,
		'bold' => 700,
		'extra-bold' => 800,
		'ultra-bold' => 800,
		'black' => 900,
		'heavy' => 900,
		'extra-black' => 950,
		'ultra-black' => 950,
	];

	/**
	 * Composite `typography` sub-property => `--nldesign-*` target.
	 *
	 * Only `fontFamily` has a generic (non component-scoped) `--nldesign-*`
	 * target today; `fontSize`/`fontWeight`/`lineHeight` are deliberately
	 * omitted per task 1.6 (no new CSS custom properties in this change) —
	 * they are counted as skipped with their sub-path instead.
	 *
	 * @var array<string, string>
	 */
	private const TYPOGRAPHY_SUBPROPERTY_TARGETS = [
		'fontFamily' => '--nldesign-font-family',
	];

	/**
	 * Suffix-match mapping table: DTCG dotted-path suffix => --nldesign target.
	 *
	 * The longest matching suffix wins, so `color.primary-text` is preferred
	 * over `color.primary` for a `color.primary-text` path.
	 *
	 * @var array<string, string>
	 */
	private const MAPPING = [
		'color.primary-text' => '--nldesign-color-primary-text',
		'color.on-primary' => '--nldesign-color-primary-text',
		'color.primary-hover' => '--nldesign-color-primary-hover',
		'color.primary-light' => '--nldesign-color-primary-light',
		'color.primary' => '--nldesign-color-primary',
		'brand.primary' => '--nldesign-color-primary',
		'color.background' => '--nldesign-color-background',
		'color.text' => '--nldesign-color-text',
		'fontfamily.base' => '--nldesign-font-family',
		'typography.font-family' => '--nldesign-font-family',
		'border-radius.base' => '--nldesign-border-radius',
		'dimension.border-radius' => '--nldesign-border-radius',
	];

	/**
	 * The alias-reference resolver collaborator.
	 *
	 * @var DesignTokensAliasResolver
	 */
	private DesignTokensAliasResolver $aliasResolver;

	/**
	 * Converts every DTCG colour space to sRGB.
	 *
	 * @var ColorSpaceConverter
	 */
	private ColorSpaceConverter $colorSpaces;

	/**
	 * The colours of the current map() that were changed to fit sRGB.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private array $adapted = [];

	/**
	 * The CSS name the leaf being mapped carries in the thematiq extension, if any.
	 *
	 * @var string|null
	 */
	private ?string $extensionTarget = null;

	/**
	 * Constructor.
	 *
	 * @param DesignTokensAliasResolver|null $aliasResolver The alias resolver
	 *                                                      (defaults to a fresh instance — it is stateless and dependency-free,
	 *                                                      so DI is optional; tests may still substitute their own).
	 * @param ColorSpaceConverter|null $colorSpaces The colour space maths (stateless, so optional as well).
	 */
	public function __construct(?DesignTokensAliasResolver $aliasResolver = null, ?ColorSpaceConverter $colorSpaces = null) {
		$this->aliasResolver = ($aliasResolver ?? new DesignTokensAliasResolver());
		$this->colorSpaces = ($colorSpaces ?? new ColorSpaceConverter());
	}//end __construct()

	/**
	 * Map a parsed DTCG document onto nldesign declarations.
	 *
	 * @param array<string, mixed> $document           The decoded DTCG JSON document.
	 * @param array<int, string>   $referenceOnlyPaths Dotted paths that resolve aliases but are not emitted.
	 *
	 * @return array{
	 *     declarations: array<string, string>,
	 *     imported: int,
	 *     skipped: array<int, array{path: string, reason: string, detail?: string}>,
	 *     errors: array<int, array{path: string, reason: string, detail?: string}>,
	 *     warnings: array<int, array{path: string, message: string|null}>,
	 *     packageVersion: string|null,
	 *     adapted: array<int, array<string, mixed>>,
	 *     thematiqExport: bool
	 * } The mapped declarations plus structured import accounting.
	 *
	 * @spec openspec/specs/custom-token-sets/spec.md
	 */
	public function map(array $document, array $referenceOnlyPaths = []): array {
		$this->adapted = [];
		$leaves = [];
		$this->collectLeaves(node: $document, prefix: '', inheritedType: null, leaves: $leaves);
		$referenceOnly = array_flip($referenceOnlyPaths);

		$declarations = [];
		$skipped = [];
		$errors = [];
		$warnings = [];

		foreach ($leaves as $path => $leaf) {
			// A reference-only leaf (a Tokens Studio `source` set) stays in the alias table
			// above and is never assigned to a target.
			if (isset($referenceOnly[$path]) === true) {
				continue;
			}

			$this->processLeaf(
				path: $path,
				leaf: $leaf,
				leaves: $leaves,
				declarations: $declarations,
				skipped: $skipped,
				errors: $errors,
				warnings: $warnings
			);
		}

		// The root cssOnly map: values DTCG has no type for, written by thematiq's own export.
		// Tokens win over it; every entry passes the validator downstream like any declaration.
		$declarations += $this->cssOnly(document: $document);

		return [
			'declarations' => $declarations,
			'adapted' => $this->adapted,
			// A document thematiq itself exported: the converter keeps every name as it is.
			'thematiqExport' => is_string($document['$extensions'][self::EXTENSION]['setId'] ?? null),
			'imported' => count($declarations),
			'skipped' => $skipped,
			'errors' => $errors,
			'warnings' => $warnings,
			'packageVersion' => $this->extractPackageVersion(document: $document),
		];
	}//end map()

	/**
	 * Recursively flatten a DTCG node into dotted-path => leaf-data entries.
	 *
	 * A node is a leaf when it carries a `$value` key. Group keys build the
	 * dotted path. Keys beginning with `$` (metadata such as `$type`,
	 * `$description`, `$extensions`) are never part of the path and their
	 * subtrees are never descended into — this is what makes `$extensions`
	 * passthrough-ignore regardless of its contents.
	 *
	 * `$type` inheritance: a group's own `$type` (when declared) becomes the
	 * inherited type for every descendant that does not declare its own —
	 * the nearest ancestor declaring `$type` wins, per the v2025.10 group
	 * inheritance rule.
	 *
	 * @param mixed $node The current node (array or scalar).
	 * @param string $prefix The accumulated dotted path.
	 * @param string|null $inheritedType The nearest ancestor's declared `$type`, if any.
	 * @param array<string, array<string, mixed>> $leaves Collected path => {value, type, deprecated} (by reference).
	 *
	 * @return void
	 *
	 * @spec openspec/specs/custom-token-sets/spec.md
	 */
	private function collectLeaves($node, string $prefix, ?string $inheritedType, array &$leaves): void {
		if (is_array($node) === false) {
			return;
		}

		$ownType = null;
		if (isset($node['$type']) === true && is_string($node['$type']) === true) {
			$ownType = $node['$type'];
		}

		if (array_key_exists('$value', $node) === true) {
			$leaves[$prefix] = $this->buildLeafEntry(node: $node, ownType: $ownType, inheritedType: $inheritedType);

			return;
		}

		$effectiveInherited = ($ownType ?? $inheritedType);

		foreach ($node as $key => $child) {
			if (is_string($key) === true && str_starts_with($key, '$') === true) {
				continue;
			}

			$path = $prefix . '.' . $key;
			if ($prefix === '') {
				$path = (string)$key;
			}

			$this->collectLeaves(node: $child, prefix: $path, inheritedType: $effectiveInherited, leaves: $leaves);
		}
	}//end collectLeaves()

	/**
	 * Build a single leaf-table entry from a `$value`-bearing DTCG node.
	 *
	 * @param array<string, mixed> $node The `$value`-bearing node.
	 * @param string|null $ownType The node's own declared `$type`, if any.
	 * @param string|null $inheritedType The nearest ancestor's declared `$type`, if any.
	 *
	 * @return array{value: mixed, type: string|null, deprecated: mixed} The leaf entry.
	 *
	 * @spec openspec/specs/custom-token-sets/spec.md
	 */
	private function buildLeafEntry(array $node, ?string $ownType, ?string $inheritedType): array {
		$deprecated = null;
		if (array_key_exists('$deprecated', $node) === true) {
			$deprecated = $node['$deprecated'];
		}

		return [
			'value' => $node['$value'],
			'type' => ($ownType ?? $inheritedType),
			'deprecated' => $deprecated,
			'cssVariable' => $this->extensionName(value: ($node['$extensions'][self::EXTENSION]['cssVariable'] ?? null)),
		];
	}//end buildLeafEntry()

	/**
	 * A custom property name from the thematiq extension, or null when it is not one.
	 *
	 * @param mixed $value The extension member.
	 *
	 * @return string|null
	 */
	private function extensionName(mixed $value): ?string {
		if (is_string($value) === false || preg_match('/^--[a-z0-9]+(-[a-z0-9]+)*$/', $value) !== 1) {
			return null;
		}

		return $value;
	}//end extensionName()

	/**
	 * The root `$extensions["nl.conduction.thematiq"].cssOnly` map, names checked, values as strings.
	 *
	 * @param array<string, mixed> $document The document.
	 *
	 * @return array<string, string>
	 *
	 * @spec openspec/specs/custom-token-sets/spec.md#requirement-w3c-design-tokens-json-import
	 */
	private function cssOnly(array $document): array {
		$map = (array)($document['$extensions'][self::EXTENSION]['cssOnly'] ?? []);
		$out = [];
		foreach ($map as $name => $value) {
			if ($this->extensionName(value: (string)$name) !== null && is_scalar($value) === true) {
				$out[(string)$name] = trim((string)$value);
			}
		}

		return $out;
	}//end cssOnly()

	/**
	 * Process a single flattened leaf: resolve aliases, resolve the type,
	 * serialize the value, and record the outcome (declaration, skip, error
	 * or warning). A single leaf's failure only ever produces a diagnostic
	 * entry for that leaf — it never aborts the loop.
	 *
	 * @param string $path The leaf's dotted path.
	 * @param array<string, mixed> $leaf The leaf data ({value, type, deprecated}).
	 * @param array<string, array<string, mixed>> $leaves The full leaf table (for alias resolution).
	 * @param array<string, string> $declarations Accumulated declarations (by reference).
	 * @param array<int, array<string, string>> $skipped Accumulated skips (by reference).
	 * @param array<int, array<string, string>> $errors Accumulated errors (by reference).
	 * @param array<int, array<string, mixed>> $warnings Accumulated warnings (by reference).
	 *
	 * @return void
	 *
	 * @spec openspec/specs/custom-token-sets/spec.md
	 */
	private function processLeaf(
		string $path,
		array $leaf,
		array $leaves,
		array &$declarations,
		array &$skipped,
		array &$errors,
		array &$warnings,
	): void {
		$rawValue = $leaf['value'];
		$ownType = $leaf['type'];
		$deprecated = $leaf['deprecated'];
		$this->extensionTarget = ($leaf['cssVariable'] ?? null);

		$value = $rawValue;
		$type = $ownType;

		if (is_string($rawValue) === true && $this->aliasResolver->isAlias(value: $rawValue) === true) {
			$resolved = $this->aliasResolver->resolve(startPath: $path, leaves: $leaves);
			if ($resolved['ok'] !== true) {
				$errors[] = [
					'path' => $path,
					'reason' => $resolved['reason'],
					'detail' => ($resolved['detail'] ?? ''),
				];

				return;
			}

			$value = $resolved['value'];
			// Own declared/inherited type wins; otherwise defer to the
			// terminal alias target's resolved type (a semantic-layer alias
			// commonly declares no $type of its own, relying on its target).
			$type = ($ownType ?? $resolved['type']);
		}//end if

		if ($type === null) {
			$errors[] = ['path' => $path, 'reason' => 'missing-type'];

			return;
		}

		$this->dispatchByType(
			type: $type,
			path: $path,
			value: $value,
			deprecated: $deprecated,
			declarations: $declarations,
			skipped: $skipped,
			errors: $errors,
			warnings: $warnings
		);
	}//end processLeaf()

	/**
	 * Dispatch a leaf's resolved type to its serializer and record the
	 * outcome. Extracted from {@see self::processLeaf()} to keep each
	 * method's own cyclomatic complexity and length in check.
	 *
	 * @param string $type The resolved `$type`.
	 * @param string $path The leaf's dotted path.
	 * @param mixed $value The resolved `$value`.
	 * @param mixed $deprecated The leaf's raw `$deprecated` value, if any.
	 * @param array<string, string> $declarations Accumulated declarations (by reference).
	 * @param array<int, array<string, string>> $skipped Accumulated skips (by reference).
	 * @param array<int, array<string, string>> $errors Accumulated errors (by reference).
	 * @param array<int, array<string, mixed>> $warnings Accumulated warnings (by reference).
	 *
	 * @return void
	 *
	 * @spec openspec/specs/custom-token-sets/spec.md
	 */
	private function dispatchByType(
		string $type,
		string $path,
		$value,
		$deprecated,
		array &$declarations,
		array &$skipped,
		array &$errors,
		array &$warnings,
	): void {
		if ($type === 'typography') {
			$this->processTypographyComposite(
				path: $path,
				value: $value,
				deprecated: $deprecated,
				declarations: $declarations,
				skipped: $skipped,
				errors: $errors,
				warnings: $warnings
			);

			return;
		}

		$result = match ($type) {
			'color' => $this->serializeColor(value: $value),
			'dimension', 'duration' => $this->serializeDimension(value: $value),
			'cubicBezier' => $this->serializeCubicBezier(value: $value),
			'fontFamily' => $this->serializeFontFamily(value: $value),
			'fontWeight' => $this->serializeFontWeight(value: $value),
			// Any other declared type (shadow, number, duration, …) is not
			// yet a first-class serializer here — fall back to the original
			// scalar-passthrough + suffix-mapping behaviour so every document
			// the previous mapper handled correctly keeps working unchanged.
			default => $this->serializeScalarPassthrough(value: $value),
		};

		$this->assignSingleTarget(
			path: $path,
			deprecated: $deprecated,
			result: $result,
			declarations: $declarations,
			skipped: $skipped,
			errors: $errors,
			warnings: $warnings
		);
	}//end dispatchByType()

	/**
	 * Serialize a `cubicBezier` value: the four-number array, or a legacy string.
	 *
	 * @param mixed $value The resolved `$value`.
	 *
	 * @return array{ok: bool, value?: string, reason?: string} The serialization result.
	 *
	 * @spec openspec/specs/token-set-dtcg-export/spec.md#requirement-a-thematiq-round-trip-is-exact
	 */
	private function serializeCubicBezier($value): array {
		if (is_string($value) === true) {
			return ['ok' => true, 'value' => trim($value)];
		}

		if (is_array($value) === false || count($value) !== 4 || count(array_filter($value, 'is_numeric')) !== 4) {
			return ['ok' => false, 'reason' => 'unsupported-value-shape'];
		}

		return ['ok' => true, 'value' => 'cubic-bezier(' . implode(', ', array_map('strval', array_values($value))) . ')'];
	}//end serializeCubicBezier()

	/**
	 * Legacy scalar-passthrough "serializer" for any declared type this
	 * mapper does not yet have a first-class serializer for (shadow, number,
	 * …) — preserves the original mapper's tolerant behaviour.
	 *
	 * @param mixed $value The resolved `$value`.
	 *
	 * @return array{ok: bool, value?: string, reason?: string} The serialization result.
	 *
	 * @spec openspec/specs/custom-token-sets/spec.md
	 */
	private function serializeScalarPassthrough($value): array {
		if (is_scalar($value) === false) {
			return ['ok' => false, 'reason' => 'unsupported-value-shape'];
		}

		return ['ok' => true, 'value' => (string)$value];
	}//end serializeScalarPassthrough()

	/**
	 * Assign a serialized single-target result to its `--nldesign-*` target.
	 *
	 * @param string $path The token's dotted path.
	 * @param mixed $deprecated The token's raw `$deprecated` value, if any.
	 * @param array{ok: bool, value?: string, reason?: string, detail?: string} $result The serializer result.
	 * @param array<string, string> $declarations Accumulated declarations (by reference).
	 * @param array<int, array<string, string>> $skipped Accumulated skips (by reference).
	 * @param array<int, array<string, string>> $errors Accumulated errors (by reference).
	 * @param array<int, array<string, mixed>> $warnings Accumulated warnings (by reference).
	 *
	 * @return void
	 *
	 * @spec openspec/specs/custom-token-sets/spec.md
	 */
	private function assignSingleTarget(
		string $path,
		$deprecated,
		array $result,
		array &$declarations,
		array &$skipped,
		array &$errors,
		array &$warnings,
	): void {
		if ($result['ok'] !== true) {
			$entry = ['path' => $path, 'reason' => ($result['reason'] ?? 'unsupported-value-shape')];
			if (isset($result['detail']) === true) {
				$entry['detail'] = $result['detail'];
			}

			$errors[] = $entry;

			return;
		}

		// The thematiq extension names the target before the suffix table is tried.
		$target = ($this->extensionTarget ?? $this->resolveTarget(path: $path));
		if ($target === null) {
			$skipped[] = ['path' => $path, 'reason' => 'unmapped-path'];

			return;
		}

		// First match wins for a given target; later collisions are skipped
		// so a more specific path earlier in the document is preserved.
		if (isset($declarations[$target]) === true) {
			$skipped[] = ['path' => $path, 'reason' => 'duplicate-target', 'detail' => $target];

			return;
		}

		$declarations[$target] = $result['value'];
		if (isset($result['adapted']) === true) {
			$this->adapted[] = ['path' => $path, 'target' => $target, 'value' => $result['value']] + $result['adapted'];
		}

		$this->maybeWarnDeprecated(path: $path, deprecated: $deprecated, warnings: $warnings, token: $target);
	}//end assignSingleTarget()

	/**
	 * Map a composite `typography` token's sub-properties individually.
	 *
	 * Each sub-property (`fontFamily`, `fontSize`, `fontWeight`, `lineHeight`,
	 * …) is mapped against its own fixed `--nldesign-*` target where one
	 * exists; sub-properties without a target are counted as skipped with
	 * their sub-path (`<path>.<subKey>`), never guessed or dropped silently.
	 *
	 * @param string $path The composite token's dotted path.
	 * @param mixed $value The resolved `$value` (expected object/array).
	 * @param mixed $deprecated The token's raw `$deprecated` value, if any.
	 * @param array<string, string> $declarations Accumulated declarations (by reference).
	 * @param array<int, array<string, string>> $skipped Accumulated skips (by reference).
	 * @param array<int, array<string, string>> $errors Accumulated errors (by reference).
	 * @param array<int, array<string, mixed>> $warnings Accumulated warnings (by reference).
	 *
	 * @return void
	 *
	 * @spec openspec/specs/custom-token-sets/spec.md
	 */
	private function processTypographyComposite(
		string $path,
		$value,
		$deprecated,
		array &$declarations,
		array &$skipped,
		array &$errors,
		array &$warnings,
	): void {
		if (is_array($value) === false) {
			$errors[] = ['path' => $path, 'reason' => 'unsupported-value-shape'];

			return;
		}

		$anyMapped = false;

		foreach ($value as $subKey => $subValue) {
			if (is_string($subKey) === false) {
				continue;
			}

			$subPath = $path . '.' . $subKey;
			$target = (self::TYPOGRAPHY_SUBPROPERTY_TARGETS[$subKey] ?? null);

			if ($target === null) {
				$skipped[] = ['path' => $subPath, 'reason' => 'unmapped-path'];
				continue;
			}

			$result = ['ok' => false, 'reason' => 'unsupported-value-shape'];
			if ($subKey === 'fontFamily') {
				$result = $this->serializeFontFamily(value: $subValue);
			}

			if ($result['ok'] !== true) {
				$errors[] = ['path' => $subPath, 'reason' => ($result['reason'] ?? 'unsupported-value-shape')];
				continue;
			}

			if (isset($declarations[$target]) === true) {
				$skipped[] = ['path' => $subPath, 'reason' => 'duplicate-target', 'detail' => $target];
				continue;
			}

			$declarations[$target] = $result['value'];
			$anyMapped = true;
		}//end foreach

		if ($anyMapped === true) {
			$this->maybeWarnDeprecated(path: $path, deprecated: $deprecated, warnings: $warnings);
		}
	}//end processTypographyComposite()

	/**
	 * Record a deprecation warning for a successfully imported token.
	 *
	 * `$deprecated` may be boolean `true` (no message), a string message, or
	 * the object Tokens Studio writes (`{severity: warning|error, message}`,
	 * EditTokenForm.tsx:444-489 at 2.12.1); per the ADDED requirement, the
	 * warning is only ever surfaced for a token that was actually imported.
	 * A single-target token also names the CSS variable it became, so the
	 * notice can be recorded as a deprecation of that name.
	 *
	 * @param string $path The token's dotted path.
	 * @param mixed $deprecated The raw `$deprecated` value.
	 * @param array<int, array<string, mixed>> $warnings Accumulated warnings (by reference).
	 * @param string|null $token The CSS variable the token became, when it became one.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/custom-token-sets/spec.md
	 */
	private function maybeWarnDeprecated(string $path, $deprecated, array &$warnings, ?string $token = null): void {
		if ($deprecated === null || $deprecated === false) {
			return;
		}

		$warning = ['path' => $path, 'message' => null];
		if (is_array($deprecated) === true) {
			$warning['severity'] = 'warning';
			if (($deprecated['severity'] ?? '') === 'error') {
				$warning['severity'] = 'critical';
			}

			$deprecated = ($deprecated['message'] ?? null);
		}

		if (is_string($deprecated) === true && trim($deprecated) !== '') {
			$warning['message'] = $deprecated;
		}

		if ($token !== null) {
			$warning['token'] = $token;
		}

		$warnings[] = $warning;
	}//end maybeWarnDeprecated()

	/**
	 * Serialize a `color` value: legacy string literal passthrough, or the
	 * v2025.10 object form (sRGB-family color spaces only).
	 *
	 * @param mixed $value The resolved `$value`.
	 *
	 * @return array{ok: bool, value?: string, reason?: string, detail?: string} The serialization result.
	 *
	 * @spec openspec/specs/custom-token-sets/spec.md
	 */
	private function serializeColor($value): array {
		if (is_string($value) === true) {
			return ['ok' => true, 'value' => trim($value)];
		}

		if (is_array($value) === false) {
			return ['ok' => false, 'reason' => 'unsupported-value-shape'];
		}

		return $this->serializeColorObject(value: $value);
	}//end serializeColor()

	/**
	 * Serialize the v2025.10 object-form `color` value, in any colour space the DTCG colour
	 * module lists, to sRGB hex: the `hex` member for an sRGB colour, else the converted
	 * components; out of gamut, the document's `hex` or the clipped value, reported `adapted`.
	 *
	 * @param array<string, mixed> $value The object-form `$value`.
	 *
	 * @return array{ok: bool, value?: string, reason?: string, detail?: string} The serialization result.
	 *
	 * @spec openspec/specs/custom-token-sets/spec.md
	 */
	private function serializeColorObject(array $value): array {
		$colorSpace = strtolower((string)($value['colorSpace'] ?? ''));
		if ($this->colorSpaces->isKnownSpace(space: $colorSpace) === false) {
			return ['ok' => false, 'reason' => 'unsupported-color-space', 'detail' => ($value['colorSpace'] ?? 'unknown')];
		}

		$alphaHex = $this->alphaToHex(alpha: $value['alpha'] ?? null);
		$hex = $this->hexMember(value: $value);
		$components = $value['components'] ?? null;
		if ($colorSpace === 'srgb' && $hex !== null) {
			return ['ok' => true, 'value' => $hex . $alphaHex];
		}

		if (is_array($components) === false || count($components) < 3) {
			if ($hex !== null) {
				return ['ok' => true, 'value' => $hex . $alphaHex];
			}

			return ['ok' => false, 'reason' => 'unsupported-value-shape'];
		}

		$rgb = (array)$this->colorSpaces->toSrgb(space: $colorSpace, components: array_values($components));
		if ($this->colorSpaces->isInGamut(rgb: $rgb) === true) {
			return ['ok' => true, 'value' => $this->colorSpaces->toHex(rgb: $rgb) . $alphaHex];
		}

		// Out of the sRGB gamut every consumer reads: the document's own fallback when it
		// has one, else each channel clipped. Either way the report says so.
		$adapted = ['reason' => 'out-of-gamut-clipped', 'original' => $value];
		if ($hex !== null) {
			$adapted['reason'] = 'out-of-gamut-hex-fallback';

			return ['ok' => true, 'value' => $hex . $alphaHex, 'adapted' => $adapted];
		}

		return ['ok' => true, 'value' => $this->colorSpaces->toHex(rgb: $rgb) . $alphaHex, 'adapted' => $adapted];
	}//end serializeColorObject()

	/**
	 * A colour object's `hex` member as `#rrggbb`, or null when it has none.
	 *
	 * @param array<string, mixed> $value The colour object.
	 *
	 * @return string|null
	 */
	private function hexMember(array $value): ?string {
		$hex = $value['hex'] ?? null;
		if (is_string($hex) === false || preg_match('/^#?([0-9a-fA-F]{6})$/', trim($hex), $match) !== 1) {
			return null;
		}

		return '#' . strtolower($match[1]);
	}//end hexMember()

	/**
	 * Turn a colour object's `alpha` into the fourth hex pair, or '' when opaque.
	 *
	 * @param mixed $alpha The `alpha` member (0–1), absent for an opaque colour.
	 *
	 * @return string Two hex digits, or an empty string for an opaque or absent alpha.
	 */
	private function alphaToHex(mixed $alpha): string {
		if (is_int($alpha) === false && is_float($alpha) === false) {
			return '';
		}

		$alpha = max(0.0, min(1.0, (float)$alpha));
		if ($alpha >= 1.0) {
			return '';
		}

		return str_pad(dechex((int)round($alpha * 255)), 2, '0', STR_PAD_LEFT);
	}//end alphaToHex()

	/**
	 * Serialize a `dimension` value: legacy string passthrough, or the
	 * `{value, unit}` object form concatenated as `<value><unit>`.
	 *
	 * @param mixed $value The resolved `$value`.
	 *
	 * @return array{ok: bool, value?: string, reason?: string} The serialization result.
	 *
	 * @spec openspec/specs/custom-token-sets/spec.md
	 */
	private function serializeDimension($value): array {
		if (is_string($value) === true) {
			return ['ok' => true, 'value' => trim($value)];
		}

		if (is_array($value) === true
			&& array_key_exists('value', $value) === true
			&& array_key_exists('unit', $value) === true
			&& is_scalar($value['value']) === true
			&& is_string($value['unit']) === true
		) {
			return ['ok' => true, 'value' => $value['value'] . $value['unit']];
		}

		return ['ok' => false, 'reason' => 'unsupported-value-shape'];
	}//end serializeDimension()

	/**
	 * Serialize a `fontFamily` value: a bare string, or an array serialized
	 * as a quoted CSS font stack (names containing whitespace are quoted;
	 * single-word names and generic keywords are left bare).
	 *
	 * @param mixed $value The resolved `$value`.
	 *
	 * @return array{ok: bool, value?: string, reason?: string} The serialization result.
	 *
	 * @spec openspec/specs/custom-token-sets/spec.md
	 */
	private function serializeFontFamily($value): array {
		if (is_string($value) === true) {
			return ['ok' => true, 'value' => trim($value)];
		}

		if (is_array($value) === true) {
			$parts = [];
			foreach ($value as $font) {
				if (is_string($font) === false) {
					continue;
				}

				$font = trim($font);
				if ($font === '') {
					continue;
				}

				$quoted = $font;
				if (str_contains($font, ' ') === true) {
					$quoted = "'" . $font . "'";
				}

				$parts[] = $quoted;
			}//end foreach

			if (empty($parts) === true) {
				return ['ok' => false, 'reason' => 'unsupported-value-shape'];
			}

			return ['ok' => true, 'value' => implode(', ', $parts)];
		}//end if

		return ['ok' => false, 'reason' => 'unsupported-value-shape'];
	}//end serializeFontFamily()

	/**
	 * Serialize a `fontWeight` value: a number, or a v2025.10 weight keyword
	 * normalized to its numeric CSS value.
	 *
	 * @param mixed $value The resolved `$value`.
	 *
	 * @return array{ok: bool, value?: string, reason?: string} The serialization result.
	 *
	 * @spec openspec/specs/custom-token-sets/spec.md
	 */
	private function serializeFontWeight($value): array {
		if (is_int($value) === true || is_float($value) === true) {
			return ['ok' => true, 'value' => (string)((int)$value)];
		}

		if (is_string($value) === true) {
			$trimmed = trim($value);
			if (is_numeric($trimmed) === true) {
				return ['ok' => true, 'value' => (string)((int)$trimmed)];
			}

			$key = strtolower($trimmed);
			if (isset(self::FONT_WEIGHT_KEYWORDS[$key]) === true) {
				return ['ok' => true, 'value' => (string)self::FONT_WEIGHT_KEYWORDS[$key]];
			}
		}

		return ['ok' => false, 'reason' => 'unsupported-value-shape'];
	}//end serializeFontWeight()

	/**
	 * Extract a declared package version from a DTCG document, checked in
	 * order: top-level `$version`, a recognized `$extensions` version
	 * convention, then a plain top-level `version` string. Never fabricated —
	 * absent a declared version this returns null.
	 *
	 * @param array<string, mixed> $document The decoded DTCG JSON document.
	 *
	 * @return string|null The declared version, verbatim, or null.
	 *
	 * @spec openspec/specs/custom-token-sets/spec.md
	 */
	private function extractPackageVersion(array $document): ?string {
		if (isset($document['$version']) === true && is_string($document['$version']) === true) {
			return $document['$version'];
		}

		$extensions = ($document['$extensions'] ?? null);
		if (is_array($extensions) === true) {
			foreach (['nl.nldesign.version', 'org.nldesign.version'] as $key) {
				if (isset($extensions[$key]) === true && is_string($extensions[$key]) === true) {
					return $extensions[$key];
				}
			}
		}

		if (isset($document['version']) === true && is_string($document['version']) === true) {
			return $document['version'];
		}

		return null;
	}//end extractPackageVersion()

	/**
	 * Resolve a dotted DTCG path to a --nldesign target by longest suffix match.
	 *
	 * @param string $path The dotted token path (e.g. "theme.color.primary").
	 *
	 * @return string|null The --nldesign target, or null when unmapped.
	 *
	 * @spec openspec/specs/custom-token-sets/spec.md
	 */
	private function resolveTarget(string $path): ?string {
		$lower = strtolower($path);

		$best = null;
		$bestLength = 0;
		foreach (self::MAPPING as $suffix => $target) {
			if (str_ends_with($lower, $suffix) === true && strlen($suffix) > $bestLength) {
				$best = $target;
				$bestLength = strlen($suffix);
			}
		}

		return $best;
	}//end resolveTarget()

	/**
	 * Get the published mapping table for transparency (API/docs).
	 *
	 * @return array<string, string> The DTCG-suffix => --nldesign-target table.
	 *
	 * @spec openspec/specs/custom-token-sets/spec.md
	 */
	public function getMappingTable(): array {
		return self::MAPPING;
	}//end getMappingTable()
}//end class
