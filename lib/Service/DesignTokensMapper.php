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
	 * The OpenProject plugin's extension key, where it keeps a colour's original text too.
	 *
	 * @var string
	 */
	private const PLUGIN_EXTENSION = 'nl.conduction.openproject-thematiq';

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
		'color.action-text' => '--nldesign-color-action-text',
		'color.on-action' => '--nldesign-color-action-text',
		'color.action-hover' => '--nldesign-color-action-hover',
		'color.action' => '--nldesign-color-action',
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
	 * The colour value serializer.
	 *
	 * @var DtcgColourSerializer
	 */
	private DtcgColourSerializer $colours;

	/**
	 * The serializer of every other single-target value.
	 *
	 * @var DtcgValueSerializer
	 */
	private DtcgValueSerializer $values;

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
		$this->colours = new DtcgColourSerializer(colorSpaces: ($colorSpaces ?? new ColorSpaceConverter()));
		$this->values = new DtcgValueSerializer(colours: $this->colours);
	}//end __construct()

	/**
	 * Map a parsed DTCG document onto nldesign declarations.
	 *
	 * @param array<string, mixed> $document The decoded DTCG JSON document.
	 * @param array<int, string> $referenceOnlyPaths Dotted paths that resolve aliases but are not emitted.
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
		$mapping = $this->mappedLeaves(document: $document, referenceOnlyPaths: $referenceOnlyPaths);

		// The root cssOnly map: values DTCG has no type for, written by thematiq's own export.
		// Tokens win over it; every entry passes the validator downstream like any declaration.
		$declarations = $mapping->declarations + $this->cssOnly(document: $document);

		return [
			'declarations' => $declarations,
			'adapted' => $this->adapted,
			// A document thematiq itself exported: the converter keeps every name as it is.
			'thematiqExport' => is_string($document['$extensions'][self::EXTENSION]['setId'] ?? null),
			'imported' => count($declarations),
			'skipped' => $mapping->skipped,
			'errors' => $mapping->errors,
			'warnings' => $mapping->warnings,
			'packageVersion' => $this->extractPackageVersion(document: $document),
		];
	}//end map()

	/**
	 * Flatten the document and map every leaf that is not reference-only.
	 *
	 * @param array<string, mixed> $document The decoded DTCG JSON document.
	 * @param array<int, string> $referenceOnlyPaths Dotted paths that resolve aliases but are not emitted.
	 *
	 * @return DesignTokensMapping The outcome.
	 */
	private function mappedLeaves(array $document, array $referenceOnlyPaths): DesignTokensMapping {
		$leaves = $this->collectLeaves(node: $document, scope: new LeafScope(prefix: '', inheritedType: null));
		$referenceOnly = array_flip($referenceOnlyPaths);
		$run = new LeafMappingRun(leaves: $leaves, mapping: new DesignTokensMapping(), referenceOnly: $referenceOnly);

		foreach (array_keys($leaves) as $path) {
			// A reference-only leaf (a Tokens Studio `source` set) stays in the alias table
			// above and is never assigned to a target.
			if (isset($referenceOnly[$path]) === false) {
				$this->processLeaf(path: $path, run: $run);
			}
		}

		return $run->mapping;
	}//end mappedLeaves()

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
	 * @param LeafScope $scope The accumulated dotted path and the nearest ancestor's declared `$type`, if any.
	 *
	 * @return array<string, array<string, mixed>> Collected path => {value, type, deprecated}.
	 *
	 * @spec openspec/specs/custom-token-sets/spec.md
	 */
	private function collectLeaves(mixed $node, LeafScope $scope): array {
		if (is_array($node) === false) {
			return [];
		}

		$ownType = $this->stringMember(map: $node, key: '$type');

		if (array_key_exists('$value', $node) === true) {
			return [$scope->prefix => $this->buildLeafEntry(node: $node, type: ($ownType ?? $scope->inheritedType))];
		}

		$effectiveInherited = ($ownType ?? $scope->inheritedType);

		$leaves = [];
		foreach ($this->groupChildren(node: $node) as $key => $child) {
			$childScope = new LeafScope(prefix: $this->childPath(prefix: $scope->prefix, key: $key), inheritedType: $effectiveInherited);
			$leaves = array_replace($leaves, $this->collectLeaves(node: $child, scope: $childScope));
		}

		return $leaves;
	}//end collectLeaves()

	/**
	 * A group's children, without its `$` metadata keys.
	 *
	 * @param array<mixed> $node The group.
	 *
	 * @return array<mixed> The children, keyed as in the group.
	 */
	private function groupChildren(array $node): array {
		return array_filter(
			$node,
			static fn ($key): bool => is_string($key) === false || str_starts_with($key, '$') === false,
			ARRAY_FILTER_USE_KEY
		);
	}//end groupChildren()

	/**
	 * The dotted path of a group's child.
	 *
	 * @param string $prefix The group's path, empty at the document root.
	 * @param int|string $key The child's key.
	 *
	 * @return string The child's path.
	 */
	private function childPath(string $prefix, int|string $key): string {
		if ($prefix === '') {
			return (string)$key;
		}

		return $prefix . '.' . $key;
	}//end childPath()

	/**
	 * A member of a map when it is a string.
	 *
	 * @param array<mixed> $map The map.
	 * @param string $key The member's key.
	 *
	 * @return string|null The member, or null when it is absent or not a string.
	 */
	private function stringMember(array $map, string $key): ?string {
		if (isset($map[$key]) === true && is_string($map[$key]) === true) {
			return $map[$key];
		}

		return null;
	}//end stringMember()

	/**
	 * Build a single leaf-table entry from a `$value`-bearing DTCG node.
	 *
	 * @param array<string, mixed> $node The `$value`-bearing node.
	 * @param string|null $type The node's own declared `$type`, else the nearest ancestor's, if any.
	 *
	 * @return array{value: mixed, type: string|null, deprecated: mixed, cssVariable: string|null, css: mixed} The leaf entry.
	 *
	 * @spec openspec/specs/custom-token-sets/spec.md
	 */
	private function buildLeafEntry(array $node, ?string $type): array {
		$deprecated = null;
		if (array_key_exists('$deprecated', $node) === true) {
			$deprecated = $node['$deprecated'];
		}

		return [
			'value' => $node['$value'],
			'type' => $type,
			'deprecated' => $deprecated,
			'cssVariable' => $this->extensionName(value: ($node['$extensions'][self::EXTENSION]['cssVariable'] ?? null)),
			'css' => ($node['$extensions'][self::EXTENSION]['css'] ?? $node['$extensions'][self::PLUGIN_EXTENSION]['css'] ?? null),
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
	 * @param LeafMappingRun $run The full leaf table (for alias resolution) and the outcome of the run so far.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/custom-token-sets/spec.md
	 */
	private function processLeaf(string $path, LeafMappingRun $run): void {
		$leaf = $run->leaves[$path];
		$this->extensionTarget = ($leaf['cssVariable'] ?? null);

		$resolved = $this->resolvedLeaf(path: $path, run: $run);
		if ($resolved === null) {
			return;
		}

		if ($resolved['type'] === null) {
			$run->mapping->errors[] = ['path' => $path, 'reason' => 'missing-type'];

			return;
		}

		$token = new MappedToken(ref: new DesignTokenRef(path: $path, deprecated: $leaf['deprecated']), mapping: $run->mapping);
		$value = $this->colours->originalText(leaf: $leaf, value: $resolved['value'], type: $resolved['type']);
		$reference = $this->referenceTarget(path: $path, run: $run);
		if ($reference !== null && $resolved['type'] !== 'typography') {
			$this->assignReference(token: $token, typed: new TypedValue(type: $resolved['type'], value: $value), reference: $reference);

			return;
		}

		$this->dispatchByType(token: $token, typed: new TypedValue(type: $resolved['type'], value: $value));
	}//end processLeaf()

	/**
	 * The `--nldesign-*` token an alias leaf keeps referring to: the target of
	 * its own alias, when that target is emitted by this import and maps onto a
	 * `--nldesign-*` token other than the leaf's own. Null keeps the resolved value.
	 *
	 * @param string $path The leaf's dotted path.
	 * @param LeafMappingRun $run The leaf table and the reference-only paths.
	 *
	 * @return string|null The referred token.
	 *
	 * @spec openspec/changes/token-references-stay-live/specs/token-editor-ui/spec.md#scenario-an-imported-alias-stays-a-reference
	 */
	private function referenceTarget(string $path, LeafMappingRun $run): ?string {
		$value = $run->leaves[$path]['value'];
		if (is_string($value) === false || preg_match('/^\{(.+)\}$/', trim($value), $matches) !== 1) {
			return null;
		}

		$targetPath = trim($matches[1]);
		if (isset($run->leaves[$targetPath]) === false || isset($run->referenceOnly[$targetPath]) === true) {
			return null;
		}

		$target = ($run->leaves[$targetPath]['cssVariable'] ?? null) ?? $this->resolveTarget(path: $targetPath);
		$own = ($this->extensionTarget ?? $this->resolveTarget(path: $path));
		if ($target === null || $target === $own || str_starts_with($target, '--nldesign-') === false) {
			return null;
		}

		return $target;
	}//end referenceTarget()

	/**
	 * Declare an alias as a reference to its target, once its resolved value
	 * has passed its type, so a value the import would refuse is still refused.
	 *
	 * @param MappedToken $token The leaf and the outcome so far.
	 * @param TypedValue $typed The resolved type and value.
	 * @param string $reference The referred `--nldesign-*` token.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/token-references-stay-live/specs/token-editor-ui/spec.md#scenario-an-imported-alias-stays-a-reference
	 */
	private function assignReference(MappedToken $token, TypedValue $typed, string $reference): void {
		$result = $this->values->serialize(type: $typed->type, value: $typed->value);
		if ($result['ok'] === true) {
			$result = ['ok' => true, 'value' => TokenReference::to(tokenName: $reference)];
		}

		$this->assignSingleTarget(token: $token, result: $result);
	}//end assignReference()

	/**
	 * A leaf's value and type, its alias resolved. Own declared/inherited type
	 * wins; otherwise the terminal alias target's resolved type (a
	 * semantic-layer alias commonly declares no $type of its own, relying on
	 * its target).
	 *
	 * @param string $path The leaf's dotted path.
	 * @param LeafMappingRun $run The leaf table and the outcome, which gets an unresolvable alias's error.
	 *
	 * @return array{value: mixed, type: string|null}|null The value and type, or null when the alias does not resolve.
	 */
	private function resolvedLeaf(string $path, LeafMappingRun $run): array|null {
		$leaf = $run->leaves[$path];
		if (is_string($leaf['value']) === false || $this->aliasResolver->isAlias(value: $leaf['value']) === false) {
			return ['value' => $leaf['value'], 'type' => $leaf['type']];
		}

		$resolved = $this->aliasResolver->resolve(startPath: $path, leaves: $run->leaves);
		if ($resolved['ok'] !== true) {
			$run->mapping->errors[] = $this->aliasError(path: $path, resolved: $resolved);

			return null;
		}

		return ['value' => $resolved['value'], 'type' => ($leaf['type'] ?? $resolved['type'])];
	}//end resolvedLeaf()

	/**
	 * The error entry for an alias that does not resolve.
	 *
	 * @param string $path The leaf's dotted path.
	 * @param array<string, mixed> $resolved The resolver's refusal.
	 *
	 * @return array{path: string, reason: mixed, detail: mixed} The error entry.
	 */
	private function aliasError(string $path, array $resolved): array {
		return [
			'path' => $path,
			'reason' => $resolved['reason'],
			'detail' => ($resolved['detail'] ?? ''),
		];
	}//end aliasError()

	/**
	 * Dispatch a leaf's resolved type to its serializer and record the
	 * outcome. Extracted from {@see self::processLeaf()} to keep each
	 * method's own cyclomatic complexity and length in check.
	 *
	 * @param MappedToken $token The leaf's path and `$deprecated` value, and the outcome of the run so far.
	 * @param TypedValue $typed The resolved `$type` and `$value`.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/custom-token-sets/spec.md
	 */
	private function dispatchByType(MappedToken $token, TypedValue $typed): void {
		if ($typed->type === 'typography') {
			$this->processTypographyComposite(token: $token, value: $typed->value);

			return;
		}

		$result = $this->values->serialize(type: $typed->type, value: $typed->value);

		$this->assignSingleTarget(token: $token, result: $result);
	}//end dispatchByType()

	/**
	 * Assign a serialized single-target result to its `--nldesign-*` target.
	 *
	 * @param MappedToken $token The token's path and `$deprecated` value, and the outcome of the run so far.
	 * @param array{ok: bool, value?: string, reason?: string, detail?: string, adapted?: array<string, mixed>} $result The serializer result.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/custom-token-sets/spec.md
	 */
	private function assignSingleTarget(MappedToken $token, array $result): void {
		$path = $token->ref->path;
		if ($result['ok'] !== true) {
			$token->mapping->errors[] = $this->errorEntry(path: $path, result: $result);

			return;
		}

		$target = $this->freeTarget(token: $token);
		if ($target === null) {
			return;
		}

		$token->mapping->declarations[$target] = $result['value'];
		if (isset($result['adapted']) === true) {
			$this->adapted[] = ['path' => $path, 'target' => $target, 'value' => $result['value']] + $result['adapted'];
		}

		$this->maybeWarnDeprecated(token: $token, target: $target);
	}//end assignSingleTarget()

	/**
	 * The `--nldesign-*` target a token takes, when it has one no earlier token took.
	 *
	 * @param MappedToken $token The token and the outcome so far, which gets the skip when there is none.
	 *
	 * @return string|null The target, or null when the path is unmapped or the target taken.
	 */
	private function freeTarget(MappedToken $token): string|null {
		$path = $token->ref->path;
		// The thematiq extension names the target before the suffix table is tried.
		$target = ($this->extensionTarget ?? $this->resolveTarget(path: $path));
		if ($target === null) {
			$token->mapping->skipped[] = ['path' => $path, 'reason' => 'unmapped-path'];

			return null;
		}

		// First match wins for a given target; later collisions are skipped
		// so a more specific path earlier in the document is preserved.
		if (isset($token->mapping->declarations[$target]) === true) {
			$token->mapping->skipped[] = ['path' => $path, 'reason' => 'duplicate-target', 'detail' => $target];

			return null;
		}

		return $target;
	}//end freeTarget()

	/**
	 * The error entry for a token whose value could not be serialized.
	 *
	 * @param string $path The token's dotted path.
	 * @param array{ok: bool, value?: string, reason?: string, detail?: string, adapted?: array<string, mixed>} $result The serializer result.
	 *
	 * @return array<string, mixed> The error entry.
	 */
	private function errorEntry(string $path, array $result): array {
		$entry = ['path' => $path, 'reason' => ($result['reason'] ?? 'unsupported-value-shape')];
		if (isset($result['detail']) === true) {
			$entry['detail'] = $result['detail'];
		}

		return $entry;
	}//end errorEntry()

	/**
	 * Map a composite `typography` token's sub-properties individually.
	 *
	 * Each sub-property (`fontFamily`, `fontSize`, `fontWeight`, `lineHeight`,
	 * …) is mapped against its own fixed `--nldesign-*` target where one
	 * exists; sub-properties without a target are counted as skipped with
	 * their sub-path (`<path>.<subKey>`), never guessed or dropped silently.
	 *
	 * @param MappedToken $token The composite token's path and `$deprecated` value, and the outcome of the run so far.
	 * @param mixed $value The resolved `$value` (expected object/array).
	 *
	 * @return void
	 *
	 * @spec openspec/specs/custom-token-sets/spec.md
	 */
	private function processTypographyComposite(MappedToken $token, mixed $value): void {
		$path = $token->ref->path;
		if (is_array($value) === false) {
			$token->mapping->errors[] = ['path' => $path, 'reason' => 'unsupported-value-shape'];

			return;
		}

		$mapped = 0;
		$members = array_filter($value, static fn ($key): bool => is_string($key), ARRAY_FILTER_USE_KEY);
		foreach ($members as $subKey => $subValue) {
			$member = new TypographyMember(path: $path . '.' . $subKey, key: $subKey, value: $subValue);
			$mapped += (int)$this->mapTypographyMember(mapping: $token->mapping, member: $member);
		}

		if ($mapped > 0) {
			$this->maybeWarnDeprecated(token: $token, target: null);
		}
	}//end processTypographyComposite()

	/**
	 * Map one typography sub-property to its fixed target, or record why it was not.
	 *
	 * @param DesignTokensMapping $mapping The outcome of the run so far.
	 * @param TypographyMember $member The sub-property.
	 *
	 * @return bool True when the sub-property was mapped.
	 */
	private function mapTypographyMember(DesignTokensMapping $mapping, TypographyMember $member): bool {
		$target = (self::TYPOGRAPHY_SUBPROPERTY_TARGETS[$member->key] ?? null);
		if ($target === null) {
			$mapping->skipped[] = ['path' => $member->path, 'reason' => 'unmapped-path'];
			return false;
		}

		$result = $this->typographyMemberResult(member: $member);
		if ($result['ok'] !== true) {
			$mapping->errors[] = ['path' => $member->path, 'reason' => ($result['reason'] ?? 'unsupported-value-shape')];
			return false;
		}

		if (isset($mapping->declarations[$target]) === true) {
			$mapping->skipped[] = ['path' => $member->path, 'reason' => 'duplicate-target', 'detail' => $target];
			return false;
		}

		$mapping->declarations[$target] = $result['value'];
		return true;
	}//end mapTypographyMember()

	/**
	 * A typography sub-property serialized: only `fontFamily` has a serializer.
	 *
	 * @param TypographyMember $member The sub-property.
	 *
	 * @return array{ok: bool, value?: string, reason?: string} The serializer result.
	 */
	private function typographyMemberResult(TypographyMember $member): array {
		if ($member->key === 'fontFamily') {
			return $this->values->serializeFontFamily(value: $member->value);
		}

		return ['ok' => false, 'reason' => 'unsupported-value-shape'];
	}//end typographyMemberResult()

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
	 * @param MappedToken $token The token's path and raw `$deprecated` value, and the outcome, which gets the warning.
	 * @param string|null $target The CSS variable the token became, when it became one.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/custom-token-sets/spec.md
	 */
	private function maybeWarnDeprecated(MappedToken $token, string|null $target): void {
		$deprecated = $token->ref->deprecated;
		if ($deprecated === null || $deprecated === false) {
			return;
		}

		$warning = $this->deprecationWarning(path: $token->ref->path, deprecated: $deprecated);

		if ($target !== null) {
			$warning['token'] = $target;
		}

		$token->mapping->warnings[] = $warning;
	}//end maybeWarnDeprecated()

	/**
	 * The deprecation warning for a `$deprecated` value: its message, and for the
	 * Tokens Studio object form its severity (`error` becomes `critical`).
	 *
	 * @param string $path The token's dotted path.
	 * @param mixed $deprecated The raw `$deprecated` value.
	 *
	 * @return array<string, mixed> The warning.
	 */
	private function deprecationWarning(string $path, mixed $deprecated): array {
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

		return $warning;
	}//end deprecationWarning()

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
		return ($this->stringMember(map: $document, key: '$version')
			?? $this->extensionVersion(extensions: ($document['$extensions'] ?? null))
			?? $this->stringMember(map: $document, key: 'version'));
	}//end extractPackageVersion()

	/**
	 * The package version an NL Design System extension block declares.
	 *
	 * @param mixed $extensions The document's `$extensions`.
	 *
	 * @return string|null
	 */
	private function extensionVersion(mixed $extensions): ?string {
		if (is_array($extensions) === false) {
			return null;
		}

		foreach (['nl.nldesign.version', 'org.nldesign.version'] as $key) {
			$version = $this->stringMember(map: $extensions, key: $key);
			if ($version !== null) {
				return $version;
			}
		}

		return null;
	}//end extensionVersion()

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
