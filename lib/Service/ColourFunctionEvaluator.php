<?php

/**
 * Turns the CSS colour functions a token set or the public bridge uses into hex.
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
 * @spec openspec/changes/denhaag-component-tokens/specs/token-set-contrast-audit/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * Evaluates `hsl()`/`hsla()` and `color-mix(in srgb, …)` to a hex literal.
 *
 * ContrastService reads hex and rgb() only. Some sets declare their status
 * colours in hsl() (westervoort's warning is `hsl(38deg 46% 39%)`), and the
 * bridge derives darker status text with color-mix(). Without this a pair on
 * those colours stays `unevaluated`, which is honest but measures nothing.
 * A value this class does not recognise comes back unchanged, so the caller
 * still reports it as unevaluated.
 *
 * @spec openspec/changes/denhaag-component-tokens/specs/token-set-contrast-audit/spec.md
 */
class ColourFunctionEvaluator {

	/**
	 * Constructor.
	 *
	 * @param ContrastService $contrast Parses the hex and rgb() literals inside a function.
	 *
	 * @spec openspec/changes/denhaag-component-tokens/specs/token-set-contrast-audit/spec.md
	 */
	public function __construct(
		private readonly ContrastService $contrast = new ContrastService(),
	) {
	}//end __construct()

	/**
	 * Evaluate a colour value to hex when it is a function this class knows.
	 *
	 * @param string $value The resolved value, free of var().
	 *
	 * @return string A hex literal, or the value unchanged.
	 *
	 * @spec openspec/changes/denhaag-component-tokens/specs/token-set-contrast-audit/spec.md
	 */
	public function evaluate(string $value): string {
		$value = trim($value);
		$hsl = $this->hsl(value: $value);
		if ($hsl !== null) {
			return $this->hex(rgb: $hsl);
		}

		$mixed = $this->mix(value: $value);
		if ($mixed !== null) {
			return $this->hex(rgb: $mixed);
		}

		return $value;
	}//end evaluate()

	/**
	 * Parse hsl() or hsla(), comma or space separated, hue with or without `deg`.
	 *
	 * The alpha of an hsla() is dropped: no audited pair uses a translucent hsl.
	 *
	 * @param string $value The value.
	 *
	 * @return array{0: int, 1: int, 2: int}|null The RGB triple, or null when the value is not hsl.
	 */
	private function hsl(string $value): ?array {
		$pattern = '/^hsla?\(\s*(-?[\d.]+)(?:deg)?[\s,]+([\d.]+)%[\s,]+([\d.]+)%\s*(?:[,\/]\s*[\d.]+%?\s*)?\)$/i';
		if (preg_match($pattern, $value, $match) !== 1) {
			return null;
		}

		$hue = fmod(((float)$match[1] / 360) + 1, 1);
		$sat = min(1.0, ((float)$match[2] / 100));
		$light = min(1.0, ((float)$match[3] / 100));
		$chroma = ($sat * min($light, (1 - $light)));

		$channel = static function (int $offset) use ($hue, $light, $chroma): int {
			$step = fmod(($offset + ($hue * 12)), 12);
			$value = ($light - ($chroma * max(-1, min(($step - 3), (9 - $step), 1))));
			return (int)round($value * 255);
		};

		return [$channel(0), $channel(8), $channel(4)];
	}//end hsl()

	/**
	 * Evaluate `color-mix(in srgb, A p%, B)`, where B takes the rest of the share.
	 *
	 * @param string $value The value.
	 *
	 * @return array{0: int, 1: int, 2: int}|null The RGB triple, or null when the value is not such a mix.
	 */
	private function mix(string $value): ?array {
		$pattern = '/^color-mix\(\s*in\s+srgb\s*,\s*(.+?)\s+([\d.]+)%\s*,\s*(.+?)\s*\)$/is';
		if (preg_match($pattern, $value, $match) !== 1) {
			return null;
		}

		$first = $this->contrast->parseColor(value: $this->evaluate(value: $match[1]));
		$second = $this->contrast->parseColor(value: $this->evaluate(value: $match[3]));
		if ($first === null || $second === null) {
			return null;
		}

		$share = min(1.0, ((float)$match[2] / 100));
		$channel = static fn (int $index): int => (int)round(($first[$index] * $share) + ($second[$index] * (1 - $share)));

		return [$channel(0), $channel(1), $channel(2)];
	}//end mix()

	/**
	 * Format an RGB triple as a hex literal.
	 *
	 * @param array{0: int, 1: int, 2: int} $rgb The triple.
	 *
	 * @return string The hex literal.
	 */
	private function hex(array $rgb): string {
		return sprintf('#%02x%02x%02x', $rgb[0], $rgb[1], $rgb[2]);
	}//end hex()
}//end class
