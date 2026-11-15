<?php

/**
 * Lightness repair and white-or-black choice for the token set converter.
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
 * The measuring half of ConverterColourRepair, mirroring `repairLightness()` and
 * `opaque()` in js/lib/tokenConverter.js.
 *
 * @spec openspec/specs/token-sync-workflow/spec.md#requirement-converted-and-gated-sync
 */
class ColourContrastAdjuster {

	/**
	 * Lightness steps of half a percent, both directions, before snapping to black or white.
	 */
	private const MAX_STEPS = 200;

	/**
	 * Constructor.
	 *
	 * @param ContrastService $contrast WCAG luminance and ratio.
	 * @param ColourLiteralParser $parser The colour parser.
	 */
	public function __construct(
		private readonly ContrastService $contrast,
		private readonly ColourLiteralParser $parser,
	) {
	}//end __construct()

	/**
	 * The smallest lightness change, hue and saturation kept, that reaches `min`.
	 *
	 * @param array{0: int, 1: int, 2: int} $foreground The foreground.
	 * @param array{0: int, 1: int, 2: int} $background The opaque background.
	 * @param float $min The threshold.
	 *
	 * @return array{0: int, 1: int, 2: int} The repaired foreground.
	 *
	 * @spec openspec/specs/token-sync-workflow/spec.md#requirement-converted-and-gated-sync
	 */
	public function repairLightness(array $foreground, array $background, float $min): array {
		$hsl = $this->parser->rgbToHsl(rgb: $foreground);
		for ($step = 1; $step <= self::MAX_STEPS; $step++) {
			foreach ($this->stepCandidates(hsl: $hsl, step: $step) as $rgb) {
				if ($this->contrast->ratio(first: $rgb, second: $background) >= $min) {
					return $rgb;
				}
			}
		}

		return $this->blackOrWhite(background: $background, preferWhite: false);
	}//end repairLightness()

	/**
	 * The colours one step darker and one step lighter, in that order, each only when its lightness is in 0..1.
	 * Yielded one at a time, so the next is computed only when the previous one falls short.
	 *
	 * @param array{0: float, 1: float, 2: float} $hsl The foreground's hue, saturation and lightness.
	 * @param int $step The step, in half percents of lightness.
	 *
	 * @return \Generator<int, array{0: int, 1: int, 2: int}> The candidates.
	 */
	private function stepCandidates(array $hsl, int $step): \Generator {
		$delta = ($step * 0.005);
		foreach ([($hsl[2] - $delta), ($hsl[2] + $delta)] as $lightness) {
			if ($lightness < 0 || $lightness > 1) {
				continue;
			}

			$channels = $this->parser->hslToRgb(hue: $hsl[0], saturation: $hsl[1], lightness: $lightness);
			yield [$this->channel(value: $channels[0]), $this->channel(value: $channels[1]), $this->channel(value: $channels[2])];
		}
	}//end stepCandidates()

	/**
	 * Whichever of white and black contrasts more with the background.
	 *
	 * @param array{0: int, 1: int, 2: int} $background The background.
	 * @param bool $preferWhite Which one wins a tie (mirrors the JavaScript order per caller).
	 *
	 * @return array{0: int, 1: int, 2: int} White or black.
	 *
	 * @spec openspec/specs/token-sync-workflow/spec.md#requirement-converted-and-gated-sync
	 */
	public function blackOrWhite(array $background, bool $preferWhite): array {
		$onWhite = $this->contrast->ratio(first: [255, 255, 255], second: $background);
		$onBlack = $this->contrast->ratio(first: [0, 0, 0], second: $background);
		if ($onWhite > $onBlack || ($onWhite === $onBlack && $preferWhite === true)) {
			return [255, 255, 255];
		}

		return [0, 0, 0];
	}//end blackOrWhite()

	/**
	 * A colour composited over the page, so a translucent fill is measured as seen.
	 *
	 * @param string $value The colour.
	 * @param array{0: int, 1: int, 2: int} $page The page colour.
	 *
	 * @return array{0: int, 1: int, 2: int}|null The opaque colour, or null.
	 *
	 * @spec openspec/specs/token-sync-workflow/spec.md#requirement-converted-and-gated-sync
	 */
	public function opaque(string $value, array $page): ?array {
		$parsed = $this->parser->parse(value: $value);
		if ($parsed === null) {
			return null;
		}

		$alpha = $parsed[3];

		return [
			$this->channel(value: $parsed[0] * $alpha + $page[0] * (1 - $alpha)),
			$this->channel(value: $parsed[1] * $alpha + $page[1] * (1 - $alpha)),
			$this->channel(value: $parsed[2] * $alpha + $page[2] * (1 - $alpha)),
		];
	}//end opaque()

	/**
	 * Round a 0-255 channel value, halves up, the same on every PHP version.
	 *
	 * The value is first rounded to 15 significant digits, so float noise such as
	 * 102.49999999999997 gives the same channel on PHP 8.3 and 8.4: PHP 8.3's round()
	 * did this pre-rounding itself, PHP 8.4's round() does not.
	 *
	 * @param float $value The channel value.
	 *
	 * @return int The channel.
	 */
	private function channel(float $value): int {
		if (abs($value) < 1.0E-9) {
			return 0;
		}

		$scale = (10 ** (14 - (int)floor(log10(abs($value)))));
		return (int)round(round($value * $scale) / $scale);
	}//end channel()
}//end class
