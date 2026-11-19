<?php

/**
 * Thematiq CSS declaration scanner.
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
 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-27
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * One scan of a CSS string for `--name: value` declarations.
 *
 * Scanned character by character rather than matched with one regex, because a `;` is only a
 * declaration terminator when it is NOT inside a string or a `url(…)`: the `;` in
 * `url("data:image/svg+xml;base64,…")` would otherwise cut the value short and leave an
 * unterminated string that swallows the rest of the block when written back out.
 *
 * Terminators are `;` at paren depth 0 and `}` (which also ends the last declaration of a rule
 * when it has no trailing `;`). Anything that is not `--name: value` is skipped, so a selector,
 * an at-rule or a stray brace is simply not a declaration. The object holds the scan position
 * and is used once.
 *
 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-27
 */
final class CssDeclarationScanner {

	/**
	 * Byte length of the content.
	 *
	 * @var integer
	 */
	private int $length;

	/**
	 * The scan position.
	 *
	 * @var integer
	 */
	private int $index = 0;

	/**
	 * The text since the last terminator.
	 *
	 * @var string
	 */
	private string $buffer = '';

	/**
	 * Open parentheses outside strings.
	 *
	 * @var integer
	 */
	private int $depth = 0;

	/**
	 * The quote of the open string, or null outside a string.
	 *
	 * @var string|null
	 */
	private ?string $quote = null;

	/**
	 * The declarations found so far.
	 *
	 * @var array<string, string>
	 */
	private array $parsed = [];

	/**
	 * Constructor.
	 *
	 * @param string $content The raw CSS content.
	 */
	public function __construct(
		private readonly string $content,
	) {
		$this->length = strlen($content);
	}//end __construct()

	/**
	 * Scan the content.
	 *
	 * @return array<string, string> Token name => value, empty when the content declares nothing.
	 *
	 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-27
	 */
	public function scan(): array {
		for ($this->index = 0; $this->index < $this->length; $this->index++) {
			if ($this->step(char: $this->content[$this->index]) === false) {
				break;
			}
		}

		// Whatever is left after the last terminator (a final declaration with neither `;` nor `}`).
		$this->collect(chunk: $this->buffer);

		return $this->parsed;
	}//end scan()

	/**
	 * Consume one character.
	 *
	 * @param string $char The character at the scan position.
	 *
	 * @return bool False when the scan must stop (a comment that never closes).
	 */
	private function step(string $char): bool {
		if ($this->quote !== null) {
			$this->consumeQuoted(char: $char);
			return true;
		}

		// Comments are skipped outright, never buffered: an apostrophe in their prose would
		// otherwise open a string that swallows every declaration up to the next apostrophe.
		if ($char === '/' && $this->next() === '*') {
			return $this->skipComment();
		}

		if ($this->isTerminator(char: $char) === true) {
			$this->terminate(char: $char);
			return true;
		}

		$this->append(char: $char);
		return true;
	}//end step()

	/**
	 * Whether the character ends a declaration: `;` outside parentheses, or `}`.
	 *
	 * @param string $char The character at the scan position.
	 *
	 * @return bool
	 */
	private function isTerminator(string $char): bool {
		return ($char === ';' && $this->depth === 0) || $char === '}';
	}//end isTerminator()

	/**
	 * The character after the scan position, or '' at the end of the content.
	 *
	 * @return string
	 */
	private function next(): string {
		if (($this->index + 1) < $this->length) {
			return $this->content[($this->index + 1)];
		}

		return '';
	}//end next()

	/**
	 * Buffer one character inside a string, ending the string at its closing quote.
	 *
	 * @param string $char The character at the scan position.
	 *
	 * @return void
	 */
	private function consumeQuoted(string $char): void {
		$this->buffer .= $char;

		// A backslash escape consumes the next byte, so an escaped quote cannot end the string early.
		if ($char === '\\' && $this->next() !== '') {
			$this->buffer .= $this->next();
			$this->index++;
			return;
		}

		if ($char === $this->quote) {
			$this->quote = null;
		}
	}//end consumeQuoted()

	/**
	 * Move the scan position to the end of the comment that starts here.
	 *
	 * @return bool False when the comment never closes.
	 */
	private function skipComment(): bool {
		$end = strpos($this->content, '*/', ($this->index + 2));
		if ($end === false) {
			return false;
		}

		$this->index = ($end + 1);
		return true;
	}//end skipComment()

	/**
	 * End the buffered chunk at a terminator.
	 *
	 * @param string $char The terminator, `;` or `}`.
	 *
	 * @return void
	 */
	private function terminate(string $char): void {
		$this->collect(chunk: $this->buffer);
		$this->buffer = '';

		// A rule boundary also resets an unbalanced `(` so one malformed value cannot swallow
		// every declaration after it.
		if ($char === '}') {
			$this->depth = 0;
		}
	}//end terminate()

	/**
	 * Buffer one character outside a string, tracking quotes and paren depth.
	 *
	 * @param string $char The character at the scan position.
	 *
	 * @return void
	 */
	private function append(string $char): void {
		if ($char === '"' || $char === '\'') {
			$this->quote = $char;
		}

		if ($char === '(') {
			$this->depth++;
		}

		if ($char === ')') {
			$this->depth = max(0, ($this->depth - 1));
		}

		$this->buffer .= $char;
	}//end append()

	/**
	 * Add one scanned chunk to the declarations when it is a custom property.
	 *
	 * The chunk may carry leading junk (`:root {`, `}`, a selector) because the scanner splits on
	 * terminators rather than on rule boundaries, so the custom-property name is located inside it
	 * rather than assumed to start it. A chunk with no `--name:` at all is dropped.
	 *
	 * @param string $chunk The raw text between two terminators.
	 *
	 * @return void
	 */
	private function collect(string $chunk): void {
		if (trim($chunk) === '') {
			return;
		}

		if (preg_match('/(--[\w-]+)\s*:\s*(.*)$/s', $chunk, $match) !== 1) {
			return;
		}

		// A trailing !important is stripped so persisted overrides (written with !important to win
		// the cascade) round-trip back to the editor as the clean value the admin entered.
		$value = trim(preg_replace('/\s*!\s*important\s*$/i', '', trim($match[2])));

		if ($value === '') {
			return;
		}

		$this->parsed[trim($match[1])] = $value;
	}//end collect()
}//end class
