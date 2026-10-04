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
		$invalid = [];
		$this->collect(node: $document, type: null, path: '', invalid: $invalid);

		return $invalid;
	}//end findInvalidColours()

	/**
	 * Walk one DTCG node.
	 *
	 * @param array<array-key, mixed> $node The group or token.
	 * @param string|null $type The type inherited from the enclosing groups.
	 * @param string $path The dotted path of the node.
	 * @param array<string, string> $invalid Accumulator, path => value.
	 *
	 * @return void
	 */
	private function collect(array $node, ?string $type, string $path, array &$invalid): void {
		$ownType = ($node['$type'] ?? ($node['type'] ?? null));
		if (is_string($ownType) === true) {
			$type = $ownType;
		}

		$token = $this->tokenValue(node: $node);
		if ($token !== null) {
			if ($type === 'color' && $this->isRefusedColour(value: $token['value']) === true) {
				$invalid[$path] = (string)$token['value'];
			}

			return;
		}

		foreach ($node as $key => $child) {
			if (is_array($child) === false || str_starts_with((string)$key, '$') === true) {
				continue;
			}

			$childPath = (string)$key;
			if ($path !== '') {
				$childPath = $path . '.' . $key;
			}

			$this->collect(node: $child, type: $type, path: $childPath, invalid: $invalid);
		}
	}//end collect()

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
