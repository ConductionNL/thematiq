<?php

/**
 * NL Design Freeform Custom CSS Validator.
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
 * @spec openspec/specs/custom-css-freeform/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * Validates admin-authored freeform CSS before it is persisted and served.
 *
 * Unlike CustomOverridesService — which only ever emits a `:root` block of
 * registry-validated `--color-*` tokens — this layer accepts arbitrary rules,
 * so the submitted text is genuinely untrusted input even though only an
 * administrator can reach the endpoint. Nextcloud's theming delegation means
 * a DELEGATED admin (a lower trust tier than a full admin) can be granted the
 * theming section, so "an admin typed it" is not on its own a sufficient
 * control.
 *
 * The validator is FAIL-CLOSED and ALL-OR-NOTHING: any single violation
 * rejects the entire submission and returns every reason found. Silently
 * stripping the offending fragment is deliberately not done — it would mutate
 * CSS the administrator never reviewed and could change the meaning of the
 * rules that survive.
 *
 * It is a standalone service (no filesystem, no HTTP, no Nextcloud services)
 * precisely so every rule below is unit-testable in isolation.
 *
 * @spec openspec/specs/custom-css-freeform/spec.md
 */
class CustomCssValidator {

	/**
	 * Maximum accepted payload size in bytes.
	 *
	 * 64 KiB is far beyond any hand-authored theme tweak while keeping the
	 * regex scans below bounded and audit-log entries small.
	 *
	 * @var integer
	 */
	public const MAX_BYTES = 65536;

	/**
	 * Variables whose values Nextcloud derives for dark mode.
	 *
	 * REQ-CSS-007: overriding any of these breaks Nextcloud's own automatic
	 * dark-mode derivation. The bridge layer is already forbidden from setting
	 * them; freeform CSS must not become a back door to the same breakage.
	 * Checked across the WHOLE document, not just a `:root` block, since a
	 * freeform author can set them under any selector.
	 *
	 * @var string[]
	 */
	public const RESERVED_VARIABLES = [
		'--color-main-background',
		'--color-main-background-rgb',
		'--color-main-background-translucent',
		'--color-main-background-blur',
		'--color-background-plain',
		'--color-background-plain-text',
		'--background-invert-if-dark',
		'--background-invert-if-bright',
	];

	/**
	 * Patterns checked before the url() rule, with their messages.
	 *
	 * `@import` fetches a remote stylesheet on every page load — both an
	 * exfiltration channel (the request itself carries the referrer) and a
	 * content-injection vector. `@charset` can change how the remaining
	 * bytes are decoded.
	 *
	 * @var array<string, string>
	 */
	private const BLOCKED_BEFORE_URLS = [
		'/@(?:import|charset)\b/i' => '@import and @charset are not allowed.',
	];

	/**
	 * Patterns checked after the url() rule, with their messages.
	 *
	 * Legacy script-execution vectors: modern engines ignore them, but they
	 * cost nothing to refuse and keep the rule set honest. And, as defence in
	 * depth, HTML tags: the document is served as a linked stylesheet, not
	 * inlined, so a breakout is not currently reachable — but refusing the
	 * sequences means a future change to inline delivery cannot silently turn
	 * this into an XSS sink.
	 *
	 * @var array<string, string>
	 */
	private const BLOCKED_AFTER_URLS = [
		'/(?:expression\s*\(|behavior\s*:|-moz-binding\s*:)/i' => 'expression(), behavior: and -moz-binding: are not allowed.',
		'#</\s*style|<\s*script#i' => 'HTML tags are not allowed in custom CSS.',
	];

	/**
	 * Validate a freeform CSS document.
	 *
	 * @param string $css The admin-submitted CSS.
	 *
	 * @return string[] Human-readable reasons the submission was rejected.
	 *                  An EMPTY array means the CSS is acceptable.
	 *
	 * @spec openspec/specs/custom-css-freeform/spec.md
	 */
	public function validate(string $css): array {
		return [
			...$this->sizeErrors(css: $css),
			...$this->matchedErrors(css: $css, rules: self::BLOCKED_BEFORE_URLS),
			...$this->urlErrors(css: $css),
			...$this->matchedErrors(css: $css, rules: self::BLOCKED_AFTER_URLS),
			...$this->reservedErrors(css: $css),
			...$this->braceErrors(css: $css),
		];
	}//end validate()

	/**
	 * The error for a document over the size limit.
	 *
	 * @param string $css The submitted CSS.
	 *
	 * @return array<int, string> The error, or none.
	 */
	private function sizeErrors(string $css): array {
		if (strlen($css) <= self::MAX_BYTES) {
			return [];
		}

		return [sprintf('Custom CSS is %d bytes, which exceeds the %d byte limit.', strlen($css), self::MAX_BYTES)];
	}//end sizeErrors()

	/**
	 * The message of every rule whose pattern the document matches, in the rules' order.
	 *
	 * @param string $css The submitted CSS.
	 * @param array<string, string> $rules Pattern => message.
	 *
	 * @return array<int, string> The errors.
	 */
	private function matchedErrors(string $css, array $rules): array {
		$errors = [];
		foreach ($rules as $pattern => $message) {
			if (preg_match($pattern, $css) === 1) {
				$errors[] = $message;
			}
		}

		return $errors;
	}//end matchedErrors()

	/**
	 * The error for a url() that could reach another origin.
	 *
	 * The url() function is the classic CSS exfiltration channel: with
	 * attribute selectors it can leak page content to a third party via
	 * background-image, @font-face src, cursor or list-style-image.
	 * Relative/same-origin references and data: URIs cannot reach another
	 * origin, so those remain available for legitimate theming.
	 *
	 * @param string $css The submitted CSS.
	 *
	 * @return array<int, string> The error, or none.
	 */
	private function urlErrors(string $css): array {
		$offendingScheme = $this->firstDisallowedUrlScheme(css: $css);
		if ($offendingScheme === null) {
			return [];
		}

		return [
			sprintf(
				'External url() references are not allowed (found "%s"); use a relative path or a data: URI.',
				$offendingScheme
			),
		];
	}//end urlErrors()

	/**
	 * The errors for the reserved dark-mode variables the document sets.
	 *
	 * @param string $css The submitted CSS.
	 *
	 * @return array<int, string> One error per reserved variable set.
	 */
	private function reservedErrors(string $css): array {
		$errors = [];
		foreach (self::RESERVED_VARIABLES as $reserved) {
			if (preg_match('/' . preg_quote($reserved, '/') . '\s*:/', $css) === 1) {
				$errors[] = sprintf(
					'%s is reserved for Nextcloud dark-mode derivation and cannot be set (REQ-CSS-007).',
					$reserved
				);
			}
		}

		return $errors;
	}//end reservedErrors()

	/**
	 * The error for unbalanced braces.
	 *
	 * The document is emitted verbatim as the last stylesheet. An
	 * unbalanced brace would swallow — or prematurely close — the rules
	 * around it, so the cascade must never receive one.
	 *
	 * @param string $css The submitted CSS.
	 *
	 * @return array<int, string> The error, or none.
	 */
	private function braceErrors(string $css): array {
		if ($this->bracesAreBalanced(css: $css) === true) {
			return [];
		}

		return ['Unbalanced braces: every { must have a matching }.'];
	}//end braceErrors()

	/**
	 * Return the first url() scheme in the document that is not permitted.
	 *
	 * EVERY url() occurrence is judged on its own target. The predecessor of
	 * this method asked two DOCUMENT-GLOBAL questions instead — "does a data:
	 * URI appear anywhere?" and "does an http(s)/protocol-relative reference
	 * appear anywhere?" — so a single legitimate data: URI satisfied the first
	 * question and, absent a second http(s)-specific hit, accepted every other
	 * scheme in the same stylesheet (`ftp://`, `chrome-extension://`, …). That
	 * bypass is issue #193; a per-occurrence scan is the only shape that
	 * cannot be disarmed by an unrelated, allowed reference elsewhere.
	 *
	 * Only `data:` is allowed to carry a scheme. Relative and root-relative
	 * references (`../img/logo.svg`, `/apps/thematiq/img/logo.svg`) carry no
	 * scheme at all, so they never match the scan and stay usable.
	 *
	 * @param string $css The admin-submitted CSS.
	 *
	 * @return string|null The first disallowed scheme (e.g. `ftp:` or `//`),
	 *                     or NULL when every url() target is permitted.
	 *
	 * @spec openspec/specs/custom-css-freeform/spec.md
	 */
	private function firstDisallowedUrlScheme(string $css): ?string {
		$found = preg_match_all(
			'#url\(\s*[\'"]?\s*([a-z][a-z0-9+.-]*:|//)#i',
			$css,
			$matches
		);

		if ($found === false || $found === 0) {
			return null;
		}

		foreach ($matches[1] as $scheme) {
			if (strcasecmp($scheme, 'data:') !== 0) {
				return $scheme;
			}
		}

		return null;
	}//end firstDisallowedUrlScheme()

	/**
	 * Report whether every `{` has a matching `}`.
	 *
	 * Braces appearing inside strings or comments are ignored so a legitimate
	 * `content: "{"` declaration is not mistaken for a structural brace.
	 *
	 * @param string $css The CSS to scan.
	 *
	 * @return boolean True when balanced.
	 */
	private function bracesAreBalanced(string $css): bool {
		$depth = 0;

		foreach ($this->structuralBraces(css: $css) as $char) {
			if ($char === '{') {
				$depth++;
				continue;
			}

			$depth--;
			if ($depth < 0) {
				return false;
			}
		}

		return ($depth === 0);
	}//end bracesAreBalanced()

	/**
	 * Collect the `{`/`}` characters that carry structural meaning.
	 *
	 * Comments and quoted strings are consumed whole by {@see skipComment()}
	 * and {@see skipQuoted()}, so any brace inside them never reaches the
	 * result — a legitimate `content: "{"` declaration is not mistaken for a
	 * structural brace, and neither is a brace inside `/ * … * /`.
	 *
	 * @param string $css The CSS to scan.
	 *
	 * @return string[] The structural braces, in source order.
	 */
	private function structuralBraces(string $css): array {
		$braces = [];
		$length = strlen($css);

		for ($i = 0; $i < $length; $i++) {
			$char = $css[$i];

			if ($this->opensCommentAt(css: $css, index: $i, length: $length) === true) {
				$i = $this->skipComment(css: $css, start: $i, length: $length);
				continue;
			}

			if ($char === '"' || $char === "'") {
				$i = $this->skipQuoted(css: $css, start: $i, length: $length, quote: $char);
				continue;
			}

			if ($char === '{' || $char === '}') {
				$braces[] = $char;
			}
		}//end for

		return $braces;
	}//end structuralBraces()

	/**
	 * Report whether a `/*` comment opener starts at the given index.
	 *
	 * @param string $css The CSS being scanned.
	 * @param integer $index The index to test.
	 * @param integer $length The total length of $css.
	 *
	 * @return boolean True when a comment opens at $index.
	 */
	private function opensCommentAt(string $css, int $index, int $length): bool {
		return ($css[$index] === '/' && ($index + 1) < $length && $css[($index + 1)] === '*');
	}//end opensCommentAt()

	/**
	 * Return the index of the last character belonging to the comment that
	 * opens at $start.
	 *
	 * An unterminated comment consumes the remainder of the document, which is
	 * how a browser parses it too.
	 *
	 * @param string $css The CSS being scanned.
	 * @param integer $start The index of the comment's opening slash.
	 * @param integer $length The total length of $css.
	 *
	 * @return integer The index of the closing slash, or $length when unterminated.
	 */
	private function skipComment(string $css, int $start, int $length): int {
		for ($i = ($start + 2); $i < $length; $i++) {
			if ($css[$i] === '*' && ($i + 1) < $length && $css[($i + 1)] === '/') {
				return ($i + 1);
			}
		}

		return $length;
	}//end skipComment()

	/**
	 * Return the index of the quote that closes the string opening at $start.
	 *
	 * A backslash escapes the character that follows it, so an escaped quote
	 * does not terminate the string. An unterminated string consumes the
	 * remainder of the document.
	 *
	 * @param string $css The CSS being scanned.
	 * @param integer $start The index of the opening quote.
	 * @param integer $length The total length of $css.
	 * @param string $quote The quote character that opened the string.
	 *
	 * @return integer The index of the closing quote, or $length when unterminated.
	 */
	private function skipQuoted(string $css, int $start, int $length, string $quote): int {
		for ($i = ($start + 1); $i < $length; $i++) {
			if ($css[$i] === '\\') {
				$i++;
				continue;
			}

			if ($css[$i] === $quote) {
				return $i;
			}
		}

		return $length;
	}//end skipQuoted()
}//end class
