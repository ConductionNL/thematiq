<?php

/**
 * The dark values a file from the OpenProject plugin or Tokens Studio brings.
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
 * Turns the dark colours of a file into dark values by CSS name and puts them in the thematiq
 * extension of the document handed on, where the converter lifts them out. A reference or a sum is
 * dropped here; every value is validated after.
 *
 * @spec openspec/changes/dark-colours-from-a-file/specs/dark-colours-from-a-file/spec.md#requirement-dark-colours-from-a-file-are-kept
 */
class FileDarkValues {

	/**
	 * The thematiq extension key.
	 *
	 * @var string
	 */
	private const EXTENSION = 'nl.conduction.thematiq';

	/**
	 * The OpenProject plugin's extension key.
	 *
	 * @var string
	 */
	private const PLUGIN_EXTENSION = 'nl.conduction.openproject-thematiq';

	/**
	 * How deep a tree may nest.
	 *
	 * @var int
	 */
	private const MAX_DEPTH = 16;

	/**
	 * The content with the dark values in the thematiq extension, or as it was when there are none.
	 *
	 * @param string $content The content so far.
	 * @param array<string, mixed> $document The same content, decoded.
	 * @param array<string, string> $dark Name => dark value.
	 *
	 * @return string The content.
	 *
	 * @spec openspec/changes/dark-colours-from-a-file/specs/dark-colours-from-a-file/spec.md#requirement-dark-colours-from-a-file-are-kept
	 */
	public function withDark(string $content, array $document, array $dark): string {
		if ($dark === []) {
			return $content;
		}

		$document['$extensions'][self::EXTENSION]['dark'] = (object)$dark;

		return (string)json_encode((object)$document, JSON_UNESCAPED_SLASHES);
	}//end withDark()

	/**
	 * The dark colours the OpenProject plugin writes in a W3C file: role => value in its own extension.
	 *
	 * @param array<string, mixed> $document The file.
	 *
	 * @return array<string, string> Name => dark value.
	 *
	 * @spec openspec/changes/dark-colours-from-a-file/specs/dark-colours-from-a-file/spec.md#requirement-dark-colours-from-a-file-are-kept
	 */
	public function pluginDark(array $document): array {
		$out = [];
		foreach ((array)($document['$extensions'][self::PLUGIN_EXTENSION]['dark'] ?? []) as $role => $value) {
			$name = (OpenProjectSetFile::ROLE_TOKENS[(string)$role] ?? null);
			$css = $this->darkValue(value: $value);
			if ($name !== null && $css !== null) {
				$out[$name] = $css;
			}
		}

		return $out;
	}//end pluginDark()

	/**
	 * The tokens of a dark tree as declarations: the name from the extension, else the path joined by dashes.
	 *
	 * @param array<string, mixed> $tree The dark tree, in the `$value` spelling.
	 *
	 * @return array<string, string> Name => dark value.
	 *
	 * @spec openspec/changes/dark-colours-from-a-file/specs/dark-colours-from-a-file/spec.md#requirement-dark-colours-from-a-file-are-kept
	 */
	public function fromTree(array $tree): array {
		$leaves = [];
		$this->leaves(node: $tree, path: [], leaves: $leaves, depth: 0);

		$out = [];
		foreach ($leaves as $leaf) {
			$name = ($leaf['token']['$extensions'][self::EXTENSION]['cssVariable'] ?? '--' . implode('-', $leaf['path']));
			$css = $this->darkValue(value: $leaf['token']['$value']);
			if (is_string($name) === true && preg_match('/^--[a-z][a-z0-9]*-[a-z0-9-]+$/', $name) === 1 && $css !== null) {
				$out[$name] = $css;
			}
		}

		return $out;
	}//end fromTree()

	/**
	 * A dark value as CSS: text without braces, or a colour object's hex.
	 *
	 * @param mixed $value The value.
	 *
	 * @return string|null The CSS value, or null when it is a reference, a sum or no value.
	 */
	private function darkValue(mixed $value): ?string {
		if (is_array($value) === true && is_string($value['hex'] ?? null) === true) {
			$value = $value['hex'];
		}

		if (is_string($value) === false || trim($value) === '' || preg_match('/[{}]/', $value) === 1) {
			return null;
		}

		return trim($value);
	}//end darkValue()

	/**
	 * Every token under a group, by dotted path.
	 *
	 * @param array<string, mixed> $node The group.
	 * @param array<int, string> $path The group's path.
	 * @param array<string, array<string, mixed>> $leaves Filled in place: dotted path to `path` and `token`.
	 * @param int $depth The depth, guarded.
	 *
	 * @return void
	 */
	private function leaves(array $node, array $path, array &$leaves, int $depth): void {
		foreach ($node as $key => $child) {
			if (is_array($child) === false || str_starts_with((string)$key, '$') === true) {
				continue;
			}

			$here = [...$path, (string)$key];
			if (array_key_exists('$value', $child) === true) {
				$leaves[implode('.', $here)] = ['path' => $here, 'token' => $child];
				continue;
			}

			if ($depth < self::MAX_DEPTH) {
				$this->leaves(node: $child, path: $here, leaves: $leaves, depth: ($depth + 1));
			}
		}
	}//end leaves()
}//end class
