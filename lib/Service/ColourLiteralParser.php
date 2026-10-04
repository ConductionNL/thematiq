<?php

/**
 * Parse any single CSS colour literal a design-system theme writes.
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
 * @spec openspec/specs/token-sync-workflow/spec.md#requirement-converted-and-gated-sync
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * Hex (3, 4, 6, 8 digits), rgb()/rgba() and hsl()/hsla() in comma or space syntax, plus
 * the HSL conversions the contrast repair needs. Mirrors `parseColorAlpha()`,
 * `hslToRgb()`, `rgbToHsl()` and `normaliseColour()` in js/lib/tokenConverter.js.
 *
 * @spec openspec/specs/token-sync-workflow/spec.md#requirement-converted-and-gated-sync
 */
class ColourLiteralParser {

	/**
	 * The CSS basic named colours a theme may write (buren: `white`); `transparent` is not one.
	 */
	private const NAMED_COLOURS = [
		'black' => '#000000',
		'white' => '#ffffff',
		'red' => '#ff0000',
		'green' => '#008000',
		'blue' => '#0000ff',
		'yellow' => '#ffff00',
		'orange' => '#ffa500',
		'purple' => '#800080',
		'gray' => '#808080',
		'grey' => '#808080',
		'silver' => '#c0c0c0',
		'maroon' => '#800000',
		'navy' => '#000080',
		'teal' => '#008080',
		'olive' => '#808000',
		'lime' => '#00ff00',
		'aqua' => '#00ffff',
		'fuchsia' => '#ff00ff',
	];

	/**
	 * Parse a colour literal into channels and alpha.
	 *
	 * @param string $value The raw value.
	 *
	 * @return array{0: int, 1: int, 2: int, 3: float}|null Channels and alpha, or null.
	 *
	 * @spec openspec/specs/token-sync-workflow/spec.md#requirement-converted-and-gated-sync
	 */
	public function parse(string $value): ?array {
		$trimmed = strtolower(trim($value));
		$trimmed = (self::NAMED_COLOURS[$trimmed] ?? $trimmed);
		if (preg_match('/^#([0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/', $trimmed, $hex) === 1) {
			return $this->parseHex(digits: $hex[1]);
		}

		if (preg_match('/^(rgba?|hsla?)\(\s*([^)]*)\)$/', $trimmed, $function) !== 1) {
			return null;
		}

		$parts = $this->splitArguments(arguments: $function[2]);
		if ($parts === null) {
			return null;
		}

		$alpha = $this->parseAlpha(part: ($parts[3] ?? '1'));
		$channels = $this->hslChannels(parts: $parts);
		if (str_starts_with($function[1], 'rgb') === true) {
			$channels = $this->rgbChannels(parts: $parts);
		}

		if ($channels === null || $alpha === null) {
			return null;
		}

		return [
			(int)max(0, min(255, round($channels[0]))),
			(int)max(0, min(255, round($channels[1]))),
			(int)max(0, min(255, round($channels[2]))),
			max(0.0, min(1.0, $alpha)),
		];
	}//end parse()

	/**
	 * Write a colour literal as `#rrggbb`, or `rgba(r, g, b, a)` when it is translucent.
	 *
	 * @param string $value The raw value.
	 *
	 * @return string|null The normalised colour, or null when it is not one.
	 *
	 * @spec openspec/specs/token-sync-workflow/spec.md#requirement-converted-and-gated-sync
	 */
	public function normalise(string $value): ?string {
		$parsed = $this->parse(value: $value);
		if ($parsed === null) {
			return null;
		}

		if ($parsed[3] >= 1.0) {
			return $this->toHex(rgb: [$parsed[0], $parsed[1], $parsed[2]]);
		}

		$alpha = rtrim(rtrim(number_format(num: (round($parsed[3] * 100) / 100), decimals: 2, decimal_separator: '.', thousands_separator: ''), '0'), '.');

		return 'rgba(' . $parsed[0] . ', ' . $parsed[1] . ', ' . $parsed[2] . ', ' . $alpha . ')';
	}//end normalise()

	/**
	 * HSL (hue in degrees, saturation and lightness in [0, 1]) to unrounded channels.
	 *
	 * @param float $hue The hue.
	 * @param float $saturation The saturation.
	 * @param float $lightness The lightness.
	 *
	 * @return array{0: float, 1: float, 2: float} The channels in [0, 255].
	 *
	 * @spec openspec/specs/token-sync-workflow/spec.md#requirement-converted-and-gated-sync
	 */
	public function hslToRgb(float $hue, float $saturation, float $lightness): array {
		$sextant = (fmod(fmod($hue, 360) + 360, 360) / 60);
		$chroma = ((1 - abs(2 * $lightness - 1)) * $saturation);
		$second = ($chroma * (1 - abs(fmod($sextant, 2) - 1)));
		$sectors = [
			[$chroma, $second, 0.0],
			[$second, $chroma, 0.0],
			[0.0, $chroma, $second],
			[0.0, $second, $chroma],
			[$second, 0.0, $chroma],
			[$chroma, 0.0, $second],
		];
		$sector = $sectors[max(0, min(5, (int)floor($sextant)))];
		$offset = ($lightness - $chroma / 2);

		return [($sector[0] + $offset) * 255, ($sector[1] + $offset) * 255, ($sector[2] + $offset) * 255];
	}//end hslToRgb()

	/**
	 * Channels to `[hue, saturation, lightness]` (degrees, [0, 1], [0, 1]).
	 *
	 * @param array{0: int, 1: int, 2: int} $rgb The colour.
	 *
	 * @return array{0: float, 1: float, 2: float} The HSL triple.
	 *
	 * @spec openspec/specs/token-sync-workflow/spec.md#requirement-converted-and-gated-sync
	 */
	public function rgbToHsl(array $rgb): array {
		$red = ($rgb[0] / 255.0);
		$green = ($rgb[1] / 255.0);
		$blue = ($rgb[2] / 255.0);
		$max = max($red, $green, $blue);
		$min = min($red, $green, $blue);
		$lightness = (($max + $min) / 2);
		$delta = ($max - $min);
		if (abs($delta) < 1e-12) {
			return [0.0, 0.0, $lightness];
		}

		$saturation = ($delta / (1 - abs(2 * $lightness - 1)));
		$hue = (60 * (($red - $green) / $delta + 4));
		if ($max === $red) {
			$hue = (60 * fmod(($green - $blue) / $delta, 6));
		}

		if ($max === $green && $max !== $red) {
			$hue = (60 * (($blue - $red) / $delta + 2));
		}

		return [fmod($hue + 360, 360), $saturation, $lightness];
	}//end rgbToHsl()

	/**
	 * Render channels as lower-case `#rrggbb`.
	 *
	 * @param array{0: int, 1: int, 2: int} $rgb The colour.
	 *
	 * @return string The hex string.
	 *
	 * @spec openspec/specs/token-sync-workflow/spec.md#requirement-converted-and-gated-sync
	 */
	public function toHex(array $rgb): string {
		return sprintf('#%02x%02x%02x', max(0, min(255, $rgb[0])), max(0, min(255, $rgb[1])), max(0, min(255, $rgb[2])));
	}//end toHex()

	/**
	 * Expand and parse 3, 4, 6 or 8 hex digits.
	 *
	 * @param string $digits The digits without `#`.
	 *
	 * @return array{0: int, 1: int, 2: int, 3: float} Channels and alpha.
	 */
	private function parseHex(string $digits): array {
		if (strlen($digits) <= 4) {
			$expanded = '';
			foreach (str_split($digits) as $digit) {
				$expanded .= $digit . $digit;
			}

			$digits = $expanded;
		}

		$alpha = 1.0;
		if (strlen($digits) === 8) {
			$alpha = (hexdec(substr($digits, 6, 2)) / 255);
		}

		return [(int)hexdec(substr($digits, 0, 2)), (int)hexdec(substr($digits, 2, 2)), (int)hexdec(substr($digits, 4, 2)), (float)$alpha];
	}//end parseHex()

	/**
	 * Split `a b c / d` or `a, b, c, d` into three or four arguments.
	 *
	 * @param string $arguments The text between the parentheses.
	 *
	 * @return array<int, string>|null The arguments, or null when the count is wrong.
	 */
	private function splitArguments(string $arguments): ?array {
		$normalised = (string)preg_replace('/\s*\/\s*/', ',', $arguments, 1);
		$normalised = (string)preg_replace('/\s*,\s*/', ',', $normalised);
		$parts = explode(',', (string)preg_replace('/\s+/', ',', $normalised));
		if (count($parts) < 3 || count($parts) > 4) {
			return null;
		}

		return $parts;
	}//end splitArguments()

	/**
	 * An alpha argument: a number, or a percentage.
	 *
	 * @param string $part The argument.
	 *
	 * @return float|null The alpha, or null when malformed.
	 */
	private function parseAlpha(string $part): ?float {
		$number = $this->number(part: $part);
		if ($number === null) {
			return null;
		}

		if (str_ends_with($part, '%') === true) {
			return ($number / 100);
		}

		return $number;
	}//end parseAlpha()

	/**
	 * The channels of rgb() arguments (numbers or percentages).
	 *
	 * @param array<int, string> $parts The arguments.
	 *
	 * @return array{0: float, 1: float, 2: float}|null The channels, or null when malformed.
	 */
	private function rgbChannels(array $parts): ?array {
		$channels = [];
		foreach (array_slice($parts, 0, 3) as $part) {
			$channel = $this->number(part: $part);
			if ($channel === null) {
				return null;
			}

			if (str_ends_with($part, '%') === true) {
				$channel = ($channel * 255 / 100);
			}

			$channels[] = $channel;
		}

		return [$channels[0], $channels[1], $channels[2]];
	}//end rgbChannels()

	/**
	 * The channels of hsl() arguments; saturation and lightness must be percentages.
	 *
	 * @param array<int, string> $parts The arguments.
	 *
	 * @return array{0: float, 1: float, 2: float}|null The channels, or null when malformed.
	 */
	private function hslChannels(array $parts): ?array {
		$hue = $this->number(part: (string)preg_replace('/(deg|turn|rad)$/', '', $parts[0]));
		$saturation = $this->number(part: $parts[1]);
		$lightness = $this->number(part: $parts[2]);
		if ($hue === null || $saturation === null || $lightness === null
			|| str_ends_with($parts[1], '%') === false || str_ends_with($parts[2], '%') === false
		) {
			return null;
		}

		if (str_ends_with($parts[0], 'turn') === true) {
			$hue = ($hue * 360);
		}

		if (str_ends_with($parts[0], 'rad') === true) {
			$hue = ($hue * 180 / M_PI);
		}

		return $this->hslToRgb(hue: $hue, saturation: ($saturation / 100), lightness: ($lightness / 100));
	}//end hslChannels()

	/**
	 * A numeric argument, without a trailing `%`.
	 *
	 * @param string $part The argument.
	 *
	 * @return float|null The number, or null when it is not one.
	 */
	private function number(string $part): ?float {
		$bare = rtrim($part, '%');
		if (is_numeric($bare) === false) {
			return null;
		}

		return (float)$bare;
	}//end number()
}//end class
