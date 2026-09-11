<?php

/**
 * NL Design Custom Token Set Validator.
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
 * @spec openspec/changes/custom-token-set-upload/tasks.md#task-1.1
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * Validates and re-serialises an uploaded custom token set.
 *
 * The served CSS file is always generated from the parsed-and-whitelisted
 * declarations, never from the raw upload bytes, so only `--nldesign-*` /
 * `--{slug}-*` custom property declarations inside a single `:root` block can
 * ever reach a file that is served to anonymous users on the login page.
 *
 * Validation is intentionally strict: a custom token set is a whole huisstijl
 * expressed in the published `--nldesign-*` vocabulary. Arbitrary CSS, external
 * resources, and selectors other than `:root` are rejected.
 *
 * @spec openspec/changes/custom-token-set-upload/tasks.md#task-1.1
 */
class CustomTokenSetValidator {

	/**
	 * Maximum accepted upload size in bytes (512 KB).
	 *
	 * @var int
	 */
	public const MAX_SIZE = (512 * 1024);

	/**
	 * The CSS file header comment written to every generated set.
	 *
	 * @var string
	 */
	private const CSS_HEADER = '/* NL Design — custom token set. Generated from an admin upload. Do not edit manually. */';

	/**
	 * Error thrown when validation fails. Carries an HTTP status hint.
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $lastError = null;

	/**
	 * Validate a list of token declarations against the whitelist.
	 *
	 * Splits the declarations into accepted (`--nldesign-*` / `--{slug}-*`)
	 * and skipped (everything else, e.g. Nextcloud `--color-*` variables that
	 * belong in custom-overrides.css). Any accepted declaration whose value
	 * contains a forbidden construct is a hard failure (sets lastError).
	 *
	 * @param array<string, string> $declarations Parsed token name => value map.
	 * @param string $slug The set slug for `--{slug}-*` extras.
	 *
	 * @return array{accepted: array<string, string>, skipped: string[]}|null
	 *                                                                        The split, or null on hard failure (see getLastError()).
	 *
	 * @spec openspec/changes/custom-token-set-upload/tasks.md#task-1.1
	 */
	public function validateDeclarations(array $declarations, string $slug): ?array {
		$this->lastError = null;

		$accepted = [];
		$skipped = [];

		// The app's own vocabulary, the set's brand prefix, and the component
		// layer. The component prefixes are accepted because converter output
		// NEEDS them: `css/systems/nldesign/utrecht-bridge.css` maps
		// `--utrecht-*` / `--ams-*` / `--denhaag-*` onto `--nldesign-component-*`,
		// and Conduction's apps read them directly, so a converted set that
		// dropped them would lose its component layer at the last gate.
		// Values are still checked by isForbiddenValue() below — widening WHICH
		// names may be declared does not widen what may be in them.
		$componentPrefixes = [];
		foreach (TokenSetConverterService::COMPONENT_PREFIXES as $prefix) {
			$componentPrefixes[] = preg_quote(trim($prefix, '-'), '/');
		}

		$namePattern = '/^--(nldesign|' . preg_quote($slug, '/') . '|'
			. implode('|', $componentPrefixes) . ')-[a-z0-9-]+$/';

		foreach ($declarations as $name => $value) {
			if (preg_match($namePattern, $name) !== 1) {
				// Not part of the supported vocabulary — counted, not written.
				$skipped[] = $name;
				continue;
			}

			if ($this->isForbiddenValue(value: $value) === true) {
				$this->lastError = [
					'status' => 422,
					'message' => 'Property ' . $name . ' contains a forbidden value (external resource, @import, expression, or markup).',
					'property' => $name,
				];

				return null;
			}

			$accepted[$name] = trim($value);
		}//end foreach

		if (empty($accepted) === true) {
			$this->lastError = [
				'status' => 422,
				'message' => 'No --nldesign-* declarations found in the uploaded token set.',
			];

			return null;
		}

		return [
			'accepted' => $accepted,
			'skipped' => $skipped,
		];
	}//end validateDeclarations()

	/**
	 * Determine whether a CSS string contains a selector other than `:root`.
	 *
	 * Used as a pre-parse structural guard: the upload MUST reduce to exactly
	 * one `:root { … }` rule. Any at-rule (@import, @font-face, @media) or any
	 * other selector block is rejected.
	 *
	 * @param string $css The raw CSS upload.
	 *
	 * @return bool True when a disallowed selector or at-rule is present.
	 *
	 * @spec openspec/changes/custom-token-set-upload/tasks.md#task-1.1
	 */
	public function hasDisallowedSelector(string $css): bool {
		// Strip comments so a commented-out selector does not trip the guard.
		$stripped = preg_replace('#/\*.*?\*/#s', '', $css);

		// Any at-rule is forbidden (covers @import, @font-face, @media, @charset).
		if (preg_match('/@[a-z-]+/i', $stripped) === 1) {
			return true;
		}

		// Find every block selector (text preceding a `{`). The only one allowed
		// is `:root` (optionally `html` is NOT allowed — strictly :root).
		if (preg_match_all('/([^{}]+)\{/', $stripped, $matches) > 0) {
			foreach ($matches[1] as $selector) {
				$selector = trim($selector);
				if ($selector !== '' && strcasecmp($selector, ':root') !== 0) {
					return true;
				}
			}
		}

		return false;
	}//end hasDisallowedSelector()

	/**
	 * Determine whether a single declaration value is forbidden.
	 *
	 * Rejects: @import, expression(, javascript:, raw markup (`<`), CSS
	 * injection characters (`;`, `{`, `}`) outside of a url(...) payload,
	 * CSS comment delimiters (`/*`, `*​/`) outside of a url(...) payload,
	 * and url() with a scheme or host. Relative url('../../img/…') and
	 * data:image/svg+xml URIs (which legitimately contain a `;` in their
	 * `data:image/svg+xml;base64,` mime prefix) are permitted, matching
	 * bundled logo usage.
	 *
	 * The `;` and comment-delimiter guard mirrors
	 * {@see CustomOverridesService::buildDeclarationLines()} — without it a
	 * value such as `red; --nldesign-evil: url(javascript:alert(1))` or
	 * `red } /* to close the block early` could smuggle an extra
	 * declaration or unbalanced comment past the whitelist and into the
	 * generated :root block.
	 *
	 * @param string $value The declaration value.
	 *
	 * @return bool True when the value must be rejected.
	 *
	 * @spec openspec/changes/custom-token-set-upload/tasks.md#task-1.1
	 * @spec openspec/changes/harden-custom-token-set-value-validation/tasks.md#task-1
	 */
	public function isForbiddenValue(string $value): bool {
		if ($this->containsDangerousKeyword(value: $value) === true) {
			return true;
		}

		// Injection characters are checked with every url(...) payload
		// stripped out first, since a legitimate data:image/svg+xml;base64,…
		// URI legitimately contains a `;` that is not a declaration
		// terminator.
		$withoutUrlPayloads = preg_replace('/url\(\s*([\'"]?).*?\1\s*\)/i', '', $value);

		if ($this->containsInjectionCharacter(value: $withoutUrlPayloads) === true) {
			return true;
		}

		return $this->hasDisallowedUrlTarget(value: $value);
	}//end isForbiddenValue()

	/**
	 * Determine whether a value contains a keyword-based dangerous construct:
	 * `@import`, `expression(`, `javascript:`, or raw markup (`<`).
	 *
	 * @param string $value The declaration value.
	 *
	 * @return bool True when a dangerous keyword is present.
	 *
	 * @spec openspec/changes/harden-custom-token-set-value-validation/tasks.md#task-1
	 */
	private function containsDangerousKeyword(string $value): bool {
		$lower = strtolower($value);

		return str_contains($lower, '@import') === true
			|| str_contains($lower, 'expression(') === true
			|| str_contains($lower, 'javascript:') === true
			|| str_contains($value, '<') === true;
	}//end containsDangerousKeyword()

	/**
	 * Determine whether a value (with any url(...) payload already stripped)
	 * contains a CSS injection character: `{`, `}`, `;`, `/*`, or `*​/`.
	 *
	 * @param string $value The value with url(...) payloads stripped.
	 *
	 * @return bool True when an injection character is present.
	 *
	 * @spec openspec/changes/harden-custom-token-set-value-validation/tasks.md#task-1
	 */
	private function containsInjectionCharacter(string $value): bool {
		return str_contains($value, '{') === true
			|| str_contains($value, '}') === true
			|| str_contains($value, ';') === true
			|| str_contains($value, '/*') === true
			|| str_contains($value, '*/') === true;
	}//end containsInjectionCharacter()

	/**
	 * Determine whether any url(...) occurrence in the value targets a
	 * disallowed scheme, protocol-relative reference, or host. Relative
	 * paths and permitted data:image/* URIs are not disallowed.
	 *
	 * @param string $value The declaration value.
	 *
	 * @return bool True when a url(...) target is disallowed.
	 *
	 * @spec openspec/changes/custom-token-set-upload/tasks.md#task-1.1
	 */
	private function hasDisallowedUrlTarget(string $value): bool {
		if (preg_match_all('/url\(\s*([\'"]?)(.*?)\1\s*\)/i', $value, $urls, PREG_SET_ORDER) === 0) {
			return false;
		}

		foreach ($urls as $url) {
			$target = trim($url[2]);

			// Data image URIs (svg+xml, png, …) are allowed.
			if (preg_match('#^data:image/(svg\+xml|png|jpeg|gif|webp)#i', $target) === 1) {
				continue;
			}

			// A scheme or protocol-relative or host reference is forbidden.
			if (preg_match('#^([a-z][a-z0-9+.-]*:|//)#i', $target) === 1) {
				return true;
			}

			// Bare relative paths (../img/..., img/...) are allowed.
		}

		return false;
	}//end hasDisallowedUrlTarget()

	/**
	 * Re-serialise accepted declarations into a canonical CSS file body.
	 *
	 * The output is generated from the parsed declarations only — the raw
	 * upload bytes never reach the served file.
	 *
	 * @param array<string, string> $declarations Accepted token name => value map.
	 *
	 * @return string The canonical CSS file content.
	 *
	 * @spec openspec/changes/custom-token-set-upload/tasks.md#task-1.1
	 */
	public function serialize(array $declarations): string {
		$lines = [];
		foreach ($declarations as $name => $value) {
			$lines[] = '  ' . $name . ': ' . trim($value) . ';';
		}

		return self::CSS_HEADER . PHP_EOL . ':root {' . PHP_EOL . implode(PHP_EOL, $lines) . PHP_EOL . '}' . PHP_EOL;
	}//end serialize()

	/**
	 * Get the last hard-failure error, if any.
	 *
	 * @return array<string, mixed>|null The error with a `status` and `message`.
	 *
	 * @spec openspec/changes/custom-token-set-upload/tasks.md#task-1.1
	 */
	public function getLastError(): ?array {
		return $this->lastError;
	}//end getLastError()
}//end class
