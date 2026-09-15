<?php

/**
 * NL Design Token Set Preview Service.
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
 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-48
 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-49
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCP\App\IAppManager;

/**
 * Computes the resolved --color-* values for a given NL Design token set.
 *
 * Resolution pipeline (server-side):
 *   1. Parse defaults.css   → all --nldesign-* default values
 *   2. Parse tokens/{id}.css → org-specific overrides to --nldesign-*
 *   3. Merge                → final --nldesign-* map
 *   4. Parse overrides.css  → extract mapping --color-X: var(--nldesign-Y)
 *   5. Resolve              → for each --color-X, look up --nldesign-Y in merged map
 *
 * This is pure string manipulation — no DOM, no CSS parser.
 * Only editable tokens (in TokenRegistry) are returned.
 *
 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-48
 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-49
 */
class TokenSetPreviewService {

	/**
	 * The app manager for resolving the app's CSS directory.
	 *
	 * @var IAppManager
	 */
	private IAppManager $appManager;

	/**
	 * Constructor.
	 *
	 * @param IAppManager $appManager The app manager.
	 */
	public function __construct(IAppManager $appManager) {
		$this->appManager = $appManager;
	}//end __construct()

	/**
	 * Resolve the complete --nldesign-* layer of a token set: the defaults,
	 * with the set's own file merged over them.
	 *
	 * This is steps 1 and 2 of the pipeline in the class docblock, stopped
	 * before the --color-* resolution, because two callers want different
	 * halves of it. {@see getResolvedColors()} wants the Nextcloud variables a
	 * preview swatch paints; the component playground's export wants the
	 * semantic layer itself, since a token set FILE is --nldesign-* names and
	 * an export has to be a complete set, not the handful of values a preview
	 * needed (component-playground design decision 6).
	 *
	 * @param string $tokenSetId The token set identifier (e.g. 'utrecht', 'custom-openwoo').
	 *
	 * @return array<string, string> Map of --nldesign-* token name => declared value.
	 *
	 * @spec openspec/changes/component-playground/specs/component-playground/spec.md
	 */
	public function getResolvedTokens(string $tokenSetId): array {
		$appPath = $this->appManager->getAppPath('thematiq');

		$vars = $this->parseCssVars(
			filePath: $appPath . '/css/systems/nldesign/defaults.css'
		);

		return $this->semanticLayer(
			vars: array_merge($vars, $this->getDeclaredTokens(tokenSetId: $tokenSetId))
		);
	}//end getResolvedTokens()

	/**
	 * The `--nldesign-*` tokens a set declares in its OWN file, without the
	 * defaults behind it.
	 *
	 * The difference from {@see getResolvedTokens()} is the difference between
	 * "what this set paints with" and "what this set decided", and only the
	 * second can answer whether a component is touched by the set at all — the
	 * question the playground's "only what this set changes" filter asks. A
	 * merged map answers "yes" for every token, because the defaults declare
	 * them all.
	 *
	 * @param string $tokenSetId The token set identifier.
	 *
	 * @return array<string, string> Map of --nldesign-* token name => declared value.
	 *
	 * @spec openspec/changes/component-playground/specs/component-playground/spec.md
	 */
	public function getDeclaredTokens(string $tokenSetId): array {
		$path = $this->appManager->getAppPath('thematiq') . '/css/tokens/' . $tokenSetId . '.css';
		if (file_exists($path) === false) {
			return [];
		}

		return $this->semanticLayer(vars: $this->parseCssVars(filePath: $path));
	}//end getDeclaredTokens()

	/**
	 * Keep only the semantic layer of a parsed declaration map, sorted.
	 *
	 * A token set file may also declare its own brand-prefixed palette steps
	 * (`--{slug}-*`); those are raw material the design system does not read,
	 * and neither an export nor a filter should treat them as vocabulary.
	 *
	 * @param array<string, string> $vars A parsed declaration map.
	 *
	 * @return array<string, string> Only the --nldesign-* entries, sorted by name.
	 *
	 * @spec openspec/changes/component-playground/specs/component-playground/spec.md
	 */
	private function semanticLayer(array $vars): array {
		$tokens = [];
		foreach ($vars as $name => $value) {
			if (str_starts_with($name, '--nldesign-') === true) {
				$tokens[$name] = $value;
			}
		}

		ksort($tokens);

		return $tokens;
	}//end semanticLayer()

	/**
	 * Which `--nldesign-*` token each Nextcloud variable reads.
	 *
	 * Parsed out of `overrides.css`, which is the stylesheet that actually
	 * makes the connection, so the map cannot drift from the cascade the way a
	 * second copy of it in JavaScript or in a data file would. The component
	 * playground needs it to write an edited Nextcloud variable back into the
	 * token set vocabulary when it exports a set.
	 *
	 * @return array<string, string> Map of --color-* (or --border-radius-*, …) to --nldesign-* token.
	 *
	 * @spec openspec/changes/component-playground/specs/component-playground/spec.md
	 */
	public function getTokenSources(): array {
		return $this->parseMappings(
			filePath: $this->appManager->getAppPath('thematiq') . '/css/systems/nldesign/overrides.css'
		);
	}//end getTokenSources()

	/**
	 * Resolve all editable --color-* values for a given token set.
	 *
	 * @param string $tokenSetId The token set identifier (e.g. 'utrecht').
	 *
	 * @return array<string, string> Map of --color-* token name => resolved hex/color value.
	 *
	 * @SuppressWarnings(PHPMD.StaticAccess) - TokenRegistry uses static methods by design
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-48
	 */
	public function getResolvedColors(string $tokenSetId): array {
		$appPath = $this->appManager->getAppPath('thematiq');

		// Step 1: parse defaults.css → --nldesign-* defaults.
		$nldesignVars = $this->parseCssVars(
			filePath: $appPath . '/css/systems/nldesign/defaults.css'
		);

		// Step 2: parse tokens/{id}.css → overrides.
		$tokenSetPath = $appPath . '/css/tokens/' . $tokenSetId . '.css';
		if (file_exists($tokenSetPath) === true) {
			$tokenSetVars = $this->parseCssVars(filePath: $tokenSetPath);
			$nldesignVars = array_merge($nldesignVars, $tokenSetVars);
		}

		// Step 3: parse overrides.css → mapping --color-X: var(--nldesign-Y).
		$mappings = $this->parseMappings(
			filePath: $appPath . '/css/systems/nldesign/overrides.css'
		);

		// Step 4: resolve --color-* values using the nldesign map.
		$resolved = [];
		$editableTokens = TokenRegistry::getTokens();

		foreach (array_keys($editableTokens) as $colorToken) {
			if (isset($mappings[$colorToken]) === true) {
				// Mapping exists in overrides.css.
				$nldesignRef = $mappings[$colorToken];
				$value = $this->resolveVarReference(
					ref: $nldesignRef,
					vars: $nldesignVars
				);
				$resolved[$colorToken] = $value;
			}

			// Non-mapped tokens (e.g. --border-radius-*) are not in overrides.css mappings.
			// Skip them — they have no nldesign mapping to resolve.
		}

		return $resolved;
	}//end getResolvedColors()

	/**
	 * Parse all CSS custom property declarations from a file.
	 *
	 * Returns only :root-scoped declarations (ignores media queries etc.)
	 *
	 * @param string $filePath Absolute path to a CSS file.
	 *
	 * @return array<string, string> Map of --property-name => value.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-49
	 */
	private function parseCssVars(string $filePath): array {
		if (file_exists($filePath) === false) {
			return [];
		}

		$content = file_get_contents($filePath);
		if ($content === false) {
			return [];
		}

		$vars = [];
		preg_match_all('/^\s*(--[\w-]+)\s*:\s*([^;]+);/m', $content, $matches, PREG_SET_ORDER);
		foreach ($matches as $match) {
			$vars[trim($match[1])] = trim($match[2]);
		}

		return $vars;
	}//end parseCssVars()

	/**
	 * Parse the overrides.css file to extract --color-X: var(--nldesign-Y) mappings.
	 *
	 * Returns a map of --color-X => --nldesign-Y (without the var() wrapper).
	 * Ignores commented-out lines.
	 *
	 * @param string $filePath Absolute path to overrides.css.
	 *
	 * @return array<string, string> Map of --color-X => --nldesign-Y.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-49
	 */
	private function parseMappings(string $filePath): array {
		if (file_exists($filePath) === false) {
			return [];
		}

		$content = file_get_contents($filePath);
		if ($content === false) {
			return [];
		}

		$mappings = [];
		// Match lines like: --color-X: var(--nldesign-Y) !important;
		// Ignore commented-out lines (those starting with //).
		preg_match_all(
			'/^\s*(--[\w-]+)\s*:\s*var\((--[\w-]+)\)\s*(?:!important)?\s*;/m',
			$content,
			$matches,
			PREG_SET_ORDER
		);

		foreach ($matches as $match) {
			$colorToken = trim($match[1]);
			$nldesignToken = trim($match[2]);
			$mappings[$colorToken] = $nldesignToken;
		}

		return $mappings;
	}//end parseMappings()

	/**
	 * Resolve a var() reference using a variable map.
	 *
	 * Handles simple var(--name) references (one level deep).
	 * Returns the raw reference string if resolution fails.
	 *
	 * @param string $ref The value from overrides.css (e.g. '--nldesign-color-primary').
	 * @param array<string, string> $vars The merged --nldesign-* variable map.
	 *
	 * @return string The resolved value or the original reference.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-49
	 */
	private function resolveVarReference(string $ref, array $vars): string {
		// Direct lookup (ref is a --nldesign-* token name).
		if (isset($vars[$ref]) === true) {
			$value = $vars[$ref];
			// If the value itself is a var(), resolve one more level.
			if (str_starts_with(haystack: $value, needle: 'var(') === true) {
				preg_match('/var\((--[\w-]+)\)/', $value, $varMatch);
				if (isset($varMatch[1]) === true && isset($vars[$varMatch[1]]) === true) {
					return $vars[$varMatch[1]];
				}
			}

			return $value;
		}

		return $ref;
	}//end resolveVarReference()
}//end class
