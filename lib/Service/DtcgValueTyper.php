<?php

/**
 * Thematiq DTCG value typer.
 *
 * Gives a CSS value its DTCG type by its shape (DTCG 2025.10 §8): a colour object with
 * its colour space and an sRGB `hex`, a dimension (`px`, `rem`), a duration (`ms`, `s`),
 * a cubic Bézier curve with both x in 0..1, a font stack, a font weight or a number.
 * A value is typed only when the importer writes it back to the same text; anything
 * else is left for the `cssOnly` map. Split out of {@see DesignTokensWriter}.
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
 * @spec openspec/specs/token-set-dtcg-export/spec.md#requirement-each-value-gets-a-dtcg-type-by-its-shape
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * CSS values to DTCG tokens.
 *
 * @spec openspec/specs/token-set-dtcg-export/spec.md#requirement-each-value-gets-a-dtcg-type-by-its-shape
 */
class DtcgValueTyper {

	/**
	 * A number, as CSS writes one.
	 *
	 * @var string
	 */
	private const NUMBER = '-?(?:\d+\.?\d*|\.\d+)';

	/**
	 * Constructor.
	 *
	 * @param CssColorParser      $colors    Reads colour values.
	 * @param ColorSpaceConverter $converter Gives every colour its sRGB hex fallback.
	 */
	public function __construct(
		private readonly CssColorParser $colors = new CssColorParser(),
		private readonly ColorSpaceConverter $converter = new ColorSpaceConverter(),
	) {
	}//end __construct()

	/**
	 * A token for a value DTCG can type, or null.
	 *
	 * @param string $name  The custom property.
	 * @param string $value The value.
	 *
	 * @return array<string, mixed>|null `$type` and `$value`, or `$alias` for a `var()` to resolve later.
	 *
	 * @spec openspec/specs/token-set-dtcg-export/spec.md#requirement-each-value-gets-a-dtcg-type-by-its-shape
	 */
	public function typed(string $name, string $value): ?array {
		if (preg_match('/^var\(\s*(--[A-Za-z0-9_-]+)\s*\)$/', $value, $match) === 1) {
			return ['$alias' => $match[1]];
		}

		$color = $this->colorToken(value: $value);
		if ($color !== null) {
			return $color;
		}

		return ($this->unitToken(value: $value) ?? $this->fontToken(name: $name, value: $value) ?? $this->numberToken(value: $value));
	}//end typed()

	/**
	 * A colour object with its colour space and an sRGB hex fallback.
	 *
	 * @param string $value The value.
	 *
	 * @return array<string, mixed>|null
	 */
	private function colorToken(string $value): ?array {
		$parsed = $this->colors->parse(value: $value);
		if ($parsed === null) {
			return null;
		}

		$rgb    = (array)$this->converter->toSrgb(space: $parsed['space'], components: $parsed['components']);
		$object = [
			'colorSpace' => $parsed['space'],
			'components' => array_map(static fn (float $v): float => round($v, 4), $parsed['components']),
			'hex' => $this->converter->toHex(rgb: $rgb),
		];
		if ($parsed['alpha'] !== null && $parsed['alpha'] < 1.0) {
			$object['alpha'] = round($parsed['alpha'], 3);
		}

		return ['$type' => 'color', '$value' => $object];
	}//end colorToken()

	/**
	 * A dimension (px, rem), a duration (ms, s) or a cubic Bézier curve.
	 *
	 * @param string $value The value.
	 *
	 * @return array<string, mixed>|null
	 */
	private function unitToken(string $value): ?array {
		if (preg_match('/^(' . self::NUMBER . ')(px|rem|ms|s)$/', $value, $match) === 1 && $this->exact(numbers: [$match[1]]) === true) {
			$type = 'dimension';
			if ($match[2] === 'ms' || $match[2] === 's') {
				$type = 'duration';
			}

			return ['$type' => $type, '$value' => ['value' => $this->toNumber(text: $match[1]), 'unit' => $match[2]]];
		}

		$n = '\s*(' . self::NUMBER . ')\s*';
		// DTCG 2025.10 section 8.6: both x values lie in 0..1; a curve outside that stays CSS only.
		if (preg_match('/^cubic-bezier\(' . $n . ',' . $n . ',' . $n . ',' . $n . '\)$/', $value, $match) === 1
			&& $this->exact(numbers: array_slice($match, 1, 4)) === true
			&& min((float)$match[1], (float)$match[3]) >= 0 && max((float)$match[1], (float)$match[3]) <= 1
		) {
			return ['$type' => 'cubicBezier', '$value' => array_map(fn (string $v): int|float => $this->toNumber(text: $v), array_slice($match, 1, 4))];
		}

		return null;
	}//end unitToken()

	/**
	 * A font stack in a font-family token, or a weight in a font-weight token. A stack is
	 * typed only when the importer writes it back to the same text.
	 *
	 * @param string $name  The custom property.
	 * @param string $value The value.
	 *
	 * @return array<string, mixed>|null
	 */
	private function fontToken(string $name, string $value): ?array {
		if (str_contains($name, 'font-weight') === true && preg_match('/^\d+$/', $value) === 1 && (int)$value >= 1 && (int)$value <= 1000) {
			return ['$type' => 'fontWeight', '$value' => (int)$value];
		}

		if (str_contains($name, 'font-family') === false || preg_match('/[()]/', $value) === 1) {
			return null;
		}

		$families = array_map(static fn (string $family): string => trim($family, " \t'\""), explode(',', $value));
		$written  = implode(', ', array_map(static fn (string $family): string => (string)preg_replace('/^(.* .*)$/', "'$1'", $family), $families));
		if ($written !== $value) {
			return null;
		}

		return ['$type' => 'fontFamily', '$value' => $families];
	}//end fontToken()

	/**
	 * A plain number.
	 *
	 * @param string $value The value.
	 *
	 * @return array<string, mixed>|null
	 */
	private function numberToken(string $value): ?array {
		if (preg_match('/^' . self::NUMBER . '$/', $value) !== 1 || $this->exact(numbers: [$value]) === false) {
			return null;
		}

		return ['$type' => 'number', '$value' => $this->toNumber(text: $value)];
	}//end numberToken()

	/**
	 * A number as an int when it is whole and written without a point, else a float.
	 *
	 * @param string $text The digits.
	 *
	 * @return int|float
	 */
	private function toNumber(string $text): int|float {
		if (preg_match('/^-?\d+$/', $text) === 1) {
			return (int)$text;
		}

		return (float)$text;
	}//end toNumber()

	/**
	 * Whether every number in a value reads back to the same text, so the import writes the
	 * value exactly as the set has it (`1.0rem` would come back as `1rem`).
	 *
	 * @param array<int, string> $numbers The number texts.
	 *
	 * @return bool
	 */
	private function exact(array $numbers): bool {
		foreach ($numbers as $text) {
			if ((string)$this->toNumber(text: $text) !== $text) {
				return false;
			}
		}

		return true;
	}//end exact()
}//end class
