<?php

/**
 * Thematiq Brand Form Service.
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
 * @spec openspec/specs/simple-brand-form/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use InvalidArgumentException;

/**
 * Derives a complete token set from a primary and a background colour, by the rules in
 * `scripts/mapping/brand-form.json`. `js/lib/brandForm.js` applies the same rules to the same
 * defaults, so the preview shows exactly what is stored.
 *
 * @spec openspec/specs/simple-brand-form/spec.md#requirement-the-derived-set-is-complete
 */
class BrandFormService {

	/**
	 * The rules file, relative to the app root.
	 *
	 * @var string
	 */
	public const RULES = 'scripts/mapping/brand-form.json';

	/**
	 * The defaults layer, relative to the app root.
	 *
	 * @var string
	 */
	public const DEFAULTS = 'css/systems/nldesign/defaults.css';

	/**
	 * Text contrast threshold (WCAG 1.4.3).
	 *
	 * @var float
	 */
	public const TEXT_MIN = 4.5;

	/**
	 * UI component contrast threshold (WCAG 1.4.11).
	 *
	 * @var float
	 */
	public const UI_MIN = 3.0;

	/**
	 * Constructor.
	 *
	 * @param ContrastService $contrast The contrast arithmetic.
	 * @param CssParserService $parser Reads the defaults layer.
	 */
	public function __construct(
		private ContrastService $contrast,
		private CssParserService $parser,
	) {
	}//end __construct()

	/**
	 * The rules and the defaults, as the browser preview needs them.
	 *
	 * @param string $appPath The app root.
	 *
	 * @return array{rules: array<string, array<int, mixed>>, defaults: array<string, string>} The inputs of derive().
	 *
	 * @spec openspec/specs/simple-brand-form/spec.md#requirement-the-derived-set-is-complete
	 */
	public function inputs(string $appPath): array {
		$rules = json_decode((string)file_get_contents($appPath . '/' . self::RULES), true);
		$defaults = $this->parser->parseRootBlock(css: (string)file_get_contents($appPath . '/' . self::DEFAULTS));

		return ['rules' => (array)($rules['tokens'] ?? []), 'defaults' => $defaults];
	}//end inputs()

	/**
	 * Derive the set.
	 *
	 * Returns the declarations in rule order, the text colour on primary, and the two ratios
	 * (two decimals).
	 *
	 * @param string $appPath The app root.
	 * @param string $primary The primary colour, `#rgb` or `#rrggbb`.
	 * @param string $background The background colour, `#rgb` or `#rrggbb`.
	 *
	 * @return array{declarations: array<string, string>, textOnPrimary: string, textRatio: float, uiRatio: float}
	 *
	 * @throws InvalidArgumentException When a colour is not a hex colour, or a rule resolves to nothing.
	 *
	 * @spec openspec/specs/simple-brand-form/spec.md#requirement-the-derived-set-is-complete
	 * @spec openspec/specs/simple-brand-form/spec.md#requirement-text-on-primary-is-chosen-for-contrast
	 */
	public function derive(string $appPath, string $primary, string $background): array {
		$inputs = $this->inputs(appPath: $appPath);
		$base = ['primary' => $this->hex(value: $primary), 'background' => $this->hex(value: $background)];
		$declarations = [];
		foreach ($inputs['rules'] as $token => $rule) {
			$value = $this->apply(rule: (array)$rule, known: array_merge($base, $declarations), defaults: $inputs['defaults'], token: $token);
			if ($value === '') {
				throw new InvalidArgumentException('No value for ' . $token);
			}

			$declarations[$token] = $value;
		}

		$onPrimary = $this->onColor(background: $base['primary']);

		return [
			'declarations' => $declarations,
			'textOnPrimary' => $onPrimary,
			'textRatio' => $this->ratio(first: $base['primary'], second: $onPrimary),
			'uiRatio' => $this->ratio(first: $base['primary'], second: $base['background']),
		];
	}//end derive()

	/**
	 * Apply one rule.
	 *
	 * @param array<int, mixed> $rule `[op, ...operands]`.
	 * @param array<string, string> $known `primary`, `background` and the tokens derived so far.
	 * @param array<string, string> $defaults The defaults layer.
	 * @param string $token The token being derived.
	 *
	 * @return string The value, or '' when the rule resolves to nothing.
	 */
	private function apply(array $rule, array $known, array $defaults, string $token): string {
		$first = (string)($known[(string)($rule[1] ?? '')] ?? '');

		return match ((string)($rule[0] ?? '')) {
			'from' => $first,
			'onColor' => $this->onColor(background: $first),
			'darken' => $this->darken(value: $first, fraction: (float)($rule[2] ?? 0)),
			'mix' => $this->mix(value: $first, with: (string)($known[(string)($rule[2] ?? '')] ?? ''), weight: (float)($rule[3] ?? 1)),
			'legible' => $this->legible(value: $first, background: $known['background'], fallback: (string)($defaults[$token] ?? '')),
			default => (string)($defaults[$token] ?? ''),
		};
	}//end apply()

	/**
	 * A validated, lower-case six-digit hex colour.
	 *
	 * @param string $value The input.
	 *
	 * @return string The colour.
	 *
	 * @throws InvalidArgumentException When it is not `#rgb` or `#rrggbb`.
	 */
	private function hex(string $value): string {
		$value = strtolower(trim($value));
		if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/', $value) !== 1) {
			throw new InvalidArgumentException('Not a hex colour: ' . $value);
		}

		$rgb = (array)$this->contrast->parseColor(value: $value);

		return $this->toHex(rgb: [(int)$rgb[0], (int)$rgb[1], (int)$rgb[2]]);
	}//end hex()

	/**
	 * Black or white, whichever contrasts better with the background; white on a tie.
	 * Mirrors `onColor()` in `js/lib/tokenConverter.js`.
	 *
	 * @param string $background The background.
	 *
	 * @return string `#ffffff` or `#000000`.
	 */
	private function onColor(string $background): string {
		if ($this->ratio(first: $background, second: '#ffffff') >= $this->ratio(first: $background, second: '#000000')) {
			return '#ffffff';
		}

		return '#000000';
	}//end onColor()

	/**
	 * The value when it reaches 4.5:1 on the background, else the fallback.
	 *
	 * @param string $value The candidate.
	 * @param string $background The background.
	 * @param string $fallback The defaults value.
	 *
	 * @return string The legible value.
	 */
	private function legible(string $value, string $background, string $fallback): string {
		if ($this->ratio(first: $value, second: $background) >= self::TEXT_MIN) {
			return $value;
		}

		return $fallback;
	}//end legible()

	/**
	 * The contrast ratio of two colours, rounded to two decimals.
	 *
	 * @param string $first One colour.
	 * @param string $second The other.
	 *
	 * @return float The ratio, 1.0 when either is not a colour.
	 */
	private function ratio(string $first, string $second): float {
		$one = $this->contrast->parseColor(value: $first);
		$two = $this->contrast->parseColor(value: $second);
		if ($one === null || $two === null) {
			return 1.0;
		}

		return round($this->contrast->ratio(first: $one, second: $two), 2);
	}//end ratio()

	/**
	 * Darken a colour. Mirrors `darken()` in `js/lib/tokenConverter.js` and `TokenSetConverterService`.
	 *
	 * @param string $value The colour.
	 * @param float $fraction 0 unchanged, 1 black.
	 *
	 * @return string The colour.
	 */
	private function darken(string $value, float $fraction): string {
		$rgb = $this->contrast->parseColor(value: $value);
		if ($rgb === null) {
			return $value;
		}

		$factor = (1 - min(1, max(0, $fraction)));

		return $this->toHex(rgb: array_map(fn (int $channel): int => max(0, (int)round($channel * $factor)), $rgb));
	}//end darken()

	/**
	 * Mix two colours; `weight` is the share of the first kept. Mirrors `mix()` in `js/lib/tokenConverter.js`.
	 *
	 * @param string $value The first colour.
	 * @param string $with The second colour.
	 * @param float $weight Share of the first (0-1).
	 *
	 * @return string The colour.
	 */
	private function mix(string $value, string $with, float $weight): string {
		$base = $this->contrast->parseColor(value: $value);
		$other = $this->contrast->parseColor(value: $with);
		if ($base === null || $other === null) {
			return $value;
		}

		$share = min(1, max(0, $weight));
		$out = [];
		foreach ([0, 1, 2] as $index) {
			$out[] = (int)round(($base[$index] * $share) + ($other[$index] * (1 - $share)));
		}

		return $this->toHex(rgb: $out);
	}//end mix()

	/**
	 * Render an RGB triple as `#rrggbb`.
	 *
	 * @param array<int, int> $rgb The channels.
	 *
	 * @return string The colour.
	 */
	private function toHex(array $rgb): string {
		return sprintf('#%02x%02x%02x', max(0, min(255, $rgb[0])), max(0, min(255, $rgb[1])), max(0, min(255, $rgb[2])));
	}//end toHex()
}//end class
