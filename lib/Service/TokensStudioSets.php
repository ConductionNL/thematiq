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
 * @spec openspec/specs/figma-token-files/spec.md#requirement-a-file-from-figma-becomes-a-custom-token-set
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
	 * @spec openspec/specs/figma-token-files/spec.md#requirement-a-file-from-the-openproject-plugin-uploads-into-thematiq
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
	 * @spec openspec/specs/figma-token-files/spec.md#requirement-a-file-from-figma-becomes-a-custom-token-set
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
	 * @spec openspec/specs/figma-token-files/spec.md#requirement-a-file-from-figma-becomes-a-custom-token-set
	 */
	public function darkSets(array $document): ?array {
		$themes = array_values(array_filter((array)($document['$themes'] ?? []), 'is_array'));
		if ($themes === []) {
			return $this->darkByName(document: $document);
		}

		if (count($themes) !== 2) {
			return null;
		}

		return $this->darkByThemes(themes: $themes);
	}//end darkSets()

	/**
	 * The sets named `dark`, in a file without themes.
	 *
	 * @param array<string, mixed> $document The file.
	 *
	 * @return array<int, string>|null The names, or null when no set is named so.
	 */
	private function darkByName(array $document): ?array {
		$named = static fn (string $name): bool => strtolower($name) === 'dark';
		$dark = array_values(array_filter($this->names(document: $document), $named));
		if ($dark === []) {
			return null;
		}

		return $dark;
	}//end darkByName()

	/**
	 * The sets a dark theme switches on that its light theme does not.
	 *
	 * @param array<int, array<string, mixed>> $themes The file's two themes.
	 *
	 * @return array<int, string>|null The names, or null unless exactly one of the themes is dark.
	 */
	private function darkByThemes(array $themes): ?array {
		if ($this->isDarkTheme(theme: $themes[0]) === $this->isDarkTheme(theme: $themes[1])) {
			return null;
		}

		$darkTheme = $themes[1];
		$lightTheme = $themes[0];
		if ($this->isDarkTheme(theme: $themes[0]) === true) {
			$darkTheme = $themes[0];
			$lightTheme = $themes[1];
		}

		return array_values(array_map('strval', array_diff($this->switchedOn(theme: $darkTheme), $this->switchedOn(theme: $lightTheme))));
	}//end darkByThemes()

	/**
	 * Whether a theme's id or name is `dark`.
	 *
	 * @param array<string, mixed> $theme The theme.
	 *
	 * @return bool True for the dark theme.
	 */
	private function isDarkTheme(array $theme): bool {
		return in_array(
			'dark',
			[strtolower((string)($theme['id'] ?? '')), strtolower((string)($theme['name'] ?? ''))],
			true
		);
	}//end isDarkTheme()

	/**
	 * The sets a theme has switched on, as enabled or as source.
	 *
	 * @param array<string, mixed> $theme The theme.
	 *
	 * @return array<int|string, int|string> The set names.
	 */
	private function switchedOn(array $theme): array {
		return array_keys(
			array_filter((array)($theme['selectedTokenSets'] ?? []), static fn ($status): bool => $status === 'enabled' || $status === 'source')
		);
	}//end switchedOn()

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
	 * @spec openspec/specs/figma-token-files/spec.md#requirement-a-file-from-figma-becomes-a-custom-token-set
	 */
	public function merged(array $document, array &$skipped): array {
		$selected = $this->selection(document: $document);

		$merged = [];
		$dark = ($this->darkSets(document: $document) ?? []);
		foreach ($this->names(document: $document) as $name) {
			// The dark sets are read apart, as the set's dark values ({@see self::darkTree()}).
			if (in_array($name, $dark, true) === true) {
				continue;
			}

			$status = $this->setStatus(selected: $selected, name: $name);
			if ($status !== 'enabled' && $status !== 'source') {
				$skipped[] = ['path' => (string)$name, 'reason' => 'token-set-disabled'];
				continue;
			}

			$merged = $this->replaced(base: $merged, over: $document[$name], depth: 0);
		}

		return $merged;
	}//end merged()

	/**
	 * A set's status in the file's only theme; every set is enabled in a file without one.
	 *
	 * @param array<string, mixed>|null $selected The theme's set statuses, or null.
	 * @param string $name The set name.
	 *
	 * @return mixed The status, `disabled` for a set the theme does not list.
	 */
	private function setStatus(?array $selected, string $name): mixed {
		if ($selected === null) {
			return 'enabled';
		}

		return ($selected[$name] ?? 'disabled');
	}//end setStatus()

	/**
	 * The dark token sets of a light and dark file as one tree, in their order; empty for any other file.
	 *
	 * @param array<string, mixed> $document The file.
	 *
	 * @return array<string, mixed> The merged dark tree.
	 *
	 * @spec openspec/specs/dark-colours-from-a-file/spec.md#requirement-dark-colours-from-a-file-are-kept
	 */
	public function darkTree(array $document): array {
		$dark = ($this->darkSets(document: $document) ?? []);
		$merged = [];
		foreach ($this->names(document: $document) as $name) {
			if (in_array($name, $dark, true) === true) {
				$merged = $this->replaced(base: $merged, over: $document[$name], depth: 0);
			}
		}

		return $merged;
	}//end darkTree()

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
			if ($this->isGroup(key: $key, node: $child) === true && $depth < self::MAX_DEPTH && $this->isGroupAt(tree: $base, key: $key) === true) {
				$base[$key] = $this->replaced(base: $base[$key], over: $child, depth: ($depth + 1));
				continue;
			}

			$base[$key] = $child;
		}

		return $base;
	}//end replaced()

	/**
	 * Whether a member of a tree is a group: an array that is not a token, under a key without `$`.
	 *
	 * @param int|string $key The member's key.
	 * @param mixed $node The member.
	 *
	 * @return bool True for a group.
	 */
	private function isGroup(int|string $key, mixed $node): bool {
		return is_array($node) === true && $this->isToken(node: $node) === false && str_starts_with((string)$key, '$') === false;
	}//end isGroup()

	/**
	 * Whether a tree holds a group (an array that is not a token) at a key.
	 *
	 * @param array<string, mixed> $tree The tree.
	 * @param int|string $key The key.
	 *
	 * @return bool True when a group is there.
	 */
	private function isGroupAt(array $tree, int|string $key): bool {
		return is_array($tree[$key] ?? null) === true && $this->isToken(node: $tree[$key]) === false;
	}//end isGroupAt()

	/**
	 * Whether a node is a token, in either spelling.
	 *
	 * @param array<string, mixed> $node The node.
	 *
	 * @return bool True for a token.
	 *
	 * @spec openspec/specs/figma-token-files/spec.md#requirement-a-file-from-figma-becomes-a-custom-token-set
	 */
	public function isToken(array $node): bool {
		if (array_key_exists('$value', $node) === true) {
			return true;
		}

		return (array_key_exists('value', $node) === true && (is_array($node['value']) === false || is_string($node['type'] ?? null) === true));
	}//end isToken()
}//end class
