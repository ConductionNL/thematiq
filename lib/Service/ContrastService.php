<?php

/**
 * NL Design WCAG Contrast Service.
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
 * @spec openspec/changes/archive/2026-06-14-custom-token-set-upload/tasks.md#task-1.3
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * Computes WCAG 2.1 relative-luminance contrast ratios for token pairs.
 *
 * Only hex (#rgb / #rrggbb) and rgb()/rgba() literal values are evaluated.
 * Values that reference a CSS variable (var(--…)) or any other non-literal
 * construct are reported as `unevaluated` so the caller never treats an
 * unknown colour as a passing one.
 *
 * @spec openspec/changes/archive/2026-06-14-custom-token-set-upload/tasks.md#task-1.3
 */
class ContrastService {

	/**
	 * The fixed token pairs that are contrast-checked on upload.
	 *
	 * Each entry: foreground token, background token, AA threshold ratio.
	 *
	 * @var array<array{fg: string, bg: string, threshold: float}>
	 */
	private const PAIRS = [
		[
			'fg' => '--nldesign-color-primary-text',
			'bg' => '--nldesign-color-primary',
			'threshold' => 4.5,
		],
		[
			'fg' => '--nldesign-color-primary',
			'bg' => '--nldesign-color-background',
			'threshold' => 3.0,
		],
	];

	/**
	 * WCAG AA threshold (ratio) for `evaluate()` `role: "text"` candidates.
	 */
	public const ROLE_TEXT_THRESHOLD = 4.5;

	/**
	 * WCAG AA threshold (ratio) for `evaluate()` `role: "ui"` candidates.
	 */
	public const ROLE_UI_THRESHOLD = 3.0;

	/**
	 * Compute contrast warnings for the supplied token declarations.
	 *
	 * Returns one warning per fixed pair whose computed ratio is below the
	 * AA threshold, or whose values could not be parsed (`unevaluated`).
	 * Pairs that meet the threshold produce no entry.
	 *
	 * @param array<string, string> $declarations Map of token name => value.
	 *
	 * @return array<array{pair: string, ratio: float|null, threshold: float, level: string, unevaluated?: bool}>
	 *                                                                                                            The non-blocking contrast warnings.
	 *
	 * @spec openspec/changes/archive/2026-06-14-custom-token-set-upload/tasks.md#task-1.3
	 */
	public function check(array $declarations): array {
		$warnings = [];

		foreach (self::PAIRS as $pair) {
			$fgValue = $declarations[$pair['fg']] ?? null;
			$bgValue = $declarations[$pair['bg']] ?? null;

			// A pair can only be evaluated when both tokens are present.
			if ($fgValue === null || $bgValue === null) {
				continue;
			}

			// Translucent colours are measured as they render: the background
			// over the page background, the foreground over that.
			$ratio = $this->measure(
				foreground: $fgValue,
				background: $bgValue,
				page: ($declarations['--nldesign-color-background'] ?? null)
			);

			$pairLabel = $pair['fg'] . ' vs ' . $pair['bg'];

			if ($ratio === null) {
				$warnings[] = [
					'pair' => $pairLabel,
					'ratio' => null,
					'threshold' => $pair['threshold'],
					'level' => 'AA',
					'unevaluated' => true,
				];
				continue;
			}

			if ($ratio < $pair['threshold']) {
				$warnings[] = [
					'pair' => $pairLabel,
					'ratio' => round($ratio, 2),
					'threshold' => $pair['threshold'],
					'level' => 'AA',
				];
			}
		}//end foreach

		return $warnings;
	}//end check()

	/**
	 * Evaluate an arbitrary list of candidate colors against one background,
	 * generalising `check()` beyond its two fixed token pairs.
	 *
	 * Reuses `relativeLuminance()`/`ratio()`/`parseColor()` unchanged — only
	 * the pairing/threshold selection is generalised, so this method can
	 * never disagree with `check()`'s underlying math. Threshold by role:
	 * `text` → 4.5:1, `ui` → 3.0:1. A candidate (or the background) that is
	 * not a parseable literal color is reported `unevaluated: true`,
	 * `ratio: null`, and is NEVER reported as `pass: true` — an unresolvable
	 * value must never be treated as passing. The response never contains a
	 * `blocked`/`allowed`/`verdict` field: this method reports facts only,
	 * the caller decides what to do with them.
	 *
	 * @param array<int, array{name: string, value: string, role: string}> $candidates The candidate colors to evaluate.
	 * @param string $background The background color (hex or rgb()/rgba()).
	 *
	 * One result per candidate, in the given order.
	 *
	 * @return array<int, array{name: string, ratio: float|null, threshold: float, level: string, pass: bool, unevaluated?: bool}>
	 *
	 * @spec openspec/specs/app-token-set-selection/spec.md
	 */
	public function evaluate(array $candidates, string $background): array {
		$results = [];
		foreach ($candidates as $candidate) {
			$threshold = self::ROLE_UI_THRESHOLD;
			if ($candidate['role'] === 'text') {
				$threshold = self::ROLE_TEXT_THRESHOLD;
			}

			// A translucent candidate is measured blended over the background.
			$measured = $this->measure(foreground: (string)$candidate['value'], background: $background);

			if ($measured === null) {
				$results[] = [
					'name' => $candidate['name'],
					'ratio' => null,
					'threshold' => $threshold,
					'level' => 'AA',
					'pass' => false,
					'unevaluated' => true,
				];
				continue;
			}

			$ratio = round($measured, 2);

			$results[] = [
				'name' => $candidate['name'],
				'ratio' => $ratio,
				'threshold' => $threshold,
				'level' => 'AA',
				'pass' => ($ratio >= $threshold),
			];
		}//end foreach

		return $results;
	}//end evaluate()

	/**
	 * Compute the WCAG 2.1 contrast ratio between two RGB colours.
	 *
	 * Ratio = (L1 + 0.05) / (L2 + 0.05) where L1 is the lighter relative
	 * luminance. The result lies in the range [1, 21].
	 *
	 * @param array{0: int, 1: int, 2: int} $first First colour as [r, g, b].
	 * @param array{0: int, 1: int, 2: int} $second Second colour as [r, g, b].
	 *
	 * @return float The contrast ratio.
	 *
	 * @spec openspec/changes/archive/2026-06-14-custom-token-set-upload/tasks.md#task-1.3
	 */
	public function ratio(array $first, array $second): float {
		$lum1 = $this->relativeLuminance(rgb: $first);
		$lum2 = $this->relativeLuminance(rgb: $second);

		$lighter = max($lum1, $lum2);
		$darker = min($lum1, $lum2);

		return (($lighter + 0.05) / ($darker + 0.05));
	}//end ratio()

	/**
	 * Compute the WCAG 2.1 relative luminance of an RGB colour.
	 *
	 * @param array{0: int, 1: int, 2: int} $rgb The colour as [r, g, b] (0-255).
	 *
	 * @return float The relative luminance in [0, 1].
	 *
	 * @spec openspec/changes/archive/2026-06-14-custom-token-set-upload/tasks.md#task-1.3
	 */
	private function relativeLuminance(array $rgb): float {
		$channels = [];
		foreach ($rgb as $value) {
			$srgb = ($value / 255);
			$channel = ((($srgb + 0.055) / 1.055) ** 2.4);
			if ($srgb <= 0.03928) {
				$channel = ($srgb / 12.92);
			}

			$channels[] = $channel;
		}

		return ((0.2126 * $channels[0]) + (0.7152 * $channels[1]) + (0.0722 * $channels[2]));
	}//end relativeLuminance()

	/**
	 * Parse a CSS colour literal into an [r, g, b] triple.
	 *
	 * Supports #rgb, #rgba, #rrggbb, #rrggbbaa, rgb() and rgba(). The alpha is
	 * dropped here, so this answers "which colour", not "what renders"; a
	 * contrast measurement goes through {@see self::measure()}, which blends.
	 * Any other value (var(), named colours, hsl(), gradients) returns null so
	 * the caller can mark the pair unevaluated.
	 *
	 * @param string $value The raw CSS colour value.
	 *
	 * @return array{0: int, 1: int, 2: int}|null The parsed RGB triple, or null.
	 *
	 * @spec openspec/changes/archive/2026-06-14-custom-token-set-upload/tasks.md#task-1.3
	 */
	public function parseColor(string $value): ?array {
		$rgba = $this->parseColorWithAlpha(value: $value);
		if ($rgba === null) {
			return null;
		}

		return [$rgba[0], $rgba[1], $rgba[2]];
	}//end parseColor()

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
	 * @spec openspec/changes/authoring-token-value-types/tasks.md#task-3.1
	 */
	public function parseColorWithAlpha(string $value): ?array {
		$value = trim($value);

		if (preg_match('/^#([0-9a-fA-F]{3,4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $value, $match) === 1) {
			return $this->parseHex(hex: $match[1]);
		}

		if (preg_match('/^rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*(?:,\s*([\d.]+)(%?)\s*)?\)$/', $value, $match) === 1) {
			$alpha = 1.0;
			if (isset($match[4]) === true && $match[4] !== '') {
				$alpha = (float)$match[4];
				if (($match[5] ?? '') === '%') {
					$alpha = ($alpha / 100);
				}
			}

			return [
				min(255, (int)$match[1]),
				min(255, (int)$match[2]),
				min(255, (int)$match[3]),
				round(max(0.0, min(1.0, $alpha)), 3),
			];
		}

		return null;
	}//end parseColorWithAlpha()

	/**
	 * Expand and parse the digits of a 3, 4, 6 or 8 digit hex colour.
	 *
	 * @param string $hex The hex digits without the leading '#'.
	 *
	 * @return array{0: int, 1: int, 2: int, 3: float} The parsed colour.
	 */
	private function parseHex(string $hex): array {
		if (strlen($hex) <= 4) {
			$expanded = '';
			foreach (str_split($hex) as $digit) {
				$expanded .= $digit . $digit;
			}

			$hex = $expanded;
		}

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
	 * Composite a colour with alpha over an opaque colour ("source over").
	 *
	 * @param array{0: int, 1: int, 2: int, 3: float} $top The colour on top.
	 * @param array{0: int, 1: int, 2: int} $under The opaque colour underneath.
	 *
	 * @return array{0: int, 1: int, 2: int} The opaque colour that renders.
	 *
	 * @spec openspec/changes/authoring-token-value-types/tasks.md#task-3.1
	 */
	public function blend(array $top, array $under): array {
		$alpha = $top[3];
		$result = [];
		foreach ([0, 1, 2] as $index) {
			$result[] = (int)round(($top[$index] * $alpha) + ($under[$index] * (1 - $alpha)));
		}

		return [$result[0], $result[1], $result[2]];
	}//end blend()

	/**
	 * Measure the contrast of a foreground over a background as it renders.
	 *
	 * A translucent background is blended over the page background first (the
	 * page itself over white), then a translucent foreground over that result.
	 * Opaque colours measure exactly as {@see self::ratio()} on their channels.
	 *
	 * @param string $foreground The foreground colour literal.
	 * @param string $background The background colour literal.
	 * @param string|null $page The page background under a translucent background; white when null or unparseable.
	 *
	 * @return float|null The unrounded ratio, or null when either colour is not a parseable literal.
	 *
	 * @spec openspec/changes/authoring-token-value-types/tasks.md#task-3.1
	 */
	public function measure(string $foreground, string $background, ?string $page = null): ?float {
		$fgRgba = $this->parseColorWithAlpha(value: $foreground);
		$bgRgba = $this->parseColorWithAlpha(value: $background);
		if ($fgRgba === null || $bgRgba === null) {
			return null;
		}

		$white = [255, 255, 255];
		$pageRgb = $white;
		$pageRgba = null;
		if ($page !== null) {
			$pageRgba = $this->parseColorWithAlpha(value: $page);
		}

		if ($pageRgba !== null) {
			$pageRgb = $this->blend(top: $pageRgba, under: $white);
		}

		$bgRgb = $this->blend(top: $bgRgba, under: $pageRgb);
		$fgRgb = $this->blend(top: $fgRgba, under: $bgRgb);

		return $this->ratio(first: $fgRgb, second: $bgRgb);
	}//end measure()
}//end class
