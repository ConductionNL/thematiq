<?php

/**
 * The token sets of a Tokens Studio single file, merged into one tree.
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
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * Tokens Studio keeps each token set under its own key in a single file. A reference
 * names the token, not the set, so the sets have to be merged before anything resolves.
 *
 * @spec openspec/changes/figma-token-files/specs/figma-token-files/spec.md#requirement-a-file-from-figma-becomes-a-custom-token-set
 */
class TokensStudioSets {

	/**
	 * How deep a file may nest.
	 *
	 * @var int
	 */
	private const MAX_DEPTH = 16;

	/**
	 * The themes of a file that are brands: none for fewer than two themes, and none for a
	 * light and dark file, which is one house style.
	 *
	 * @param array<string, mixed> $document The file.
	 *
	 * @return array<int|string, mixed> The themes.
	 *
	 * @spec openspec/changes/figma-token-files/specs/figma-token-files/spec.md#requirement-a-file-from-the-openproject-plugin-uploads-into-thematiq
	 */
	public function brandThemes(array $document): array {
		$themes = ($document['$themes'] ?? null);
		if (is_array($themes) === false || count($themes) < 2 || $this->darkSets(document: $document) !== null) {
			return [];
		}

		return $themes;
	}//end brandThemes()

	/**
	 * Whether a document is a Tokens Studio single file: it has `$themes` or `$metadata`.
	 *
	 * @param array<string, mixed> $document The document.
	 *
	 * @return bool True for a single file.
	 *
	 * @spec openspec/changes/figma-token-files/specs/figma-token-files/spec.md#requirement-a-file-from-figma-becomes-a-custom-token-set
	 */
	public function isSingleFile(array $document): bool {
		return (array_key_exists('$themes', $document) === true || array_key_exists('$metadata', $document) === true);
	}//end isSingleFile()

	/**
	 * The token sets that hold the dark colours of a light and dark file.
	 *
	 * Two themes of which one is called "dark": the sets only that theme switches on. No
	 * themes: a set called `dark`. Such a file is one house style, not two brands.
	 *
	 * @param array<string, mixed> $document The file.
	 *
	 * @return array<int, string>|null The names, or null when the file is not a light and dark file.
	 *
	 * @spec openspec/changes/figma-token-files/specs/figma-token-files/spec.md#requirement-a-file-from-figma-becomes-a-custom-token-set
	 */
	public function darkSets(array $document): ?array {
		$themes = array_values(array_filter((array)($document['$themes'] ?? []), 'is_array'));
		if ($themes === []) {
			$named = static fn (string $name): bool => strtolower($name) === 'dark';
			$dark = array_values(array_filter($this->names(document: $document), $named));
			if ($dark === []) {
				return null;
			}

			return $dark;
		}

		if (count($themes) !== 2) {
			return null;
		}

		$isDark = static fn (array $theme): bool => in_array(
			'dark',
			[strtolower((string)($theme['id'] ?? '')), strtolower((string)($theme['name'] ?? ''))],
			true
		);
		if ($isDark($themes[0]) === $isDark($themes[1])) {
			return null;
		}

		$darkTheme = $themes[1];
		$lightTheme = $themes[0];
		if ($isDark($themes[0]) === true) {
			$darkTheme = $themes[0];
			$lightTheme = $themes[1];
		}

		$switchedOn = static fn (array $theme): array => array_keys(
			array_filter((array)($theme['selectedTokenSets'] ?? []), static fn ($status): bool => $status === 'enabled' || $status === 'source')
		);

		return array_values(array_map('strval', array_diff($switchedOn($darkTheme), $switchedOn($lightTheme))));
	}//end darkSets()

	/**
	 * The names of the token sets: first in `tokenSetOrder`, then the ones it does not list.
	 *
	 * @param array<string, mixed> $document The file.
	 *
	 * @return array<int, string> The names.
	 */
	private function names(array $document): array {
		$names = [];
		foreach ((array)($document['$metadata']['tokenSetOrder'] ?? []) as $name) {
			if (is_string($name) === true) {
				$names[] = $name;
			}
		}

		$names = [...$names, ...array_map('strval', array_keys($document))];

		return array_values(
			array_unique(
				array_filter($names, static fn (string $name): bool => str_starts_with($name, '$') === false && is_array($document[$name] ?? null) === true)
			)
		);
	}//end names()

	/**
	 * The set statuses of the file's only theme, or null when it has no theme or several.
	 *
	 * @param array<string, mixed> $document The file.
	 *
	 * @return array<string, mixed>|null Set name to status.
	 */
	private function selection(array $document): ?array {
		$themes = array_values(array_filter((array)($document['$themes'] ?? []), 'is_array'));
		if (count($themes) !== 1 || is_array($themes[0]['selectedTokenSets'] ?? null) === false) {
			return null;
		}

		return $themes[0]['selectedTokenSets'];
	}//end selection()

	/**
	 * The token sets of a Tokens Studio file as one tree.
	 *
	 * With exactly one theme, the sets that theme has switched off are left out. Later sets
	 * replace earlier ones at the same path, as the plugin does.
	 *
	 * @param array<string, mixed> $document The file.
	 * @param array<int, array{path: string, reason: string}> $skipped Appended to.
	 *
	 * @return array<string, mixed> The merged tree.
	 *
	 * @spec openspec/changes/figma-token-files/specs/figma-token-files/spec.md#requirement-a-file-from-figma-becomes-a-custom-token-set
	 */
	public function merged(array $document, array &$skipped): array {
		$selected = $this->selection(document: $document);

		$merged = [];
		$dark = ($this->darkSets(document: $document) ?? []);
		foreach ($this->names(document: $document) as $name) {
			if (in_array($name, $dark, true) === true) {
				$skipped[] = ['path' => (string)$name, 'reason' => 'dark-colours-not-read'];
				continue;
			}

			$status = 'enabled';
			if ($selected !== null) {
				$status = ($selected[$name] ?? 'disabled');
			}

			if ($status !== 'enabled' && $status !== 'source') {
				$skipped[] = ['path' => (string)$name, 'reason' => 'token-set-disabled'];
				continue;
			}

			$merged = $this->replaced(base: $merged, over: $document[$name], depth: 0);
		}

		return $merged;
	}//end mergedSets()

	/**
	 * One tree laid over another: a token replaces whatever was at its path.
	 *
	 * @param array<string, mixed> $base The tree so far.
	 * @param array<string, mixed> $over The tree that wins.
	 * @param int $depth The depth, guarded.
	 *
	 * @return array<string, mixed> The result.
	 */
	private function replaced(array $base, array $over, int $depth): array {
		foreach ($over as $key => $child) {
			$isGroup = (is_array($child) === true && $this->isToken(node: $child) === false && str_starts_with((string)$key, '$') === false);
			if ($isGroup === true && $depth < self::MAX_DEPTH && is_array($base[$key] ?? null) === true && $this->isToken(node: $base[$key]) === false) {
				$base[$key] = $this->replaced(base: $base[$key], over: $child, depth: ($depth + 1));
				continue;
			}

			$base[$key] = $child;
		}

		return $base;
	}//end replaced()

	/**
	 * Whether a node is a token, in either spelling.
	 *
	 * @param array<string, mixed> $node The node.
	 *
	 * @return bool True for a token.
	 *
	 * @spec openspec/changes/figma-token-files/specs/figma-token-files/spec.md#requirement-a-file-from-figma-becomes-a-custom-token-set
	 */
	public function isToken(array $node): bool {
		if (array_key_exists('$value', $node) === true) {
			return true;
		}

		return (array_key_exists('value', $node) === true && (is_array($node['value']) === false || is_string($node['type'] ?? null) === true));
	}//end isToken()
}//end class
