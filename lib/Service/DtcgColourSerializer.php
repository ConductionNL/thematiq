<?php

/**
 * Serializes a W3C Design Tokens colour value to a CSS literal.
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
 * Serializes a DTCG `color` value to CSS: a string as written, an object in any colour space the
 * DTCG colour module lists to sRGB hex, and the CSS text an exporter wrote beside an sRGB object.
 *
 * @spec openspec/specs/custom-token-sets/spec.md
 */
class DtcgColourSerializer {

	/**
	 * A serializer over the given colour space maths.
	 *
	 * @param ColorSpaceConverter $colorSpaces The colour space maths.
	 */
	public function __construct(
		private readonly ColorSpaceConverter $colorSpaces,
	) {
	}//end __construct()

	/**
	 * A colour's original text instead of its object, when the token carries one and it is the same colour.
	 *
	 * The download and the OpenProject plugin keep the text a colour was written in
	 * (`rgb(…)`, `hsl(…)`, a name) beside the object. A tool that changed the colour left the text behind,
	 * so the text counts only while it still names the colour the object holds.
	 *
	 * @param array<string, mixed> $leaf The leaf entry.
	 * @param mixed $value The resolved value.
	 * @param string $type The resolved type.
	 *
	 * @return mixed The text, or the value as it was.
	 *
	 * @spec openspec/specs/colour-notation-kept/spec.md#requirement-a-colour-keeps-the-text-it-was-written-in
	 */
	public function originalText(array $leaf, mixed $value, string $type): mixed {
		if ($type !== 'color' || is_array($value) === false) {
			return $value;
		}

		$text = $leaf['css'] ?? null;
		if (is_string($text) === false || trim($text) === '') {
			return $value;
		}

		return ($this->sameColourText(text: $text, value: $value) ?? $value);
	}//end originalText()

	/**
	 * The CSS text of a colour object, when it is an sRGB notation of the same colour.
	 *
	 * Only the sRGB notations: a lab(), lch() or color() value keeps its conversion to sRGB, which
	 * the upload reports and the contrast checks read.
	 *
	 * @param string $text The CSS text the exporter wrote alongside the object.
	 * @param array<string, mixed> $value The colour object.
	 *
	 * @return string|null The trimmed text, or null when the object is to be kept.
	 */
	private function sameColourText(string $text, array $value): ?string {
		$parsed = (new CssColorParser())->parse(value: $text);
		$object = $this->serializeColorObject(value: $value);
		if ($this->isSrgbNotation(parsed: $parsed) === false || ($object['ok'] ?? false) !== true) {
			return null;
		}

		$rgb = (array)$this->colorSpaces->toSrgb(space: $parsed['space'], components: $parsed['components']);
		if (strtolower($this->colorSpaces->toHex(rgb: $rgb, alpha: $parsed['alpha'])) !== strtolower((string)$object['value'])) {
			return null;
		}

		return trim($text);
	}//end sameColourText()

	/**
	 * Whether a parsed CSS colour is written in an sRGB notation (hex or rgb(), hsl(), hwb()).
	 *
	 * @param array<string, mixed>|null $parsed The parsed colour, or null when it did not parse.
	 *
	 * @return bool True for an sRGB notation.
	 */
	private function isSrgbNotation(?array $parsed): bool {
		return $parsed !== null && in_array($parsed['space'], ['srgb', 'hsl', 'hwb'], true) === true;
	}//end isSrgbNotation()

	/**
	 * Serialize a `color` value: legacy string literal passthrough, or the
	 * v2025.10 object form (sRGB-family color spaces only).
	 *
	 * @param mixed $value The resolved `$value`.
	 *
	 * @return array{ok: bool, value?: string, reason?: string, detail?: string, adapted?: array<string, mixed>} The serialization result.
	 *
	 * @spec openspec/specs/custom-token-sets/spec.md
	 */
	public function serializeColor($value): array {
		if (is_string($value) === true) {
			return ['ok' => true, 'value' => trim($value)];
		}

		if (is_array($value) === false) {
			return ['ok' => false, 'reason' => 'unsupported-value-shape'];
		}

		return $this->serializeColorObject(value: $value);
	}//end serializeColor()

	/**
	 * Serialize the v2025.10 object-form `color` value, in any colour space the DTCG colour
	 * module lists, to sRGB hex: the `hex` member for an sRGB colour, else the converted
	 * components; out of gamut, the document's `hex` or the clipped value, reported `adapted`.
	 *
	 * @param array<string, mixed> $value The object-form `$value`.
	 *
	 * @return array{ok: bool, value?: string, reason?: string, detail?: string, adapted?: array<string, mixed>} The serialization result.
	 *
	 * @spec openspec/specs/custom-token-sets/spec.md
	 */
	private function serializeColorObject(array $value): array {
		$colorSpace = strtolower((string)($value['colorSpace'] ?? ''));
		if ($this->colorSpaces->isKnownSpace(space: $colorSpace) === false) {
			return ['ok' => false, 'reason' => 'unsupported-color-space', 'detail' => ($value['colorSpace'] ?? 'unknown')];
		}

		if ($this->usesHexMember(value: $value, colorSpace: $colorSpace) === true) {
			return $this->hexMemberColour(value: $value);
		}

		return $this->componentsColour(value: $value, colorSpace: $colorSpace);
	}//end serializeColorObject()

	/**
	 * Whether a colour object is serialized from its `hex` member: an sRGB object that has one,
	 * or an object without three components.
	 *
	 * @param array<string, mixed> $value The colour object.
	 * @param string $colorSpace The lower-cased colour space.
	 *
	 * @return bool True when the `hex` member decides the value.
	 */
	private function usesHexMember(array $value, string $colorSpace): bool {
		$components = $value['components'] ?? null;
		if ($colorSpace === 'srgb' && $this->hexMember(value: $value) !== null) {
			return true;
		}

		return is_array($components) === false || count($components) < 3;
	}//end usesHexMember()

	/**
	 * A colour object serialized from its `hex` member and alpha.
	 *
	 * @param array<string, mixed> $value The colour object.
	 *
	 * @return array{ok: bool, value?: string, reason?: string} The serialized value, or the reason it has none.
	 */
	private function hexMemberColour(array $value): array {
		$hex = $this->hexMember(value: $value);
		if ($hex === null) {
			return ['ok' => false, 'reason' => 'unsupported-value-shape'];
		}

		return ['ok' => true, 'value' => $hex . $this->alphaToHex(alpha: $value['alpha'] ?? null)];
	}//end hexMemberColour()

	/**
	 * A colour object serialized from its components, converted to sRGB; out of gamut, the
	 * `hex` member or the clipped channels, reported as adapted.
	 *
	 * @param array<string, mixed> $value The colour object, with at least three components.
	 * @param string $colorSpace The lower-cased colour space.
	 *
	 * @return array{ok: bool, value: string, adapted?: array<string, mixed>} The serialized value.
	 */
	private function componentsColour(array $value, string $colorSpace): array {
		$alphaHex = $this->alphaToHex(alpha: $value['alpha'] ?? null);
		$hex = $this->hexMember(value: $value);
		$rgb = (array)$this->colorSpaces->toSrgb(space: $colorSpace, components: array_values($value['components']));
		if ($this->colorSpaces->isInGamut(rgb: $rgb) === true) {
			return ['ok' => true, 'value' => $this->colorSpaces->toHex(rgb: $rgb) . $alphaHex];
		}

		// Out of the sRGB gamut every consumer reads: the document's own fallback when it
		// has one, else each channel clipped. Either way the report says so.
		$adapted = ['reason' => 'out-of-gamut-clipped', 'original' => $value];
		if ($hex !== null) {
			$adapted['reason'] = 'out-of-gamut-hex-fallback';

			return ['ok' => true, 'value' => $hex . $alphaHex, 'adapted' => $adapted];
		}

		return ['ok' => true, 'value' => $this->colorSpaces->toHex(rgb: $rgb) . $alphaHex, 'adapted' => $adapted];
	}//end componentsColour()

	/**
	 * A colour object's `hex` member as `#rrggbb`, or null when it has none.
	 *
	 * @param array<string, mixed> $value The colour object.
	 *
	 * @return string|null
	 */
	private function hexMember(array $value): ?string {
		$hex = $value['hex'] ?? null;
		if (is_string($hex) === false || preg_match('/^#?([0-9a-fA-F]{6})$/', trim($hex), $match) !== 1) {
			return null;
		}

		return '#' . strtolower($match[1]);
	}//end hexMember()

	/**
	 * Turn a colour object's `alpha` into the fourth hex pair, or '' when opaque.
	 *
	 * @param mixed $alpha The `alpha` member (0–1), absent for an opaque colour.
	 *
	 * @return string Two hex digits, or an empty string for an opaque or absent alpha.
	 */
	private function alphaToHex(mixed $alpha): string {
		if (is_int($alpha) === false && is_float($alpha) === false) {
			return '';
		}

		$alpha = max(0.0, min(1.0, (float)$alpha));
		if ($alpha >= 1.0) {
			return '';
		}

		return str_pad(dechex((int)round($alpha * 255)), 2, '0', STR_PAD_LEFT);
	}//end alphaToHex()
}//end class
