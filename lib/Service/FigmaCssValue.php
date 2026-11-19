<?php

/**
 * The CSS value of a token in a thematiq Figma file.
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
 * @spec openspec/specs/figma-token-files/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * Reads a plain token value of a thematiq file as CSS, and finds reference cycles between tokens.
 *
 * @spec openspec/specs/figma-token-files/spec.md
 */
class FigmaCssValue {

	/**
	 * A plain token value as CSS: itself, or `var()` for a reference to another token of the file.
	 *
	 * @param mixed $value The scalar value.
	 * @param array<string, mixed> $leaves Every token of the file by dotted path.
	 *
	 * @return string|null The CSS value, or null for a boolean, an empty value, a sum over
	 *                     references or a reference to a token the file does not hold.
	 *
	 * @spec openspec/specs/figma-token-files/spec.md#requirement-a-file-from-figma-becomes-a-custom-token-set
	 */
	public function cssValue(mixed $value, array $leaves): ?string {
		if (is_bool($value) === true || trim((string)$value) === '') {
			return null;
		}

		$value = trim((string)$value);

		return ($this->referenceVar(value: $value, leaves: $leaves) ?? $this->plainCss(value: $value));
	}//end cssValue()

	/**
	 * A reference to another token of the file, as `var()`.
	 *
	 * @param string $value The trimmed value.
	 * @param array<string, mixed> $leaves Every token of the file by dotted path.
	 *
	 * @return string|null The `var()`, or null when the value is no reference to a token the file holds.
	 */
	private function referenceVar(string $value, array $leaves): ?string {
		if (preg_match('/^\{([a-z][a-z0-9]*(?:\.[a-z0-9-]+)+)\}$/', $value, $match) === 1 && isset($leaves[$match[1]]) === true) {
			return 'var(--' . str_replace('.', '-', $match[1]) . ')';
		}

		return null;
	}//end referenceVar()

	/**
	 * A value without references as CSS.
	 *
	 * A sum over references is not a CSS value. Everything else is judged by the token set
	 * validator, which sees the file that would be stored: a data: image may hold markup.
	 *
	 * @param string $value The trimmed value.
	 *
	 * @return string|null The value, or null when it still holds a brace.
	 */
	private function plainCss(string $value): ?string {
		if (str_contains($value, '{') === true || str_contains($value, '}') === true) {
			return null;
		}

		return $value;
	}//end plainCss()

	/**
	 * Whether following a token's references leads back to a token already passed.
	 *
	 * @param string $start The dotted path of the token.
	 * @param array<string, array<string, mixed>> $leaves Every token by dotted path.
	 *
	 * @return bool True for a cycle.
	 *
	 * @spec openspec/specs/figma-token-files/spec.md#requirement-a-file-from-figma-becomes-a-custom-token-set
	 */
	public function cycles(string $start, array $leaves): bool {
		$seen = [];
		$current = $start;
		while (isset($leaves[$current]) === true) {
			if (isset($seen[$current]) === true) {
				return true;
			}

			$seen[$current] = true;
			$value = $leaves[$current]['token']['$value'];
			if (is_string($value) === false || preg_match('/^\{([^{}]+)\}$/', trim($value), $match) !== 1) {
				return false;
			}

			$current = $match[1];
		}

		return false;
	}//end cycles()
}//end class
