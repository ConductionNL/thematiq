<?php

/**
 * NL Design CSS Parser Service.
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
 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-27
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * Service for parsing CSS custom property declarations.
 *
 * Extracts --token-name: value pairs from raw CSS strings.
 *
 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-27
 * @spec openspec/specs/dark-mode/spec.md
 */
class CssParserService {
	/**
	 * Parse CSS custom property declarations from a raw CSS string.
	 *
	 * Matches all lines like: --some-token: some-value;
	 *
	 * @param string $content The raw CSS content.
	 *
	 * @return array<string, string>|null Parsed token map, or null if none found.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-27
	 *
	 * @SuppressWarnings(PHPMD.CyclomaticComplexity) - a CSS tokenizer is a state machine: comments, strings, nesting and fallbacks are states,
	 *   not helpers, and extracting them would pass the whole state between methods.
	 * @SuppressWarnings(PHPMD.NPathComplexity) - a CSS tokenizer is a state machine: comments, strings, nesting and fallbacks are states, not
	 *   helpers, and extracting them would pass the whole state between methods.
	 * @SuppressWarnings(PHPMD.ExcessiveMethodLength) - a CSS tokenizer is a state machine: comments, strings, nesting and fallbacks are
	 *   states, not helpers, and extracting them would pass the whole state between methods.
	 */
	public function parseDeclarations(string $content): ?array {
		// Scanned character by character rather than matched with one regex,
		// because a `;` is only a declaration terminator when it is NOT inside a
		// string or a `url(…)`. The previous `([^;]+);` pattern stopped at the
		// first `;` it saw, and the `;` in `url("data:image/svg+xml;base64,…")`
		// is the first one: every such declaration came back truncated to
		// `url("data:image/svg+xml`, an unterminated CSS string. Written back
		// out, that single unclosed quote swallowed the whole rest of the
		// `:root` block — an NL Design System theme carrying three inline logos
		// lost 650 of its 740 declarations, including every colour, while the
		// file still looked plausible in an editor.
		//
		// Terminators are `;` at paren depth 0 and `}` (which also ends the last
		// declaration of a rule when it has no trailing `;` — legal CSS the old
		// pattern could not see at all). Anything that is not `--name: value` is
		// skipped, so a selector, an at-rule or a stray brace is simply not a
		// declaration.
		$parsed = [];
		$length = strlen($content);
		$buffer = '';
		$depth = 0;
		$quote = null;

		for ($index = 0; $index < $length; $index++) {
			$char = $content[$index];

			if ($quote !== null) {
				$buffer .= $char;

				// A backslash escape inside a string consumes the next byte, so
				// an escaped quote cannot end the string early.
				if ($char === '\\' && ($index + 1) < $length) {
					$buffer .= $content[($index + 1)];
					$index++;
					continue;
				}

				if ($char === $quote) {
					$quote = null;
				}

				continue;
			}//end if

			// Comments are skipped outright, never buffered. They are not
			// declarations, and their prose is actively hostile to a scanner:
			// an apostrophe in "the theme's own steps" would otherwise open a
			// string that stays open until the next apostrophe several comments
			// later, swallowing every declaration in between.
			if ($char === '/' && ($index + 1) < $length && $content[($index + 1)] === '*') {
				$end = strpos($content, '*/', ($index + 2));
				if ($end === false) {
					break;
				}

				$index = ($end + 1);
				continue;
			}

			if ($char === '"' || $char === '\'') {
				$quote = $char;
				$buffer .= $char;
				continue;
			}

			if ($char === '(') {
				$depth++;
				$buffer .= $char;
				continue;
			}

			if ($char === ')') {
				$depth = max(0, ($depth - 1));
				$buffer .= $char;
				continue;
			}

			if (($char === ';' && $depth === 0) || $char === '}') {
				$this->collectDeclaration(chunk: $buffer, parsed: $parsed);
				$buffer = '';

				// A rule boundary also resets an unbalanced `(` so one malformed
				// value cannot swallow every declaration after it.
				if ($char === '}') {
					$depth = 0;
				}

				continue;
			}

			$buffer .= $char;
		}//end for

		// Whatever is left after the last terminator (a final declaration with
		// neither `;` nor `}`).
		$this->collectDeclaration(chunk: $buffer, parsed: $parsed);

		if (empty($parsed) === true) {
			return null;
		}

		return $parsed;
	}//end parseDeclarations()

	/**
	 * Add one scanned chunk to the declaration map when it is a custom property.
	 *
	 * The chunk may carry leading junk (`:root {`, `}`, a selector) because the
	 * scanner splits on terminators rather than on rule boundaries, so the
	 * custom-property name is located inside it rather than assumed to start it.
	 * A chunk with no `--name:` at all is not a declaration and is dropped.
	 *
	 * @param string                $chunk  The raw text between two terminators.
	 * @param array<string, string> $parsed The declaration map, appended to by reference.
	 *
	 * @return void
	 */
	private function collectDeclaration(string $chunk, array &$parsed): void {
		if (trim($chunk) === '') {
			return;
		}

		if (preg_match('/(--[\w-]+)\s*:\s*(.*)$/s', $chunk, $match) !== 1) {
			return;
		}

		$value = trim($match[2]);

		// Strip a trailing !important so persisted overrides (which are written
		// with !important to win the cascade) round-trip back to the editor as
		// the clean value the admin entered.
		$value = trim(preg_replace('/\s*!\s*important\s*$/i', '', $value));

		if ($value === '') {
			return;
		}

		$parsed[trim($match[1])] = $value;
	}//end collectDeclaration()

	/**
	 * Parse CSS custom property declarations from within a :root {} block.
	 *
	 * @param string $css The raw CSS string containing a :root {} block.
	 *
	 * @return array<string, string> Map of token name => value.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-27
	 */
	public function parseRootBlock(string $css): array {
		if (preg_match('/:root\s*\{([^}]*)\}/s', $css, $rootMatch) !== 1) {
			return [];
		}

		$result = $this->parseDeclarations(content: $rootMatch[1]);

		if ($result !== null) {
			return $result;
		}

		return [];
	}//end parseRootBlock()

	/**
	 * Parse hand-authored dark-mode declarations from a top-level
	 * `@media (prefers-color-scheme: dark) { :root { ... } }` block.
	 *
	 * This is the hand-authored override extraction point for dark-variant
	 * generation (see the `dark-mode` spec's override requirement): any
	 * `--nldesign-*` declaration found inside the block is treated as an
	 * author-supplied dark value that MUST replace the algorithmically
	 * derived one for the same token. Absence of the block, or CSS the
	 * pattern cannot recognise, degrades to an empty map — never an
	 * exception — so generation always has something safe to merge over.
	 *
	 * @param string $css The raw CSS content of a token set file.
	 *
	 * @return array<string, string> Map of token name => hand-authored dark value (empty when absent).
	 *
	 * @spec openspec/specs/dark-mode/spec.md
	 */
	public function parseDarkBlock(string $css): array {
		$pattern = '/@media\s*\(\s*prefers-color-scheme\s*:\s*dark\s*\)\s*\{\s*:root\s*\{([^}]*)\}\s*\}/is';

		if (preg_match($pattern, $css, $match) !== 1) {
			return [];
		}

		return ($this->parseDeclarations(content: $match[1]) ?? []);
	}//end parseDarkBlock()
}//end class
