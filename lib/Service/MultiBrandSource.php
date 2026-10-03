<?php

/**
 * Thematiq multi-brand source reader.
 *
 * Finds the brands in one token source and cuts one brand out of it as ordinary
 * single-brand input for the converter, which never learns about brands.
 *
 * Two shapes hold several brands:
 *  - a Tokens Studio document whose `$themes` lists two or more themes (ThemeObject:
 *    `id`, `name`, `group`, `selectedTokenSets` with `enabled`, `source` or `disabled`,
 *    tokens-studio/figma-plugin 2.12.1 `src/types/ThemeObject.ts:3-18`,
 *    `src/constants/TokenSetStatus.ts:1-5`);
 *  - built theme CSS with two or more blocks whose selector is exactly `.{key}-theme`.
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
 * @spec openspec/specs/multi-brand-token-sources/spec.md#requirement-a-multi-brand-source-is-recognised-by-its-content
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use RuntimeException;

/**
 * Detect and cut brands.
 *
 * @spec openspec/specs/multi-brand-token-sources/spec.md#requirement-a-multi-brand-source-is-recognised-by-its-content
 */
class MultiBrandSource {

	/**
	 * A brand class selector: exactly one class, `.{key}-theme`.
	 *
	 * @var string
	 */
	private const BRAND_SELECTOR = '/^\.([a-z0-9]+(?:-[a-z0-9]+)*)-theme$/';

	/**
	 * Constructor.
	 *
	 * @param CssBlockReader $reader Reads the blocks of built theme CSS.
	 */
	public function __construct(
		private readonly CssBlockReader $reader = new CssBlockReader(),
	) {
	}//end __construct()

	/**
	 * The brands of a source; fewer than two means "one brand".
	 *
	 * @param string $content The uploaded document.
	 *
	 * @return array<int, array{key: string, name: string, tokenCount: int, group?: string}>
	 *
	 * @spec openspec/specs/multi-brand-token-sources/spec.md#requirement-a-multi-brand-source-is-recognised-by-its-content
	 */
	public function detectBrands(string $content): array {
		$decoded = json_decode($content, true);
		if (is_array($decoded) === true) {
			return $this->themes(document: $decoded);
		}

		$brands = [];
		foreach ($this->reader->blocks(css: $content) as $block) {
			if (preg_match(self::BRAND_SELECTOR, $block['selector'], $match) === 1) {
				$brands[] = [
					'key' => $match[1],
					'name' => ucfirst(str_replace('-', ' ', $match[1])),
					'tokenCount' => count($block['declarations']),
				];
			}
		}

		return $this->atLeastTwo(brands: $brands);
	}//end detectBrands()

	/**
	 * One brand as single-brand input: for CSS every shared block then the brand's own block;
	 * for Tokens Studio the brand's `enabled` and `source` sets merged in set order, later
	 * winning, with the paths only a `source` set defines listed as reference-only.
	 *
	 * @param string $content The uploaded document.
	 * @param string $key The brand key.
	 *
	 * @return array{content: string, referenceOnlyPaths: array<int, string>}
	 *
	 * @throws RuntimeException 422 for a key the source does not have.
	 *
	 * @spec openspec/specs/multi-brand-token-sources/spec.md#requirement-each-brand-is-converted-by-the-existing-pipeline
	 */
	public function cut(string $content, string $key): array {
		$decoded = json_decode($content, true);
		if (is_array($decoded) === true) {
			return $this->cutTheme(document: $decoded, key: $key);
		}

		$shared = [];
		$own = null;
		foreach ($this->reader->blocks(css: $content) as $block) {
			if (preg_match(self::BRAND_SELECTOR, $block['selector'], $match) === 1) {
				if ($match[1] === $key) {
					$own = $block;
				}

				continue;
			}

			// A modifier of another brand (`.zuid-theme--dark`) is that brand's, not shared.
			if (preg_match('/^\.([a-z0-9-]+)-theme--/', $block['selector']) === 1) {
				continue;
			}

			$shared[] = $block;
		}

		if ($own === null) {
			throw new RuntimeException('unknown brand: ' . $key, 422);
		}

		$css = '';
		foreach (array_merge($shared, [$own]) as $block) {
			$lines = [];
			foreach ($block['declarations'] as $name => $value) {
				$lines[] = '  ' . $name . ': ' . $value . ';';
			}

			$css .= $block['selector'] . " {\n" . implode("\n", $lines) . "\n}\n";
		}

		return ['content' => $css, 'referenceOnlyPaths' => []];
	}//end cut()

	/**
	 * The themes of a Tokens Studio document.
	 *
	 * @param array<string, mixed> $document The document.
	 *
	 * @return array<int, array{key: string, name: string, tokenCount: int, group?: string}>
	 */
	private function themes(array $document): array {
		$themes = ($document['$themes'] ?? null);
		if (is_array($themes) === false || count($themes) < 2) {
			return [];
		}

		$brands = [];
		foreach ($themes as $theme) {
			if (is_array($theme) === false || is_string($theme['id'] ?? null) === false) {
				continue;
			}

			$brand = [
				'key' => (string)$theme['id'],
				'name' => (string)($theme['name'] ?? $theme['id']),
				'tokenCount' => $this->countLeaves(node: $this->mergeSets(document: $document, theme: $theme, statuses: ['enabled'])),
			];
			if (is_string($theme['group'] ?? null) === true) {
				$brand['group'] = $theme['group'];
			}

			$brands[] = $brand;
		}

		return $this->atLeastTwo(brands: $brands);
	}//end themes()

	/**
	 * The brands when there are two or more, else none: one brand is an ordinary upload.
	 *
	 * @param array<int, array<string, mixed>> $brands The brands found.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function atLeastTwo(array $brands): array {
		if (count($brands) < 2) {
			return [];
		}

		return $brands;
	}//end atLeastTwo()

	/**
	 * Cut one theme out of a Tokens Studio document.
	 *
	 * @param array<string, mixed> $document The document.
	 * @param string $key The theme id.
	 *
	 * @return array{content: string, referenceOnlyPaths: array<int, string>}
	 *
	 * @throws RuntimeException 422 for an unknown theme.
	 */
	private function cutTheme(array $document, string $key): array {
		foreach ((array)($document['$themes'] ?? []) as $theme) {
			if (is_array($theme) === true && ($theme['id'] ?? null) === $key) {
				$merged = $this->mergeSets(document: $document, theme: $theme, statuses: ['enabled', 'source']);
				$enabled = $this->leafPaths(node: $this->mergeSets(document: $document, theme: $theme, statuses: ['enabled']), prefix: '');

				return [
					'content' => (string)json_encode((object)$merged, JSON_UNESCAPED_SLASHES),
					'referenceOnlyPaths' => array_values(array_diff($this->leafPaths(node: $merged, prefix: ''), $enabled)),
				];
			}
		}

		throw new RuntimeException('unknown brand: ' . $key, 422);
	}//end cutTheme()

	/**
	 * The theme's sets with the given statuses, merged in `$metadata.tokenSetOrder` (sets
	 * the order does not list follow, by name), later sets replacing earlier ones at the same
	 * path, as Tokens Studio's `applyTokenSetOrder` and `mergeTokenGroups` do.
	 *
	 * @param array<string, mixed> $document The document.
	 * @param array<string, mixed> $theme The theme.
	 * @param array<int, string> $statuses The statuses to include.
	 *
	 * @return array<string, mixed> The merged token tree.
	 */
	private function mergeSets(array $document, array $theme, array $statuses): array {
		$selected = array_filter(
			(array)($theme['selectedTokenSets'] ?? []),
			static fn ($status): bool => in_array($status, $statuses, true)
		);
		$order = array_values(array_filter((array)($document['$metadata']['tokenSetOrder'] ?? []), 'is_string'));
		$names = array_keys($selected);
		usort(
			$names,
			static function (string $first, string $second) use ($order): int {
				$firstAt = array_search($first, $order, true);
				$secondAt = array_search($second, $order, true);
				if ($firstAt === false && $secondAt === false) {
					return strcmp($first, $second);
				}

				if ($firstAt === false || $secondAt === false) {
					return (int)($firstAt === false) - (int)($secondAt === false);
				}

				return $firstAt <=> $secondAt;
			}
		);

		$merged = [];
		foreach ($names as $name) {
			if (is_array($document[$name] ?? null) === true) {
				$merged = $this->replaceTree(base: $merged, over: $document[$name]);
			}
		}

		return $merged;
	}//end mergeSets()

	/**
	 * Recursive replace: a token (a node with `$value` or `value`) replaces whatever sits at its path.
	 *
	 * @param array<string, mixed> $base The tree so far.
	 * @param array<string, mixed> $over The tree on top.
	 *
	 * @return array<string, mixed>
	 */
	private function replaceTree(array $base, array $over): array {
		foreach ($over as $key => $node) {
			$bothGroups = is_array($node) === true && $this->isToken(node: $node) === false
				&& is_array($base[$key] ?? null) === true && $this->isToken(node: $base[$key]) === false;
			if ($bothGroups === true) {
				$base[$key] = $this->replaceTree(base: $base[$key], over: $node);
				continue;
			}

			$base[$key] = $node;
		}

		return $base;
	}//end replaceTree()

	/**
	 * Whether a node is a token.
	 *
	 * @param array<string, mixed> $node The node.
	 *
	 * @return bool
	 */
	private function isToken(array $node): bool {
		return array_key_exists('$value', $node) === true || (array_key_exists('value', $node) === true && is_array($node['value']) === false);
	}//end isToken()

	/**
	 * The dotted paths of every token in a tree.
	 *
	 * @param array<string, mixed> $node The tree.
	 * @param string $prefix The path so far.
	 *
	 * @return array<int, string>
	 */
	private function leafPaths(array $node, string $prefix): array {
		if ($this->isToken(node: $node) === true) {
			return [$prefix];
		}

		$paths = [];
		foreach ($node as $key => $child) {
			if (is_array($child) === true && str_starts_with((string)$key, '$') === false) {
				$paths = array_merge($paths, $this->leafPaths(node: $child, prefix: ltrim($prefix . '.' . $key, '.')));
			}
		}

		return $paths;
	}//end leafPaths()

	/**
	 * How many tokens a tree holds.
	 *
	 * @param array<string, mixed> $node The tree.
	 *
	 * @return int
	 */
	private function countLeaves(array $node): int {
		return count($this->leafPaths(node: $node, prefix: ''));
	}//end countLeaves()

}//end class
