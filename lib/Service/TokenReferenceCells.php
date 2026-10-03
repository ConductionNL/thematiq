<?php

/**
 * Thematiq Token Reference Cells.
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
 * @spec openspec/specs/token-reference/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * Formats the cells of a token reference row, in Markdown and in HTML: a swatch next to a
 * colour value (never the colour alone), escaped text, and the capped list of consumers.
 *
 * @spec openspec/specs/token-reference/spec.md#requirement-the-reference-does-not-rely-on-colour-alone
 */
class TokenReferenceCells {

	/**
	 * How many consumers a row names before "and N more".
	 *
	 * @var integer
	 */
	private const MAX_PAINTS = 5;

	/**
	 * Whether a value is a literal colour a swatch can show.
	 *
	 * @param string $value The value.
	 *
	 * @return boolean True for hex, rgb(a) and hsl(a) literals.
	 *
	 * @spec openspec/specs/token-reference/spec.md#requirement-the-reference-does-not-rely-on-colour-alone
	 */
	public function isColour(string $value): bool {
		return preg_match('/^(#[0-9a-fA-F]{3,8}|(rgb|hsl)a?\([0-9.,%\s\/deg]+\))$/', trim($value)) === 1;
	}//end isColour()

	/**
	 * A value cell as text alone: the value as code, with no swatch image.
	 *
	 * For Markdown read as text, where a swatch is a long data URL drowning the row.
	 *
	 * @param string $value The value, or ''.
	 *
	 * @return string The cell.
	 *
	 * @spec openspec/specs/token-reference/spec.md#requirement-the-reference-does-not-rely-on-colour-alone
	 */
	public function mdPlainValue(string $value): string {
		if ($value === '') {
			return '';
		}

		return '`' . str_replace(['`', '|'], ["'", '\\|'], $value) . '`';
	}//end mdPlainValue()

	/**
	 * A value cell: a swatch for a colour, then the value as code.
	 *
	 * @param string $value The value, or ''.
	 *
	 * @return string The cell.
	 *
	 * @spec openspec/specs/token-reference/spec.md#requirement-the-reference-does-not-rely-on-colour-alone
	 */
	public function mdValue(string $value): string {
		$code = $this->mdPlainValue(value: $value);
		if ($code === '' || $this->isColour(value: $value) === false) {
			return $code;
		}

		// Fully percent-encoded, so the URL holds no space or quote a Markdown link would stop at.
		$svg = rawurlencode(
			'<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14"><rect width="14" height="14" fill="' . trim($value) . '"/></svg>'
		);

		return '![](data:image/svg+xml,' . $svg . ') ' . $code;
	}//end mdValue()

	/**
	 * Text safe inside a Markdown line.
	 *
	 * @param string $text The text.
	 *
	 * @return string The text without characters Markdown or MDX would read as markup.
	 *
	 * @spec openspec/specs/token-reference/spec.md#requirement-the-reference-does-not-rely-on-colour-alone
	 */
	public function mdText(string $text): string {
		return str_replace(['<', '>', '{', '}', '|', '[', ']'], ['', '', '', '', '', '(', ')'], $text);
	}//end mdText()

	/**
	 * The paints cell.
	 *
	 * @param array<int, string> $list The consumers.
	 *
	 * @return string The cell.
	 *
	 * @spec openspec/specs/token-reference/spec.md#requirement-the-reference-does-not-rely-on-colour-alone
	 */
	public function paints(array $list): string {
		if (count($list) <= self::MAX_PAINTS) {
			return implode(', ', $list);
		}

		return implode(', ', array_slice($list, 0, self::MAX_PAINTS)) . ' and ' . (count($list) - self::MAX_PAINTS) . ' more';
	}//end paints()

	/**
	 * A value cell in HTML: a decorative swatch for a colour, then the value as code.
	 *
	 * @param string $value The value, or ''.
	 *
	 * @return string HTML.
	 *
	 * @spec openspec/specs/token-reference/spec.md#requirement-the-reference-does-not-rely-on-colour-alone
	 */
	public function htmlValue(string $value): string {
		if ($value === '') {
			return '';
		}

		$code = '<code>' . $this->esc(text: $value) . '</code>';
		if ($this->isColour(value: $value) === false) {
			return $code;
		}

		return '<span class="swatch" aria-hidden="true" style="background:' . $this->esc(text: trim($value)) . '"></span> ' . $code;
	}//end htmlValue()

	/**
	 * Escape text for HTML.
	 *
	 * @param string $text The text.
	 *
	 * @return string Escaped.
	 *
	 * @spec openspec/specs/token-reference/spec.md#requirement-the-reference-does-not-rely-on-colour-alone
	 */
	public function esc(string $text): string {
		return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
	}//end esc()
}//end class
