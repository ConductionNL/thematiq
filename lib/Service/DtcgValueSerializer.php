<?php

/**
 * Serializes the single-target W3C Design Tokens values to CSS literals.
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
 * Serializes a DTCG single-target value by its `$type`: colour, dimension and duration, cubic
 * Bezier, font family and font weight; any other type passes a scalar through.
 *
 * @spec openspec/specs/custom-token-sets/spec.md
 */
class DtcgValueSerializer {

	/**
	 * The v2025.10 `fontWeight` keyword => numeric CSS value table.
	 *
	 * @var array<string, int>
	 */
	private const FONT_WEIGHT_KEYWORDS = [
		'thin' => 100,
		'hairline' => 100,
		'extra-light' => 200,
		'ultra-light' => 200,
		'light' => 300,
		'normal' => 400,
		'regular' => 400,
		'book' => 400,
		'medium' => 500,
		'semi-bold' => 600,
		'demi-bold' => 600,
		'bold' => 700,
		'extra-bold' => 800,
		'ultra-bold' => 800,
		'black' => 900,
		'heavy' => 900,
		'extra-black' => 950,
		'ultra-black' => 950,
	];

	/**
	 * A serializer that hands colours to the colour serializer.
	 *
	 * @param DtcgColourSerializer $colours The colour serializer.
	 */
	public function __construct(
		private readonly DtcgColourSerializer $colours,
	) {
	}//end __construct()

	/**
	 * Serialize a value by its declared type.
	 *
	 * Any other declared type (shadow, number, …) is not yet a first-class serializer here: it
	 * falls back to the scalar passthrough, so every document the previous mapper handled keeps working.
	 *
	 * @param string $type The resolved `$type`.
	 * @param mixed $value The resolved `$value`.
	 *
	 * @return array{ok: bool, value?: string, reason?: string, detail?: string, adapted?: array<string, mixed>} The serialization result.
	 *
	 * @spec openspec/specs/custom-token-sets/spec.md
	 */
	public function serialize(string $type, mixed $value): array {
		return match ($type) {
			'color' => $this->colours->serializeColor(value: $value),
			'dimension', 'duration' => $this->serializeDimension(value: $value),
			'cubicBezier' => $this->serializeCubicBezier(value: $value),
			'fontFamily' => $this->serializeFontFamily(value: $value),
			'fontWeight' => $this->serializeFontWeight(value: $value),
			default => $this->serializeScalarPassthrough(value: $value),
		};
	}//end serialize()

	/**
	 * Serialize a `cubicBezier` value: the four-number array, or a legacy string.
	 *
	 * @param mixed $value The resolved `$value`.
	 *
	 * @return array{ok: bool, value?: string, reason?: string, adapted?: array<string, mixed>} The serialization result.
	 *
	 * @spec openspec/specs/token-set-dtcg-export/spec.md#requirement-a-thematiq-round-trip-is-exact
	 */
	private function serializeCubicBezier($value): array {
		if (is_string($value) === true) {
			return ['ok' => true, 'value' => trim($value)];
		}

		if (is_array($value) === false || count($value) !== 4 || count(array_filter($value, 'is_numeric')) !== 4) {
			return ['ok' => false, 'reason' => 'unsupported-value-shape'];
		}

		return ['ok' => true, 'value' => 'cubic-bezier(' . implode(', ', array_map('strval', array_values($value))) . ')'];
	}//end serializeCubicBezier()

	/**
	 * Legacy scalar-passthrough "serializer" for any declared type this
	 * mapper does not yet have a first-class serializer for (shadow, number,
	 * …) — preserves the original mapper's tolerant behaviour.
	 *
	 * @param mixed $value The resolved `$value`.
	 *
	 * @return array{ok: bool, value?: string, reason?: string, adapted?: array<string, mixed>} The serialization result.
	 *
	 * @spec openspec/specs/custom-token-sets/spec.md
	 */
	private function serializeScalarPassthrough($value): array {
		if (is_scalar($value) === false) {
			return ['ok' => false, 'reason' => 'unsupported-value-shape'];
		}

		return ['ok' => true, 'value' => (string)$value];
	}//end serializeScalarPassthrough()

	/**
	 * Serialize a `dimension` value: legacy string passthrough, or the
	 * `{value, unit}` object form concatenated as `<value><unit>`.
	 *
	 * @param mixed $value The resolved `$value`.
	 *
	 * @return array{ok: bool, value?: string, reason?: string} The serialization result.
	 *
	 * @spec openspec/specs/custom-token-sets/spec.md
	 */
	private function serializeDimension($value): array {
		if (is_string($value) === true) {
			return ['ok' => true, 'value' => trim($value)];
		}

		if ($this->isUnitObject(value: $value) === true) {
			return ['ok' => true, 'value' => $value['value'] . $value['unit']];
		}

		return ['ok' => false, 'reason' => 'unsupported-value-shape'];
	}//end serializeDimension()

	/**
	 * Whether a dimension value is the `{value, unit}` object form.
	 *
	 * @param mixed $value The raw `$value`.
	 *
	 * @return bool True for a scalar value with a string unit.
	 */
	private function isUnitObject(mixed $value): bool {
		return is_array($value) === true
			&& array_key_exists('value', $value) === true
			&& array_key_exists('unit', $value) === true
			&& is_scalar($value['value']) === true
			&& is_string($value['unit']) === true;
	}//end isUnitObject()

	/**
	 * Serialize a `fontFamily` value: a bare string, or an array serialized
	 * as a quoted CSS font stack (names containing whitespace are quoted;
	 * single-word names and generic keywords are left bare).
	 *
	 * @param mixed $value The resolved `$value`.
	 *
	 * @return array{ok: bool, value?: string, reason?: string} The serialization result.
	 *
	 * @spec openspec/specs/custom-token-sets/spec.md
	 */
	public function serializeFontFamily($value): array {
		if (is_string($value) === true) {
			return ['ok' => true, 'value' => trim($value)];
		}

		if (is_array($value) === true) {
			$parts = $this->fontFamilyParts(fonts: $value);
			if (empty($parts) === true) {
				return ['ok' => false, 'reason' => 'unsupported-value-shape'];
			}

			return ['ok' => true, 'value' => implode(', ', $parts)];
		}

		return ['ok' => false, 'reason' => 'unsupported-value-shape'];
	}//end serializeFontFamily()

	/**
	 * The non-empty string families of a font list, trimmed, a name with a space quoted.
	 *
	 * @param array<mixed> $fonts The font list.
	 *
	 * @return array<int, string> The families.
	 */
	private function fontFamilyParts(array $fonts): array {
		$parts = [];
		foreach ($fonts as $font) {
			if (is_string($font) === false || trim($font) === '') {
				continue;
			}

			$font = trim($font);
			$quoted = $font;
			if (str_contains($font, ' ') === true) {
				$quoted = "'" . $font . "'";
			}

			$parts[] = $quoted;
		}

		return $parts;
	}//end fontFamilyParts()

	/**
	 * Serialize a `fontWeight` value: a number, or a v2025.10 weight keyword
	 * normalized to its numeric CSS value.
	 *
	 * @param mixed $value The resolved `$value`.
	 *
	 * @return array{ok: bool, value?: string, reason?: string} The serialization result.
	 *
	 * @spec openspec/specs/custom-token-sets/spec.md
	 */
	private function serializeFontWeight($value): array {
		if (is_int($value) === true || is_float($value) === true) {
			return ['ok' => true, 'value' => (string)((int)$value)];
		}

		$weight = null;
		if (is_string($value) === true) {
			$weight = $this->fontWeightOf(text: trim($value));
		}

		if ($weight === null) {
			return ['ok' => false, 'reason' => 'unsupported-value-shape'];
		}

		return ['ok' => true, 'value' => $weight];
	}//end serializeFontWeight()

	/**
	 * A font weight written as text: a number, or one of the CSS keywords.
	 *
	 * @param string $text The trimmed value.
	 *
	 * @return string|null The numeric weight, or null when the text is neither.
	 */
	private function fontWeightOf(string $text): ?string {
		if (is_numeric($text) === true) {
			return (string)((int)$text);
		}

		$key = strtolower($text);
		if (isset(self::FONT_WEIGHT_KEYWORDS[$key]) === false) {
			return null;
		}

		return (string)self::FONT_WEIGHT_KEYWORDS[$key];
	}//end fontWeightOf()
}//end class
