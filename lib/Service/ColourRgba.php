<?php

/**
 * Thematiq Colour Rgba.
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
 * Reads a colour literal the contrast checks measure (`#rgb`, `#rgba`,
 * `#rrggbb`, `#rrggbbaa`, `rgb()` and `rgba()`) as red, green, blue and
 * alpha. Built by ContrastService.
 *
 * @spec openspec/specs/custom-token-sets/spec.md
 */
final class ColourRgba {

	/**
	 * Parse a CSS colour literal into an [r, g, b, a] quadruple.
	 *
	 * Supports #rgb, #rgba, #rrggbb, #rrggbbaa, rgb() and rgba() with a number
	 * or percentage alpha. Alpha is 1.0 for an opaque colour, rounded to three
	 * decimals.
	 *
	 * @param string $value The raw CSS colour value.
	 *
	 * @return array{0: int, 1: int, 2: int, 3: float}|null The parsed colour, or null.
	 *
	 * @spec openspec/specs/token-set-contrast-audit/spec.md#requirement-translucent-colours-are-measured-as-they-render
	 */
	public function parse(string $value): ?array {
		$value = trim($value);

		if (preg_match('/^#([0-9a-fA-F]{3,4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $value, $match) === 1) {
			return $this->parseHex(hex: $match[1]);
		}

		if (preg_match('/^rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*(?:,\s*([\d.]+)(%?)\s*)?\)$/', $value, $match) === 1) {
			$alpha = $this->functionAlpha(match: $match);

			return [
				min(255, (int)$match[1]),
				min(255, (int)$match[2]),
				min(255, (int)$match[3]),
				round(max(0.0, min(1.0, $alpha)), 3),
			];
		}

		return null;
	}//end parse()

	/**
	 * The alpha of a matched rgb()/rgba(): 1.0 when absent, a percentage divided by 100.
	 *
	 * @param array<int, string> $match The match: channels, then the alpha number and its `%`.
	 *
	 * @return float The alpha, not yet clamped.
	 */
	private function functionAlpha(array $match): float {
		if (isset($match[4]) === false || $match[4] === '') {
			return 1.0;
		}

		$alpha = (float)$match[4];
		if (($match[5] ?? '') === '%') {
			return ($alpha / 100);
		}

		return $alpha;
	}//end functionAlpha()

	/**
	 * Expand and parse the digits of a 3, 4, 6 or 8 digit hex colour.
	 *
	 * @param string $hex The hex digits without the leading '#'.
	 *
	 * @return array{0: int, 1: int, 2: int, 3: float} The parsed colour.
	 */
	private function parseHex(string $hex): array {
		$hex = $this->expandedHex(hex: $hex);

		$alpha = 1.0;
		if (strlen($hex) === 8) {
			$alpha = round(hexdec(substr($hex, 6, 2)) / 255, 3);
		}

		return [
			(int)hexdec(substr($hex, 0, 2)),
			(int)hexdec(substr($hex, 2, 2)),
			(int)hexdec(substr($hex, 4, 2)),
			$alpha,
		];
	}//end parseHex()

	/**
	 * A short hex colour written out in full: every digit doubled.
	 *
	 * @param string $hex The hex digits, without `#`.
	 *
	 * @return string The six or eight digits.
	 */
	private function expandedHex(string $hex): string {
		if (strlen($hex) > 4) {
			return $hex;
		}

		$expanded = '';
		foreach (str_split($hex) as $digit) {
			$expanded .= $digit . $digit;
		}

		return $expanded;
	}//end expandedHex()
}//end class
