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
			$delta = ($step * 0.005);
			foreach ([($hsl[2] - $delta), ($hsl[2] + $delta)] as $lightness) {
				if ($lightness < 0 || $lightness > 1) {
					continue;
				}

				$channels = $this->parser->hslToRgb(hue: $hsl[0], saturation: $hsl[1], lightness: $lightness);
				$rgb = [(int)round($channels[0]), (int)round($channels[1]), (int)round($channels[2])];
				if ($this->contrast->ratio(first: $rgb, second: $background) >= $min) {
					return $rgb;
				}
			}
		}

		return $this->blackOrWhite(background: $background, preferWhite: false);
	}//end repairLightness()

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
			(int)round($parsed[0] * $alpha + $page[0] * (1 - $alpha)),
			(int)round($parsed[1] * $alpha + $page[1] * (1 - $alpha)),
			(int)round($parsed[2] * $alpha + $page[2] * (1 - $alpha)),
		];
	}//end opaque()
}//end class
