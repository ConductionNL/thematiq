<?php

/**
 * Writes a token set as a file a Figma tool reads.
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
 * Turns the W3C Design Tokens document of a set into one of the Figma side formats.
 *
 * Three formats besides the document itself. `figma` is the same document with every
 * colour as a hex or `rgba()` string, which is what variable import tools read.
 * `tokens-studio` is a Tokens Studio single file: the set under its own key, with
 * `$themes` and `$metadata`, every value a string. `tokens-studio-legacy` is that file
 * in the `value` and `type` spelling. A token keeps its path in all of them.
 *
 * @spec openspec/changes/figma-token-files/specs/figma-token-files/spec.md#requirement-any-token-set-can-be-downloaded-for-figma
 */
class FigmaTokenWriter {

	/**
	 * The formats a set can be downloaded in.
	 *
	 * @var array<int, string>
	 */
	public const FORMATS = ['dtcg', 'figma', 'tokens-studio', 'tokens-studio-legacy'];

	/**
	 * The thematiq extension key.
	 *
	 * @var string
	 */
	private const EXTENSION = 'nl.conduction.thematiq';

	/**
	 * The Tokens Studio type of each W3C type. Any other type is written as `other`.
	 *
	 * @var array<string, string>
	 */
	private const STUDIO_TYPES = [
		'color' => 'color',
		'dimension' => 'dimension',
		'fontFamily' => 'fontFamilies',
		'fontWeight' => 'fontWeights',
		'number' => 'number',
	];

	/**
	 * The document in the asked format.
	 *
	 * @param array<string, mixed> $document The W3C document of the set, as DesignTokensWriter wrote it.
	 * @param array<string, string> $declarations The set's own declarations, name to CSS value.
	 * @param string $format One of FORMATS.
	 * @param string $setId The set id, which is the token set key in a Tokens Studio file.
	 * @param string $setName The set's display name.
	 *
	 * @return array<string, mixed> The document to encode as JSON.
	 *
	 * @spec openspec/changes/figma-token-files/specs/figma-token-files/spec.md#requirement-any-token-set-can-be-downloaded-for-figma
	 */
	public function write(array $document, array $declarations, string $format, string $setId, string $setName): array {
		if ($format !== 'figma' && $format !== 'tokens-studio' && $format !== 'tokens-studio-legacy') {
			return $document;
		}

		$document = (array)json_decode((string)json_encode($document), true);
		if ($format === 'figma') {
			$document = $this->withColourStrings(node: $document);
			$document['$extensions'][self::EXTENSION]['cssOnly'] = (object)($document['$extensions'][self::EXTENSION]['cssOnly'] ?? []);

			return (array)$this->grouped(node: $document, valueKey: '$value');
		}

		$valueKey = '$value';
		$typeKey = '$type';
		if ($format === 'tokens-studio-legacy') {
			$valueKey = 'value';
			$typeKey = 'type';
		}

		$tree = [];
		$this->studioTokens(node: $document, declarations: $declarations, keys: [$valueKey, $typeKey], tree: $tree);
		foreach ((array)($document['$extensions'][self::EXTENSION]['cssOnly'] ?? []) as $name => $value) {
			$path = array_values(array_filter(explode('-', ltrim((string)$name, '-'), 3), static fn (string $part): bool => $part !== ''));
			$this->place(tree: $tree, path: $path, token: [$valueKey => (string)$value, $typeKey => 'other'], valueKey: $valueKey);
		}

		return [
			$setId => $this->grouped(node: $tree, valueKey: $valueKey),
			'$themes' => [['id' => $setId, 'name' => $setName, 'selectedTokenSets' => (object)[$setId => 'enabled']]],
			'$metadata' => ['tokenSetOrder' => [$setId]],
		];
	}//end write()

	/**
	 * The file name of a download.
	 *
	 * @param string $setId The set id.
	 * @param string $format One of FORMATS.
	 *
	 * @return string The file name.
	 *
	 * @spec openspec/changes/figma-token-files/specs/figma-token-files/spec.md#requirement-any-token-set-can-be-downloaded-for-figma
	 */
	public function fileName(string $setId, string $format): string {
		if ($format === 'tokens-studio' || $format === 'tokens-studio-legacy') {
			return $setId . '.tokens-studio.json';
		}

		return $setId . '.tokens.json';
	}//end fileName()

	/**
	 * The document with every colour object as a string.
	 *
	 * @param array<string, mixed> $node A group or a token.
	 *
	 * @return array<string, mixed> The same node.
	 */
	private function withColourStrings(array $node): array {
		if (array_key_exists('$value', $node) === true) {
			if (is_array($node['$value']) === true && ($node['$type'] ?? null) === 'color') {
				$node['$value'] = ($this->colourString(value: $node['$value']) ?? $node['$value']);
			}

			return $node;
		}

		foreach ($node as $key => $child) {
			if (is_array($child) === true && str_starts_with((string)$key, '$') === false) {
				$node[$key] = $this->withColourStrings(node: $child);
			}
		}

		return $node;
	}//end withColourStrings()

	/**
	 * A colour object as `#rrggbb`, or as `rgba()` when it is not opaque.
	 *
	 * @param array<string, mixed> $value The W3C colour value.
	 *
	 * @return string|null The string, or null for a colour without a hex.
	 */
	private function colourString(array $value): ?string {
		$hex = ($value['hex'] ?? null);
		if (is_string($hex) === false || preg_match('/^#[0-9a-fA-F]{6}$/', $hex) !== 1) {
			return null;
		}

		$alpha = ($value['alpha'] ?? 1);
		if (is_numeric($alpha) === false || (float)$alpha >= 1.0) {
			return strtolower($hex);
		}

		$channels = [hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2))];

		return 'rgba(' . implode(', ', $channels) . ', ' . rtrim(rtrim(number_format((float)$alpha, 4, '.', ''), '0'), '.') . ')';
	}//end colourString()

	/**
	 * Every token of the W3C document as a Tokens Studio token, at the same path.
	 *
	 * @param array<string, mixed> $node A group of the W3C document.
	 * @param array<string, string> $declarations The set's declarations.
	 * @param array{0: string, 1: string} $keys The value key and the type key.
	 * @param array<string, mixed> $tree The Tokens Studio tree, filled in place.
	 * @param array<int, string> $path The path of the group.
	 *
	 * @return void
	 */
	private function studioTokens(array $node, array $declarations, array $keys, array &$tree, array $path = []): void {
		foreach ($node as $key => $child) {
			if (is_array($child) === false || str_starts_with((string)$key, '$') === true) {
				continue;
			}

			$here = [...$path, (string)$key];
			if (array_key_exists('$value', $child) === false) {
				$this->studioTokens(node: $child, declarations: $declarations, keys: $keys, tree: $tree, path: $here);
				continue;
			}

			$value = $this->studioValue(token: $child, declarations: $declarations);
			if ($value !== null) {
				$type = (self::STUDIO_TYPES[(string)($child['$type'] ?? '')] ?? 'other');
				$this->place(tree: $tree, path: $here, token: [$keys[0] => $value, $keys[1] => $type], valueKey: $keys[0]);
			}
		}
	}//end studioTokens()

	/**
	 * The string a Tokens Studio token holds: the reference, the colour, or the CSS value as declared.
	 *
	 * @param array<string, mixed> $token The W3C token.
	 * @param array<string, string> $declarations The set's declarations.
	 *
	 * @return string|null The value, or null when the token has none to write.
	 */
	private function studioValue(array $token, array $declarations): ?string {
		$value = $token['$value'];
		if (is_string($value) === true && preg_match('/^\{[^{}]+\}$/', $value) === 1) {
			return $value;
		}

		if (is_array($value) === true && ($token['$type'] ?? null) === 'color') {
			$colour = $this->colourString(value: $value);
			if ($colour !== null) {
				return $colour;
			}
		}

		$name = ($token['$extensions'][self::EXTENSION]['cssVariable'] ?? null);
		if (is_string($name) === true && isset($declarations[$name]) === true) {
			return trim((string)$declarations[$name]);
		}

		return null;
	}//end studioValue()

	/**
	 * Put a token at its path, unless a token or a group is already in the way.
	 *
	 * @param array<string, mixed> $tree The tree, changed in place.
	 * @param array<int, string> $path The path.
	 * @param array<string, string> $token The token.
	 * @param string $valueKey The key that marks a token.
	 *
	 * @return void
	 */
	private function place(array &$tree, array $path, array $token, string $valueKey): void {
		if ($path === []) {
			return;
		}

		$node = &$tree;
		$last = array_pop($path);
		foreach ($path as $segment) {
			if (isset($node[$segment]) === false) {
				$node[$segment] = [];
			}

			if (array_key_exists($valueKey, $node[$segment]) === true) {
				return;
			}

			$node = &$node[$segment];
		}

		if (isset($node[$last]) === false) {
			$node[$last] = $token;
		}
	}//end place()

	/**
	 * Every group as an object, so a group with the keys 0, 1, 2 still encodes as a JSON object.
	 *
	 * @param array<string, mixed> $node A group.
	 * @param string $valueKey The key that marks a token.
	 *
	 * @return object The group.
	 */
	private function grouped(array $node, string $valueKey): object {
		foreach ($node as $key => $child) {
			if (is_array($child) === true && str_starts_with((string)$key, '$') === false && array_key_exists($valueKey, $child) === false) {
				$node[$key] = $this->grouped(node: $child, valueKey: $valueKey);
			}
		}

		return (object)$node;
	}//end grouped()
}//end class
