<?php

/**
 * Colour objects of a W3C document as the strings Figma and Tokens Studio read.
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
 * Writes a W3C colour object as `#rrggbb`, or as `rgba()` when it is not opaque.
 *
 * @spec openspec/specs/figma-token-files/spec.md#requirement-any-token-set-can-be-downloaded-for-figma
 */
final class FigmaColourStrings {

	/**
	 * The document with every colour object as a string.
	 *
	 * @param array<string, mixed> $node A group or a token.
	 *
	 * @return array<string, mixed> The same node.
	 *
	 * @spec openspec/specs/figma-token-files/spec.md#requirement-any-token-set-can-be-downloaded-for-figma
	 */
	public function withColourStrings(array $node): array {
		if (array_key_exists('$value', $node) === true) {
			return $this->withColourString(token: $node);
		}

		foreach ($node as $key => $child) {
			if (is_array($child) === true && str_starts_with((string)$key, '$') === false) {
				$node[$key] = $this->withColourStrings(node: $child);
			}
		}

		return $node;
	}//end withColourStrings()

	/**
	 * A token with its colour object as a string.
	 *
	 * @param array<string, mixed> $token A token.
	 *
	 * @return array<string, mixed> The same token.
	 */
	private function withColourString(array $token): array {
		if (is_array($token['$value']) === true && ($token['$type'] ?? null) === 'color') {
			$token['$value'] = ($this->colourString(value: $token['$value']) ?? $token['$value']);
		}

		return $token;
	}//end withColourString()

	/**
	 * A colour object as `#rrggbb`, or as `rgba()` when it is not opaque.
	 *
	 * @param array<string, mixed> $value The W3C colour value.
	 *
	 * @return string|null The string, or null for a colour without a hex.
	 *
	 * @spec openspec/specs/figma-token-files/spec.md#requirement-any-token-set-can-be-downloaded-for-figma
	 */
	public function colourString(array $value): ?string {
		$hex = $this->hexOf(value: $value);
		if ($hex === null) {
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
	 * The `#rrggbb` of a colour object.
	 *
	 * @param array<string, mixed> $value The W3C colour value.
	 *
	 * @return string|null The hex, or null when it has none.
	 */
	private function hexOf(array $value): ?string {
		$hex = ($value['hex'] ?? null);
		if (is_string($hex) === false || preg_match('/^#[0-9a-fA-F]{6}$/', $hex) !== 1) {
			return null;
		}

		return $hex;
	}//end hexOf()
}//end class
