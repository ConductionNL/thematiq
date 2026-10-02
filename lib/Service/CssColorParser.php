<?php

/**
 * Thematiq CSS colour parser.
 *
 * Reads a CSS colour value into a colour space, three components and an alpha, the
 * shape a DTCG colour object carries: hex, rgb() and named colours as `srgb` in 0..1,
 * hsl() converted to `srgb`, and oklch(), oklab(), lab(), lch(), hwb() and
 * color(<space> ...) in their own colour space with the DTCG component ranges.
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
 * CSS colour text to a colour space and components.
 *
 * @spec openspec/specs/token-set-dtcg-export/spec.md#requirement-each-value-gets-a-dtcg-type-by-its-shape
 */
class CssColorParser {

	/**
	 * The CSS named colours (CSS Color 4), lower case, as hex.
	 *
	 * @var array<string, string>
	 */
	private const NAMED = [
		'aliceblue' => '#f0f8ff', 'antiquewhite' => '#faebd7', 'aqua' => '#00ffff', 'aquamarine' => '#7fffd4',
		'azure' => '#f0ffff', 'beige' => '#f5f5dc', 'bisque' => '#ffe4c4', 'black' => '#000000',
		'blanchedalmond' => '#ffebcd', 'blue' => '#0000ff', 'blueviolet' => '#8a2be2', 'brown' => '#a52a2a',
		'burlywood' => '#deb887', 'cadetblue' => '#5f9ea0', 'chartreuse' => '#7fff00', 'chocolate' => '#d2691e',
		'coral' => '#ff7f50', 'cornflowerblue' => '#6495ed', 'cornsilk' => '#fff8dc', 'crimson' => '#dc143c',
		'cyan' => '#00ffff', 'darkblue' => '#00008b', 'darkcyan' => '#008b8b', 'darkgoldenrod' => '#b8860b',
		'darkgray' => '#a9a9a9', 'darkgreen' => '#006400', 'darkgrey' => '#a9a9a9', 'darkkhaki' => '#bdb76b',
		'darkmagenta' => '#8b008b', 'darkolivegreen' => '#556b2f', 'darkorange' => '#ff8c00',
		'darkorchid' => '#9932cc', 'darkred' => '#8b0000', 'darksalmon' => '#e9967a', 'darkseagreen' => '#8fbc8f',
		'darkslateblue' => '#483d8b', 'darkslategray' => '#2f4f4f', 'darkslategrey' => '#2f4f4f',
		'darkturquoise' => '#00ced1', 'darkviolet' => '#9400d3', 'deeppink' => '#ff1493', 'deepskyblue' => '#00bfff',
		'dimgray' => '#696969', 'dimgrey' => '#696969', 'dodgerblue' => '#1e90ff', 'firebrick' => '#b22222',
		'floralwhite' => '#fffaf0', 'forestgreen' => '#228b22', 'fuchsia' => '#ff00ff', 'gainsboro' => '#dcdcdc',
		'ghostwhite' => '#f8f8ff', 'gold' => '#ffd700', 'goldenrod' => '#daa520', 'gray' => '#808080',
		'green' => '#008000', 'greenyellow' => '#adff2f', 'grey' => '#808080', 'honeydew' => '#f0fff0',
		'hotpink' => '#ff69b4', 'indianred' => '#cd5c5c', 'indigo' => '#4b0082', 'ivory' => '#fffff0',
		'khaki' => '#f0e68c', 'lavender' => '#e6e6fa', 'lavenderblush' => '#fff0f5', 'lawngreen' => '#7cfc00',
		'lemonchiffon' => '#fffacd', 'lightblue' => '#add8e6', 'lightcoral' => '#f08080', 'lightcyan' => '#e0ffff',
		'lightgoldenrodyellow' => '#fafad2', 'lightgray' => '#d3d3d3', 'lightgreen' => '#90ee90',
		'lightgrey' => '#d3d3d3', 'lightpink' => '#ffb6c1', 'lightsalmon' => '#ffa07a', 'lightseagreen' => '#20b2aa',
		'lightskyblue' => '#87cefa', 'lightslategray' => '#778899', 'lightslategrey' => '#778899',
		'lightsteelblue' => '#b0c4de', 'lightyellow' => '#ffffe0', 'lime' => '#00ff00', 'limegreen' => '#32cd32',
		'linen' => '#faf0e6', 'magenta' => '#ff00ff', 'maroon' => '#800000', 'mediumaquamarine' => '#66cdaa',
		'mediumblue' => '#0000cd', 'mediumorchid' => '#ba55d3', 'mediumpurple' => '#9370db',
		'mediumseagreen' => '#3cb371', 'mediumslateblue' => '#7b68ee', 'mediumspringgreen' => '#00fa9a',
		'mediumturquoise' => '#48d1cc', 'mediumvioletred' => '#c71585', 'midnightblue' => '#191970',
		'mintcream' => '#f5fffa', 'mistyrose' => '#ffe4e1', 'moccasin' => '#ffe4b5', 'navajowhite' => '#ffdead',
		'navy' => '#000080', 'oldlace' => '#fdf5e6', 'olive' => '#808000', 'olivedrab' => '#6b8e23',
		'orange' => '#ffa500', 'orangered' => '#ff4500', 'orchid' => '#da70d6', 'palegoldenrod' => '#eee8aa',
		'palegreen' => '#98fb98', 'paleturquoise' => '#afeeee', 'palevioletred' => '#db7093',
		'papayawhip' => '#ffefd5', 'peachpuff' => '#ffdab9', 'peru' => '#cd853f', 'pink' => '#ffc0cb',
		'plum' => '#dda0dd', 'powderblue' => '#b0e0e6', 'purple' => '#800080', 'rebeccapurple' => '#663399',
		'red' => '#ff0000', 'rosybrown' => '#bc8f8f', 'royalblue' => '#4169e1', 'saddlebrown' => '#8b4513',
		'salmon' => '#fa8072', 'sandybrown' => '#f4a460', 'seagreen' => '#2e8b57', 'seashell' => '#fff5ee',
		'sienna' => '#a0522d', 'silver' => '#c0c0c0', 'skyblue' => '#87ceeb', 'slateblue' => '#6a5acd',
		'slategray' => '#708090', 'slategrey' => '#708090', 'snow' => '#fffafa', 'springgreen' => '#00ff7f',
		'steelblue' => '#4682b4', 'tan' => '#d2b48c', 'teal' => '#008080', 'thistle' => '#d8bfd8',
		'tomato' => '#ff6347', 'turquoise' => '#40e0d0', 'violet' => '#ee82ee', 'wheat' => '#f5deb3',
		'white' => '#ffffff', 'whitesmoke' => '#f5f5f5', 'yellow' => '#ffff00', 'yellowgreen' => '#9acd32',
	];

	/**
	 * How a percentage reads per function and component: the value 100% stands for.
	 *
	 * @var array<string, array<int, float>>
	 */
	private const PERCENT_SCALE = [
		'oklch' => [1.0, 0.4, 1.0],
		'oklab' => [1.0, 0.4, 0.4],
		'lab' => [100.0, 125.0, 125.0],
		'lch' => [100.0, 150.0, 1.0],
		'hwb' => [1.0, 100.0, 100.0],
		'hsl' => [1.0, 100.0, 100.0],
	];

	/**
	 * Constructor.
	 *
	 * @param ColorSpaceConverter $converter For hsl(), written as sRGB.
	 */
	public function __construct(
		private readonly ColorSpaceConverter $converter = new ColorSpaceConverter(),
	) {
	}//end __construct()

	/**
	 * Parse a CSS colour.
	 *
	 * @param string $value The CSS value.
	 *
	 * @return array{space: string, components: array<int, float>, alpha: float|null}|null Null when it is not a colour this reads.
	 *
	 * @spec openspec/specs/token-set-dtcg-export/spec.md#requirement-each-value-gets-a-dtcg-type-by-its-shape
	 */
	public function parse(string $value): ?array {
		$value = strtolower(trim($value));
		if ($value === 'transparent') {
			return ['space' => 'srgb', 'components' => [0.0, 0.0, 0.0], 'alpha' => 0.0];
		}

		if (isset(self::NAMED[$value]) === true) {
			$value = self::NAMED[$value];
		}

		if (preg_match('/^#([0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/', $value, $match) === 1) {
			return $this->fromHex(hex: $match[1]);
		}

		if (preg_match('/^([a-z0-9-]+)\((.*)\)$/', $value, $match) !== 1) {
			return null;
		}

		return $this->fromFunction(name: $match[1], arguments: $match[2]);
	}//end parse()

	/**
	 * Hex digits to sRGB.
	 *
	 * @param string $hex 3, 4, 6 or 8 digits.
	 *
	 * @return array{space: string, components: array<int, float>, alpha: float|null}
	 */
	private function fromHex(string $hex): array {
		if (strlen($hex) <= 4) {
			$hex = implode('', array_map(static fn (string $digit): string => $digit . $digit, str_split($hex)));
		}

		$pairs = array_map('hexdec', str_split($hex, 2));
		$alpha = null;
		if (count($pairs) === 4) {
			$alpha = ((int)$pairs[3] / 255);
		}

		return ['space' => 'srgb', 'components' => array_map(static fn ($v): float => ((int)$v / 255), array_slice($pairs, 0, 3)), 'alpha' => $alpha];
	}//end fromHex()

	/**
	 * A colour function to its colour space.
	 *
	 * @param string $name      The function name.
	 * @param string $arguments What is between the parentheses.
	 *
	 * @return array{space: string, components: array<int, float>, alpha: float|null}|null
	 */
	private function fromFunction(string $name, string $arguments): ?array {
		[$parts, $alpha] = $this->split(arguments: $arguments);
		$space = $name;
		if ($name === 'color') {
			$space = (string)array_shift($parts);
		}

		$space = rtrim($space, 'a');
		if ($space === 'rgb') {
			$space = 'srgb';
		}

		if (count($parts) !== 3 || ($space !== 'srgb' && $this->converter->isKnownSpace(space: $space) === false)) {
			return null;
		}

		$components = [];
		foreach ($parts as $index => $part) {
			$number = $this->number(part: $part, space: $space, index: $index, isRgbFunction: $name !== 'color');
			if ($number === null) {
				return null;
			}

			$components[] = $number;
		}

		if ($space === 'hsl') {
			$space      = 'srgb';
			$components = (array)$this->converter->toSrgb(space: 'hsl', components: $components);
		}

		return ['space' => $space, 'components' => $components, 'alpha' => $alpha];
	}//end fromFunction()

	/**
	 * The component words and the alpha, from comma or space syntax.
	 *
	 * @param string $arguments What is between the parentheses.
	 *
	 * @return array{0: array<int, string>, 1: float|null}
	 */
	private function split(string $arguments): array {
		$alpha = null;
		$main  = $arguments;
		if (str_contains($arguments, '/') === true) {
			[$main, $alphaText] = array_map('trim', explode('/', $arguments, 2));
			$alpha = $this->alpha(text: $alphaText);
		}

		$parts = (array)preg_split('/[\s,]+/', trim($main), -1, PREG_SPLIT_NO_EMPTY);
		if ($alpha === null && count($parts) === 4 && str_contains($main, ',') === true) {
			$alpha = $this->alpha(text: (string)array_pop($parts));
		}

		return [$parts, $alpha];
	}//end split()

	/**
	 * An alpha word, number or percentage, to 0..1.
	 *
	 * @param string $text The word.
	 *
	 * @return float
	 */
	private function alpha(string $text): float {
		if (str_ends_with($text, '%') === true) {
			return max(0.0, min(1.0, ((float)$text / 100)));
		}

		return max(0.0, min(1.0, (float)$text));
	}//end alpha()

	/**
	 * One component word to its DTCG number.
	 *
	 * @param string $part          The word.
	 * @param string $space         The colour space.
	 * @param int    $index         Its position.
	 * @param bool   $isRgbFunction Whether it came from rgb() rather than color(srgb ...), which reads 0..255.
	 *
	 * @return float|null Null for a word that is not a number.
	 */
	private function number(string $part, string $space, int $index, bool $isRgbFunction): ?float {
		if ($part === 'none') {
			return 0.0;
		}

		$part = preg_replace('/deg$/', '', $part);
		if (preg_match('/^-?(\d+\.?\d*|\.\d+)(e-?\d+)?%?$/', (string)$part) !== 1) {
			return null;
		}

		$number = (float)$part;
		if (str_ends_with((string)$part, '%') === true) {
			return ($number / 100 * (self::PERCENT_SCALE[$space][$index] ?? 1.0));
		}

		if ($space === 'srgb' && $isRgbFunction === true) {
			return ($number / 255);
		}

		return $number;
	}//end number()
}//end class
