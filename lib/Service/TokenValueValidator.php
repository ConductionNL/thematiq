<?php

/**
 * Thematiq Token Value Validator.
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
 * @spec openspec/specs/token-editor-ui/spec.md#requirement-the-server-checks-each-value-against-its-token-type
 */

declare(strict_types=1);
namespace OCA\Thematiq\Service;

/**
 * One value grammar per token type: `color`, `duration`, `easing` and `text`.
 * `isValidTokenValue()` in `js/lib/tokenTransforms.js` mirrors it; both run the cases in
 * `tests/Unit/fixtures/token-value-grammar.json`.
 *
 * @spec openspec/specs/token-editor-ui/spec.md#requirement-the-server-checks-each-value-against-its-token-type
 */
class TokenValueValidator {

	/**
	 * The CSS named colours, and `transparent`.
	 *
	 * @var array<int, string>
	 */
	private const NAMED_COLOURS = [
		'aliceblue', 'antiquewhite', 'aqua', 'aquamarine', 'azure', 'beige', 'bisque', 'black', 'blanchedalmond', 'blue', 'blueviolet', 'brown',
		'burlywood', 'cadetblue', 'chartreuse', 'chocolate', 'coral', 'cornflowerblue', 'cornsilk', 'crimson', 'cyan', 'darkblue', 'darkcyan',
		'darkgoldenrod', 'darkgray', 'darkgreen', 'darkgrey', 'darkkhaki', 'darkmagenta', 'darkolivegreen', 'darkorange', 'darkorchid', 'darkred',
		'darksalmon', 'darkseagreen', 'darkslateblue', 'darkslategray', 'darkslategrey', 'darkturquoise', 'darkviolet', 'deeppink',
		'deepskyblue', 'dimgray', 'dimgrey', 'dodgerblue', 'firebrick', 'floralwhite', 'forestgreen', 'fuchsia', 'gainsboro', 'ghostwhite', 'gold',
		'goldenrod', 'gray', 'green', 'greenyellow', 'grey', 'honeydew', 'hotpink', 'indianred', 'indigo', 'ivory', 'khaki', 'lavender', 'lavenderblush',
		'lawngreen', 'lemonchiffon', 'lightblue', 'lightcoral', 'lightcyan', 'lightgoldenrodyellow', 'lightgray', 'lightgreen', 'lightgrey',
		'lightpink', 'lightsalmon', 'lightseagreen', 'lightskyblue', 'lightslategray', 'lightslategrey', 'lightsteelblue', 'lightyellow',
		'lime', 'limegreen', 'linen', 'magenta', 'maroon', 'mediumaquamarine', 'mediumblue', 'mediumorchid', 'mediumpurple', 'mediumseagreen',
		'mediumslateblue', 'mediumspringgreen', 'mediumturquoise', 'mediumvioletred', 'midnightblue', 'mintcream', 'mistyrose', 'moccasin',
		'navajowhite', 'navy', 'oldlace', 'olive', 'olivedrab', 'orange', 'orangered', 'orchid', 'palegoldenrod', 'palegreen', 'paleturquoise',
		'palevioletred', 'papayawhip', 'peachpuff', 'peru', 'pink', 'plum', 'powderblue', 'purple', 'rebeccapurple', 'red', 'rosybrown', 'royalblue',
		'saddlebrown', 'salmon', 'sandybrown', 'seagreen', 'sienna', 'silver', 'skyblue', 'slateblue', 'slategray', 'slategrey', 'snow', 'springgreen',
		'steelblue', 'tan', 'teal', 'thistle', 'tomato', 'turquoise', 'violet', 'wheat', 'white', 'whitesmoke', 'yellow', 'yellowgreen', 'transparent',
	];

	/**
	 * The easing keywords.
	 *
	 * @var array<int, string>
	 */
	private const EASING_KEYWORDS = ['linear', 'ease', 'ease-in', 'ease-out', 'ease-in-out'];

	/**
	 * Whether a value is valid for its token type. An unknown type is treated as `text`.
	 *
	 * @param string $type  The token type.
	 * @param string $value The value.
	 *
	 * @return boolean True when it passes.
	 *
	 * @spec openspec/specs/token-editor-ui/spec.md#requirement-the-server-checks-each-value-against-its-token-type
	 */
	public function isValid(string $type, string $value): bool {
		$value = trim($value);

		return match ($type) {
			'color' => $this->isColour(value: strtolower($value)),
			'duration' => $this->isDuration(value: $value),
			'easing' => $this->isEasing(value: $value),
			'rgb' => $this->isTriplet(value: $value),
			default => $value !== '',
		};
	}//end isValid()

	/**
	 * Hex (3, 4, 6 or 8 digits), rgb()/rgba() with three or four parts, hsl()/hsla(), or a named colour.
	 *
	 * @param string $value The lower-cased value.
	 *
	 * @return boolean True for a colour.
	 */
	private function isColour(string $value): bool {
		if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{4}|[0-9a-f]{6}|[0-9a-f]{8})$/', $value) === 1) {
			return true;
		}

		$number = '\\s*-?[0-9.]+%?\\s*';
		$functional = '/^(rgb|rgba|hsl|hsla)\\((' . $number . ',){2}' . $number . '(,' . $number . ')?\\)$/';
		if (preg_match($functional, $value) === 1) {
			return true;
		}

		return in_array($value, self::NAMED_COLOURS, true);
	}//end isColour()

	/**
	 * 0 to 5000 ms, or 0 to 5 s.
	 *
	 * @param string $value The value.
	 *
	 * @return boolean True for a duration in range.
	 */
	private function isDuration(string $value): bool {
		if (preg_match('/^([0-9]+(?:\\.[0-9]+)?)(ms|s)$/', $value, $match) !== 1) {
			return false;
		}

		$max = 5000.0;
		if ($match[2] === 's') {
			$max = 5.0;
		}

		return (float)$match[1] <= $max;
	}//end isDuration()

	/**
	 * A keyword, or cubic-bezier() with four numbers and both x values in 0..1.
	 *
	 * @param string $value The value.
	 *
	 * @return boolean True for an easing.
	 */
	private function isEasing(string $value): bool {
		if (in_array($value, self::EASING_KEYWORDS, true) === true) {
			return true;
		}

		$number = '\\s*(-?[0-9]*\\.?[0-9]+)\\s*';
		if (preg_match('/^cubic-bezier\\(' . $number . ',' . $number . ',' . $number . ',' . $number . '\\)$/', $value, $match) !== 1) {
			return false;
		}

		return (float)$match[1] >= 0 && (float)$match[1] <= 1 && (float)$match[3] >= 0 && (float)$match[3] <= 1;
	}//end isEasing()

	/**
	 * A bare `r, g, b` triplet, each 0 to 255 (the note cards' Nextcloud 32 fills).
	 *
	 * @param string $value The value.
	 *
	 * @return boolean True for a triplet.
	 */
	private function isTriplet(string $value): bool {
		if (preg_match('/^([0-9]{1,3})\\s*,\\s*([0-9]{1,3})\\s*,\\s*([0-9]{1,3})$/', $value, $match) !== 1) {
			return false;
		}

		return max((int)$match[1], (int)$match[2], (int)$match[3]) <= 255;
	}//end isTriplet()
}//end class
