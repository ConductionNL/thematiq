<?php

/**
 * Thematiq colour space converter.
 *
 * Converts a colour in any colour space the DTCG colour module (v2025.10) lists to
 * gamma-encoded sRGB, following the CSS Color 4 sample code
 * (https://www.w3.org/TR/css-color-4/#color-conversion-code): to linear light, to
 * CIE XYZ (D65, with Bradford adaptation from D50), to linear sRGB, to sRGB. Every
 * consumer in this app reads sRGB, so the importer stores the converted value.
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
 * @spec openspec/specs/custom-token-sets/spec.md#requirement-w3c-design-tokens-json-import
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * Colour space maths, CSS Color 4.
 *
 * @spec openspec/specs/custom-token-sets/spec.md#requirement-w3c-design-tokens-json-import
 */
class ColorSpaceConverter {

	/**
	 * The colour spaces of the DTCG colour module, v2025.10.
	 *
	 * @var array<int, string>
	 */
	public const SPACES = [
		'srgb', 'srgb-linear', 'hsl', 'hwb', 'lab', 'lch', 'oklab', 'oklch',
		'display-p3', 'a98-rgb', 'prophoto-rgb', 'rec2020', 'xyz-d65', 'xyz-d50',
	];

	/**
	 * Linear-light RGB to XYZ (D65, or D50 for prophoto-rgb), per RGB space.
	 *
	 * @var array<string, array<int, array<int, float>>>
	 */
	private const TO_XYZ = [
		'srgb' => [
			[0.41239079926595934, 0.357584339383878, 0.1804807884018343],
			[0.21263900587151027, 0.715168678767756, 0.07219231536073371],
			[0.01933081871559182, 0.11919477979462598, 0.9505321522496607],
		],
		'display-p3' => [
			[0.4865709486482162, 0.26566769316909306, 0.1982172852343625],
			[0.2289745640697488, 0.6917385218365064, 0.079286914093745],
			[0.0, 0.04511338185890264, 1.043944368900976],
		],
		'a98-rgb' => [
			[0.5766690429101305, 0.1855582379065463, 0.1882286462349947],
			[0.29734497525053605, 0.6273635662554661, 0.07529145849399788],
			[0.02703136138641234, 0.07068885253582723, 0.9913375368376388],
		],
		'prophoto-rgb' => [
			[0.7977666449006423, 0.13518129740053308, 0.0313477341283922],
			[0.2880748288194013, 0.711835234241873, 0.00008993693872564],
			[0.0, 0.0, 0.8251046025104602],
		],
		'rec2020' => [
			[0.6369580483012914, 0.14461690358620832, 0.1688809751641721],
			[0.2627002120112671, 0.6779980715188708, 0.05930171646986196],
			[0.0, 0.028072693049087428, 1.060985057710791],
		],
	];

	/**
	 * XYZ (D65) to linear sRGB.
	 *
	 * @var array<int, array<int, float>>
	 */
	private const XYZ_TO_LINEAR_SRGB = [
		[3.2409699419045226, -1.537383177570094, -0.4986107602930034],
		[-0.9692436362808796, 1.8759675015077202, 0.04155505740717559],
		[0.05563007969699366, -0.20397695888897652, 1.0569715142428786],
	];

	/**
	 * Bradford chromatic adaptation, D50 to D65.
	 *
	 * @var array<int, array<int, float>>
	 */
	private const D50_TO_D65 = [
		[0.955473421488075, -0.02309845494876471, 0.06325924320057072],
		[-0.0283697093338637, 1.0099953980813041, 0.021041441191917323],
		[0.012314014864481998, -0.020507649298898964, 1.330365926242124],
	];

	/**
	 * OKLab to cone response (LMS, cube-root domain).
	 *
	 * @var array<int, array<int, float>>
	 */
	private const OKLAB_TO_LMS = [
		[1.0, 0.3963377773761749, 0.2158037573099136],
		[1.0, -0.1055613458156586, -0.0638541728258133],
		[1.0, -0.0894841775298119, -1.2914855480194092],
	];

	/**
	 * Cone response (LMS) to XYZ (D65).
	 *
	 * @var array<int, array<int, float>>
	 */
	private const LMS_TO_XYZ = [
		[1.2268798758459243, -0.5578149944602171, 0.2813910456659647],
		[-0.0405757452148008, 1.112286803280317, -0.0717110580655164],
		[-0.0763729366746601, -0.4214933324022432, 1.5869240198367816],
	];

	/**
	 * The D50 reference white.
	 *
	 * @var array<int, float>
	 */
	private const D50_WHITE = [0.9642956764295677, 1.0, 0.8251046025104602];

	/**
	 * How far outside 0..1 a channel may sit and still count as in gamut (rounding noise).
	 *
	 * @var float
	 */
	private const GAMUT_EPSILON = 0.0005;

	/**
	 * Whether a colour space is one the DTCG colour module lists.
	 *
	 * @param string $space The colour space identifier.
	 *
	 * @return bool
	 *
	 * @spec openspec/specs/custom-token-sets/spec.md#requirement-w3c-design-tokens-json-import
	 */
	public function isKnownSpace(string $space): bool {
		return in_array($space, self::SPACES, true);
	}//end isKnownSpace()

	/**
	 * Convert to gamma-encoded sRGB, unclipped.
	 *
	 * @param string            $space      A colour space from {@see self::SPACES}.
	 * @param array<int, float> $components Its three components, in the DTCG ranges.
	 *
	 * @return array<int, float>|null [r, g, b] in 0..1 when in gamut; null for an unknown space.
	 *
	 * @spec openspec/specs/custom-token-sets/spec.md#requirement-w3c-design-tokens-json-import
	 */
	public function toSrgb(string $space, array $components): ?array {
		$c = array_map('floatval', array_slice($components, 0, 3));
		if (count($c) !== 3 || $this->isKnownSpace(space: $space) === false) {
			return null;
		}

		return match ($space) {
			'srgb' => $c,
			'hsl' => $this->hslToSrgb(hue: $c[0], saturation: ($c[1] / 100), lightness: ($c[2] / 100)),
			'hwb' => $this->hwbToSrgb(hue: $c[0], white: ($c[1] / 100), black: ($c[2] / 100)),
			default => array_map(
				fn (float $v): float => $this->gammaSrgb(value: $v),
				$this->multiply(matrix: self::XYZ_TO_LINEAR_SRGB, vector: $this->toXyzD65(space: $space, c: $c))
			),
		};
	}//end toSrgb()

	/**
	 * Whether an sRGB triple lies inside the gamut.
	 *
	 * @param array<int, float> $rgb [r, g, b].
	 *
	 * @return bool
	 *
	 * @spec openspec/specs/custom-token-sets/spec.md#requirement-w3c-design-tokens-json-import
	 */
	public function isInGamut(array $rgb): bool {
		foreach ($rgb as $channel) {
			if ($channel < -self::GAMUT_EPSILON || $channel > (1 + self::GAMUT_EPSILON)) {
				return false;
			}
		}

		return true;
	}//end isInGamut()

	/**
	 * An sRGB triple as hex, each channel clipped to 0..1, with alpha as a fourth pair below 1.
	 *
	 * @param array<int, float> $rgb   [r, g, b].
	 * @param float|null        $alpha The alpha, 0..1, or null for opaque.
	 *
	 * @return string `#rrggbb` or `#rrggbbaa`.
	 *
	 * @spec openspec/specs/custom-token-sets/spec.md#requirement-w3c-design-tokens-json-import
	 */
	public function toHex(array $rgb, ?float $alpha = null): string {
		$hex = '#';
		foreach (array_slice(array_values($rgb), 0, 3) as $channel) {
			$hex .= sprintf('%02x', (int)round(max(0.0, min(1.0, $channel)) * 255));
		}

		if ($alpha !== null && $alpha < 1.0) {
			$hex .= sprintf('%02x', (int)round(max(0.0, $alpha) * 255));
		}

		return $hex;
	}//end toHex()

	/**
	 * A colour space other than srgb, hsl and hwb to XYZ (D65).
	 *
	 * @param string            $space The colour space.
	 * @param array<int, float> $c     The components.
	 *
	 * @return array<int, float> XYZ.
	 */
	private function toXyzD65(string $space, array $c): array {
		return match ($space) {
			'srgb-linear' => $this->multiply(matrix: self::TO_XYZ['srgb'], vector: $c),
			'display-p3' => $this->multiply(matrix: self::TO_XYZ['display-p3'], vector: array_map(fn (float $v): float => $this->linearSrgb(value: $v), $c)),
			'a98-rgb' => $this->multiply(
				matrix: self::TO_XYZ['a98-rgb'],
				vector: array_map(static fn (float $v): float => (($v <=> 0) * (abs($v) ** (563 / 256))), $c)
			),
			'rec2020' => $this->multiply(matrix: self::TO_XYZ['rec2020'], vector: array_map(fn (float $v): float => $this->linearRec2020(value: $v), $c)),
			'prophoto-rgb' => $this->d50ToD65(
				xyz: $this->multiply(
					matrix: self::TO_XYZ['prophoto-rgb'],
					vector: array_map(fn (float $v): float => $this->linearProphoto(value: $v), $c)
				)
			),
			'xyz-d65' => $c,
			'xyz-d50' => $this->d50ToD65(xyz: $c),
			'lab' => $this->d50ToD65(xyz: $this->labToXyzD50(lab: $c)),
			'lch' => $this->d50ToD65(xyz: $this->labToXyzD50(lab: $this->polarToRectangular(c: $c))),
			'oklab' => $this->oklabToXyz(lab: $c),
			default => $this->oklabToXyz(lab: $this->polarToRectangular(c: $c)),
		};
	}//end toXyzD65()

	/**
	 * Lightness, chroma, hue to lightness, a, b.
	 *
	 * @param array<int, float> $c [L, C, h in degrees].
	 *
	 * @return array<int, float> [L, a, b].
	 */
	private function polarToRectangular(array $c): array {
		$hue = deg2rad($c[2]);

		return [$c[0], ($c[1] * cos($hue)), ($c[1] * sin($hue))];
	}//end polarToRectangular()

	/**
	 * CIE Lab (D50) to XYZ (D50).
	 *
	 * @param array<int, float> $lab [L 0..100, a, b].
	 *
	 * @return array<int, float> XYZ.
	 */
	private function labToXyzD50(array $lab): array {
		$kappa   = (24389 / 27);
		$epsilon = (216 / 24389);
		$yRoot   = (($lab[0] + 16) / 116);
		$xRoot   = (($lab[1] / 500) + $yRoot);
		$zRoot   = ($yRoot - ($lab[2] / 200));
		$white   = self::D50_WHITE;

		return [
			($this->labInverse(value: $xRoot, kappa: $kappa, epsilon: $epsilon) * $white[0]),
			($this->labLightness(lightness: (float)$lab[0], kappa: $kappa, epsilon: $epsilon) * $white[1]),
			($this->labInverse(value: $zRoot, kappa: $kappa, epsilon: $epsilon) * $white[2]),
		];
	}//end labToXyzD50()

	/**
	 * The inverse Lab companding of the x or z channel.
	 *
	 * @param float $value   f(x) or f(z).
	 * @param float $kappa   CIE kappa.
	 * @param float $epsilon CIE epsilon.
	 *
	 * @return float
	 */
	private function labInverse(float $value, float $kappa, float $epsilon): float {
		if (($value ** 3) > $epsilon) {
			return ($value ** 3);
		}

		return (((116 * $value) - 16) / $kappa);
	}//end labInverse()

	/**
	 * The relative luminance Y of a Lab lightness.
	 *
	 * @param float $lightness L, 0..100.
	 * @param float $kappa     CIE kappa.
	 * @param float $epsilon   CIE epsilon.
	 *
	 * @return float
	 */
	private function labLightness(float $lightness, float $kappa, float $epsilon): float {
		if ($lightness > ($kappa * $epsilon)) {
			return ((($lightness + 16) / 116) ** 3);
		}

		return ($lightness / $kappa);
	}//end labLightness()

	/**
	 * OKLab to XYZ (D65).
	 *
	 * @param array<int, float> $lab [L 0..1, a, b].
	 *
	 * @return array<int, float> XYZ.
	 */
	private function oklabToXyz(array $lab): array {
		$lms = array_map(static fn (float $v): float => ($v ** 3), $this->multiply(matrix: self::OKLAB_TO_LMS, vector: $lab));

		return $this->multiply(matrix: self::LMS_TO_XYZ, vector: $lms);
	}//end oklabToXyz()

	/**
	 * Bradford D50 to D65.
	 *
	 * @param array<int, float> $xyz XYZ (D50).
	 *
	 * @return array<int, float> XYZ (D65).
	 */
	private function d50ToD65(array $xyz): array {
		return $this->multiply(matrix: self::D50_TO_D65, vector: $xyz);
	}//end d50ToD65()

	/**
	 * HSL to sRGB (CSS Color 4 hslToRgb).
	 *
	 * @param float $hue        Degrees.
	 * @param float $saturation 0..1.
	 * @param float $lightness  0..1.
	 *
	 * @return array<int, float> [r, g, b].
	 */
	private function hslToSrgb(float $hue, float $saturation, float $lightness): array {
		$hue = fmod(fmod($hue, 360) + 360, 360);
		$a   = ($saturation * min($lightness, (1 - $lightness)));
		$f   = static function (int $n) use ($hue, $a, $lightness): float {
			$k = fmod(($n + ($hue / 30)), 12);

			return ($lightness - ($a * max(-1, min(($k - 3), (9 - $k), 1))));
		};

		return [$f(0), $f(8), $f(4)];
	}//end hslToSrgb()

	/**
	 * HWB to sRGB (CSS Color 4 hwbToRgb).
	 *
	 * @param float $hue   Degrees.
	 * @param float $white 0..1.
	 * @param float $black 0..1.
	 *
	 * @return array<int, float> [r, g, b].
	 */
	private function hwbToSrgb(float $hue, float $white, float $black): array {
		if (($white + $black) >= 1) {
			$grey = ($white / ($white + $black));

			return [$grey, $grey, $grey];
		}

		return array_map(
			static fn (float $channel): float => (($channel * (1 - $white - $black)) + $white),
			$this->hslToSrgb(hue: $hue, saturation: 1.0, lightness: 0.5)
		);
	}//end hwbToSrgb()

	/**
	 * The sRGB (and display-p3) transfer function, to linear light.
	 *
	 * @param float $value A gamma-encoded channel.
	 *
	 * @return float
	 */
	private function linearSrgb(float $value): float {
		$abs = abs($value);
		if ($abs <= 0.04045) {
			return ($value / 12.92);
		}

		return (($value <=> 0) * ((($abs + 0.055) / 1.055) ** 2.4));
	}//end linearSrgb()

	/**
	 * The inverse sRGB transfer function, from linear light.
	 *
	 * @param float $value A linear channel.
	 *
	 * @return float
	 */
	private function gammaSrgb(float $value): float {
		$abs = abs($value);
		if ($abs <= 0.0031308) {
			return (12.92 * $value);
		}

		return (($value <=> 0) * ((1.055 * ($abs ** (1 / 2.4))) - 0.055));
	}//end gammaSrgb()

	/**
	 * The rec2020 transfer function, to linear light.
	 *
	 * @param float $value A gamma-encoded channel.
	 *
	 * @return float
	 */
	private function linearRec2020(float $value): float {
		$alpha = 1.09929682680944;
		$beta  = 0.018053968510807;
		$abs   = abs($value);
		if ($abs < ($beta * 4.5)) {
			return ($value / 4.5);
		}

		return (($value <=> 0) * ((($abs + $alpha - 1) / $alpha) ** (1 / 0.45)));
	}//end linearRec2020()

	/**
	 * The prophoto-rgb transfer function, to linear light.
	 *
	 * @param float $value A gamma-encoded channel.
	 *
	 * @return float
	 */
	private function linearProphoto(float $value): float {
		$abs = abs($value);
		if ($abs <= (16 / 512)) {
			return ($value / 16);
		}

		return (($value <=> 0) * ($abs ** 1.8));
	}//end linearProphoto()

	/**
	 * A 3x3 matrix times a vector.
	 *
	 * @param array<int, array<int, float>> $matrix The matrix.
	 * @param array<int, float>             $vector The vector.
	 *
	 * @return array<int, float>
	 */
	private function multiply(array $matrix, array $vector): array {
		$out = [];
		foreach ($matrix as $row) {
			$out[] = (($row[0] * $vector[0]) + ($row[1] * $vector[1]) + ($row[2] * $vector[2]));
		}

		return $out;
	}//end multiply()
}//end class
