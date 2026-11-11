<?php

/**
 * Thematiq DTCG value checker.
 *
 * Applies the editor's value rules to the tokens of a DTCG source, so a
 * theme-as-code package cannot serve a value the editor and the overrides API
 * would refuse.
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
 * @spec openspec/specs/theme-as-code/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * Finds the colour tokens of a DTCG document whose value is not a colour.
 *
 * @spec openspec/specs/theme-as-code/spec.md
 */
class DtcgValueChecker {

	/**
	 * Constructor.
	 *
	 * @param TokenValueValidator $values The value rules the editor and the overrides API apply (#809).
	 */
	public function __construct(
		private readonly TokenValueValidator $values,
	) {
	}//end __construct()

	/**
	 * The colour tokens of a DTCG document whose value is not a colour.
	 *
	 * A token's `$type` is its own or the nearest group's. Only a string value
	 * is checked: an alias (`{path.to.token}`) is checked where it points, and
	 * the colour object form is the converter's to read. Tokens Studio's
	 * `type` and `value` without the dollar are read the same way.
	 *
	 * @param array<array-key, mixed> $document The decoded DTCG document.
	 *
	 * @return array<string, string> Dotted token path => the refused value.
	 *
	 * @spec openspec/specs/theme-as-code/spec.md
	 */
	public function findInvalidColours(array $document): array {
		return $this->collect(node: $document, parent: new DtcgPlace(type: null, path: ''));
	}//end findInvalidColours()

	/**
	 * Walk one DTCG node.
	 *
	 * @param array<array-key, mixed> $node The group or token.
	 * @param DtcgPlace $parent The place of the enclosing group, with the type it carries.
	 *
	 * @return array<string, string> The node's refused colours, path => value, in document order.
	 */
	private function collect(array $node, DtcgPlace $parent): array {
		$place = $this->typedPlace(node: $node, parent: $parent);
		$token = $this->tokenValue(node: $node);
		if ($token !== null) {
			return $this->refusedAt(value: $token['value'], place: $place);
		}

		$invalid = [];
		foreach ($node as $key => $child) {
			if (is_array($child) === false || str_starts_with((string)$key, '$') === true) {
				continue;
			}

			$invalid = array_replace($invalid, $this->collect(node: $child, parent: $this->childPlace(place: $place, key: (string)$key)));
		}

		return $invalid;
	}//end collect()

	/**
	 * The node's place with its own type, when it declares one.
	 *
	 * @param array<array-key, mixed> $node The group or token.
	 * @param DtcgPlace $parent The place as the enclosing group leaves it.
	 *
	 * @return DtcgPlace The place, typed by the node or as inherited.
	 */
	private function typedPlace(array $node, DtcgPlace $parent): DtcgPlace {
		$ownType = ($node['$type'] ?? ($node['type'] ?? null));
		if (is_string($ownType) === false) {
			return $parent;
		}

		return new DtcgPlace(type: $ownType, path: $parent->path);
	}//end typedPlace()

	/**
	 * The place of a child node.
	 *
	 * @param DtcgPlace $place The group's place.
	 * @param string $key The child's key.
	 *
	 * @return DtcgPlace The child's place, inheriting the group's type.
	 */
	private function childPlace(DtcgPlace $place, string $key): DtcgPlace {
		if ($place->path === '') {
			return new DtcgPlace(type: $place->type, path: $key);
		}

		return new DtcgPlace(type: $place->type, path: $place->path . '.' . $key);
	}//end childPlace()

	/**
	 * The token's value when it is a refused colour.
	 *
	 * @param mixed $value The token value.
	 * @param DtcgPlace $place The token's place.
	 *
	 * @return array<string, string> Path => value, or empty.
	 */
	private function refusedAt(mixed $value, DtcgPlace $place): array {
		if ($place->type === 'color' && $this->isRefusedColour(value: $value) === true) {
			return [$place->path => (string)$value];
		}

		return [];
	}//end refusedAt()

	/**
	 * The value of a DTCG token node, or null when the node is a group.
	 *
	 * @param array<array-key, mixed> $node The node.
	 *
	 * @return array{value: mixed}|null The value, wrapped so a null value still marks a token.
	 */
	private function tokenValue(array $node): ?array {
		if (array_key_exists('$value', $node) === true) {
			return ['value' => $node['$value']];
		}

		if (array_key_exists('value', $node) === true && is_array($node['value']) === false) {
			return ['value' => $node['value']];
		}

		return null;
	}//end tokenValue()

	/**
	 * Whether a colour token's value is refused: a string that is neither an
	 * alias nor a colour. Other shapes are the converter's to read.
	 *
	 * @param mixed $value The token value.
	 *
	 * @return boolean True when it is refused.
	 */
	private function isRefusedColour(mixed $value): bool {
		if (is_string($value) === false || preg_match('/^\s*\{[^}]+\}\s*$/', $value) === 1) {
			return false;
		}

		return $this->values->isValid(type: 'color', value: $value) === false && $this->isModernColourFunction(value: $value) === false;
	}//end isRefusedColour()

	/**
	 * A CSS Color 4 function: space-separated `hsl(0 100% 40%)`, a slash alpha,
	 * hwb(), lab(), lch(), oklab(), oklch() or color(). Design systems ship
	 * these in their DTCG sources (Utrecht does), and the browser reads them.
	 *
	 * @param string $value The value.
	 *
	 * @return boolean True for a colour function.
	 */
	private function isModernColourFunction(string $value): bool {
		return preg_match('/^\s*(rgb|rgba|hsl|hsla|hwb|lab|lch|oklab|oklch|color)\(\s*[-+0-9.%a-z\s,\/]+\)\s*$/i', $value) === 1;
	}//end isModernColourFunction()
}//end class
