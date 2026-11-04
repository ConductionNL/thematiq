<?php

/**
 * NL Design CSS Parser Service.
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
 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-27
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * Service for parsing CSS custom property declarations.
 *
 * Extracts --token-name: value pairs from raw CSS strings.
 *
 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-27
 * @spec openspec/specs/dark-mode/spec.md
 */
class CssParserService {
	/**
	 * How many `var()` hops {@see self::resolveVarChain()} follows.
	 */
	public const VAR_CHAIN_MAX_DEPTH = 4;

	/**
	 * Parse CSS custom property declarations from a raw CSS string.
	 *
	 * Matches all declarations like: --some-token: some-value; ({@see CssDeclarationScanner}).
	 *
	 * @param string $content The raw CSS content.
	 *
	 * @return array<string, string>|null Parsed token map, or null if none found.
	 *
	 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-27
	 */
	public function parseDeclarations(string $content): ?array {
		$parsed = (new CssDeclarationScanner(content: $content))->scan();

		if (empty($parsed) === true) {
			return null;
		}

		return $parsed;
	}//end parseDeclarations()

	/**
	 * Parse CSS custom property declarations from within a :root {} block.
	 *
	 * @param string $css The raw CSS string containing a :root {} block.
	 *
	 * @return array<string, string> Map of token name => value.
	 *
	 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-27
	 */
	public function parseRootBlock(string $css): array {
		// Comments first: one holding braces (defaults.css has `{normal,bold}`) ended the block early.
		$css = (string)preg_replace('#/\*.*?\*/#s', '', $css);
		if (preg_match('/:root\s*\{([^}]*)\}/s', $css, $rootMatch) !== 1) {
			return [];
		}

		$result = $this->parseDeclarations(content: $rootMatch[1]);

		if ($result !== null) {
			return $result;
		}

		return [];
	}//end parseRootBlock()

	/**
	 * Read the light values out of a custom-overrides file.
	 *
	 * The file custom-overrides.css carries its light values in a `:root` block
	 * and, for brand colour overrides, the same tokens again in two dark blocks. Reading every
	 * declaration in the file would let the dark values overwrite the light
	 * ones on import. So a file with a `:root` block is read from that block
	 * alone; a file of bare declarations (a hand-written import) is read whole.
	 *
	 * @param string $css The raw file content.
	 *
	 * @return array<string, string>|null Token => light value, or null when the file declares nothing.
	 *
	 * @spec openspec/specs/dark-mode/spec.md#requirement-editor-overrides-apply-the-same-way-for-every-dark-user
	 */
	public function parseOverridesFile(string $css): ?array {
		if (preg_match('/:root\s*\{/', $css) === 1) {
			$root = $this->parseRootBlock(css: $css);
			if (empty($root) === true) {
				return null;
			}

			return $root;
		}

		return $this->parseDeclarations(content: $css);
	}//end parseOverridesFile()

	/**
	 * Parse hand-authored dark-mode declarations from a top-level
	 * `@media (prefers-color-scheme: dark) { :root { ... } }` block.
	 *
	 * This is the hand-authored override extraction point for dark-variant
	 * generation (see the `dark-mode` spec's override requirement): any
	 * `--nldesign-*` declaration found inside the block is treated as an
	 * author-supplied dark value that MUST replace the algorithmically
	 * derived one for the same token. Absence of the block, or CSS the
	 * pattern cannot recognise, degrades to an empty map — never an
	 * exception — so generation always has something safe to merge over.
	 *
	 * @param string $css The raw CSS content of a token set file.
	 *
	 * @return array<string, string> Map of token name => hand-authored dark value (empty when absent).
	 *
	 * @spec openspec/specs/dark-mode/spec.md
	 */
	public function parseDarkBlock(string $css): array {
		$pattern = '/@media\s*\(\s*prefers-color-scheme\s*:\s*dark\s*\)\s*\{\s*:root\s*\{([^}]*)\}\s*\}/is';

		if (preg_match($pattern, $css, $match) !== 1) {
			return [];
		}

		return ($this->parseDeclarations(content: $match[1]) ?? []);
	}//end parseDarkBlock()

	/**
	 * Resolve a declaration value through its `var(--token)` indirections.
	 *
	 * A browser resolves `--a: var(--b)` by looking `--b` up in the same
	 * cascade, so anything that measures a token value has to do the same or it
	 * reads an indirection as "not a colour". Both an un-parenthesised
	 * reference (`var(--b)`) and a reference with a fallback
	 * (`var(--b, #fff)`) are followed; the fallback is used only when the
	 * referenced token is absent from the map, which is what the browser does.
	 *
	 * The depth cap is four hops. Measured on the shipped sets, the longest
	 * real chain is two (`--nldesign-color-primary` ->
	 * `--conduction-color-brand-primary` -> `--c-blue-cobalt`), and the cap is
	 * what stops a cyclic set (`--a: var(--b); --b: var(--a)`) from recursing
	 * forever. A value that is still a reference at the cap is reported
	 * unresolved rather than guessed.
	 *
	 * @param string $value The raw declaration value.
	 * @param array<string,string> $declarations The declaration map to resolve against.
	 * @param int $depth The current recursion depth (internal).
	 *
	 * @return array{value: string|null, unresolved: string|null} The resolved
	 *                                                            literal, or the name of the token the chain could not resolve.
	 *
	 * @spec openspec/specs/token-set-contrast-audit/spec.md#requirement-automated-contrast-audit-over-all-shipped-token-sets
	 */
	public function resolveVarChain(string $value, array $declarations, int $depth = 0): array {
		$trimmed = trim($value);

		if (preg_match('/^var\(\s*(--[\w-]+)\s*(?:,(.*))?\)$/s', $trimmed, $matches) !== 1) {
			// Not a var() indirection - a literal (parseable or not).
			return ['value' => $trimmed, 'unresolved' => null];
		}

		$reference = $matches[1];
		$fallback = trim(($matches[2] ?? ''));

		if ($depth >= self::VAR_CHAIN_MAX_DEPTH) {
			return ['value' => null, 'unresolved' => $reference];
		}

		if (array_key_exists($reference, $declarations) === true) {
			return $this->resolveVarChain(
				value: $declarations[$reference],
				declarations: $declarations,
				depth: ($depth + 1)
			);
		}

		// The referenced token is absent, so the browser takes the fallback.
		if ($fallback !== '') {
			return $this->resolveVarChain(
				value: $fallback,
				declarations: $declarations,
				depth: ($depth + 1)
			);
		}

		return ['value' => null, 'unresolved' => $reference];
	}//end resolveVarChain()

	/**
	 * Replace every value in a declaration map by the literal its `var()` chain
	 * reaches, resolving against the map itself.
	 *
	 * A browser resolves `--nldesign-color-primary: var(--c-blue-cobalt)` out of
	 * the same cascade; anything that MEASURES a token value has to do the same
	 * or it reads an indirection as "not a colour". That is how conduction-new's
	 * two contrast pairs went unmeasured.
	 *
	 * A chain that genuinely does not resolve — a reference no layer declares, or
	 * a cycle — keeps its raw value, so a caller still sees "not a colour" rather
	 * than a fabricated one.
	 *
	 * @param array<string, string> $declarations The declaration map.
	 *
	 * @return array<string, string> The same keys, with var() chains resolved.
	 *
	 * @spec openspec/specs/token-set-contrast-audit/spec.md#requirement-automated-contrast-audit-over-all-shipped-token-sets
	 */
	public function resolveAll(array $declarations): array {
		$resolved = [];
		foreach ($declarations as $name => $value) {
			$chain = $this->resolveVarChain(value: $value, declarations: $declarations);
			$resolved[$name] = ($chain['value'] ?? $value);
		}

		return $resolved;
	}//end resolveAll()
}//end class
