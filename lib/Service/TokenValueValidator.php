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

	/**
	 * The tokens a save would drop or refuse, with the reason, which names the type.
	 *
	 * @param array<string, mixed> $tokens     Token name => light value.
	 * @param array<string, mixed> $darkTokens Token name => the administrator's own dark value.
	 *
	 * @return array<string, string> Token name => reason; empty when everything passes.
	 *
	 * @SuppressWarnings(PHPMD.StaticAccess) - TokenRegistry uses static methods by design.
	 *
	 * @spec openspec/specs/token-editor-ui/spec.md#requirement-the-server-checks-each-value-against-its-token-type
	 */
	public function findRejected(array $tokens, array $darkTokens = []): array {
		$registry = array_merge(TokenRegistry::getInternalTokens(), TokenRegistry::getTokens());
		$rejected = [];
		foreach ($tokens as $name => $value) {
			$name = (string)$name;
			if (TokenRegistry::isEditable(tokenName: $name) === false) {
				$rejected[$name] = 'not an editable token';
				continue;
			}

			$reason = $this->valueProblem(value: $value, type: (string)($registry[$name]['type'] ?? 'text'));
			if ($reason !== null) {
				$rejected[$name] = $reason;
			}
		}

		foreach ($darkTokens as $name => $value) {
			$name = (string)$name;
			if (isset($tokens[$name]) === false || $this->hasDarkValueFor(name: $name) === false) {
				$rejected[$name] = 'no dark value for this token';
				continue;
			}

			$reason = $this->valueProblem(value: $value, type: 'color');
			if ($reason !== null) {
				$rejected[$name] = 'dark value: ' . $reason;
			}
		}

		return $rejected;
	}//end findRejected()

	/**
	 * Why a value is refused for its type, or null when it passes.
	 *
	 * @param mixed  $value The value.
	 * @param string $type  The token type.
	 *
	 * @return string|null The reason, naming the type.
	 */
	private function valueProblem(mixed $value, string $type): ?string {
		if (is_string($value) === false || preg_match('/[{};]|\\/\\*|\\*\\//', $value) === 1) {
			return 'not an allowed value';
		}

		if ($this->isValid(type: $type, value: $value) === false) {
			return 'not a valid ' . $type . ' value';
		}

		return null;
	}//end valueProblem()

	/**
	 * Whether a token gets a dark copy: a colour of the brand layer (Nextcloud's own variables).
	 *
	 * @param array<string, mixed> $meta The registry entry.
	 *
	 * @return boolean True when it does.
	 *
	 * @spec openspec/specs/token-editor-ui/spec.md#requirement-each-colour-token-has-an-optional-dark-value
	 */
	public function hasDarkValue(array $meta): bool {
		return ($meta['type'] ?? '') === 'color' && ($meta['group'] ?? '') === 'brand';
	}//end hasDarkValue()

	/**
	 * Whether a token takes the administrator's own dark value: a brand-layer
	 * colour, including the settable theme colours, or an internal colour.
	 *
	 * @param string $name The token name.
	 *
	 * @return boolean True when it does.
	 *
	 * @SuppressWarnings(PHPMD.StaticAccess) - TokenRegistry uses static methods by design.
	 *
	 * @spec openspec/changes/token-editor-at-scale/specs/token-editor-ui/spec.md
	 */
	public function hasDarkValueFor(string $name): bool {
		$internal = TokenRegistry::getInternalTokens();
		if (isset($internal[$name]) === true) {
			return $internal[$name]['type'] === 'color';
		}

		return $this->hasDarkValue(meta: (TokenRegistry::getTokens()[$name] ?? []));
	}//end hasDarkValueFor()
}//end class
