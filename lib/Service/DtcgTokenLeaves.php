<?php

/**
 * Every token of a W3C Design Tokens tree, by dotted path.
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
 * @spec openspec/specs/figma-token-files/spec.md#requirement-a-file-from-figma-becomes-a-custom-token-set
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * Walks a W3C tree the way the Figma reader and the dark-values reader both
 * do: a member whose key starts with `$` is metadata, a member with a
 * `$value` is a token, anything else is a group, followed to a fixed depth.
 *
 * @spec openspec/specs/figma-token-files/spec.md#requirement-a-file-from-figma-becomes-a-custom-token-set
 */
final class DtcgTokenLeaves {

	/**
	 * How deep groups are followed below the root.
	 *
	 * @var int
	 */
	private const MAX_DEPTH = 16;

	/**
	 * Every token under a tree, by dotted path, in document order.
	 *
	 * @param array<string, mixed> $tree The tree.
	 *
	 * @return array<string, array{path: array<int, string>, token: array<string, mixed>}> Dotted path to `path` and `token`.
	 *
	 * @spec openspec/specs/figma-token-files/spec.md#requirement-a-file-from-figma-becomes-a-custom-token-set
	 */
	public static function of(array $tree): array {
		return self::under(node: $tree, path: []);
	}//end of()

	/**
	 * Every token under a group; a later token at the same dotted path replaces the value and keeps the first one's place.
	 *
	 * @param array<string, mixed> $node The group.
	 * @param array<int, string> $path The group's path.
	 *
	 * @return array<string, array{path: array<int, string>, token: array<string, mixed>}> Dotted path to `path` and `token`.
	 */
	private static function under(array $node, array $path): array {
		$leaves = [];
		foreach ($node as $key => $child) {
			if (is_array($child) === false || str_starts_with((string)$key, '$') === true) {
				continue;
			}

			$leaves = array_replace($leaves, self::childLeaves(child: $child, here: [...$path, (string)$key]));
		}

		return $leaves;
	}//end under()

	/**
	 * The token a member is, or the tokens under it when it is a group within the depth limit.
	 *
	 * @param array<string, mixed> $child The member.
	 * @param array<int, string> $here The member's path.
	 *
	 * @return array<string, array{path: array<int, string>, token: array<string, mixed>}> Dotted path to `path` and `token`.
	 */
	private static function childLeaves(array $child, array $here): array {
		if (array_key_exists('$value', $child) === true) {
			return [implode('.', $here) => ['path' => $here, 'token' => $child]];
		}

		if ((count($here) - 1) < self::MAX_DEPTH) {
			return self::under(node: $child, path: $here);
		}

		return [];
	}//end childLeaves()
}//end class
