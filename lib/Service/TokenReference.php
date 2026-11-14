<?php

/**
 * Thematiq Token Reference.
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
 * @spec openspec/changes/token-references-stay-live/specs/token-editor-ui/spec.md#requirement-a-token-can-refer-to-another-token
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * A token value that refers to another token, `var(--nldesign-...)`.
 *
 * The overrides keep it unresolved, so a later edit of the target reaches every
 * token that refers to it. A reference closes a cycle when following the
 * references among the saved tokens comes back to where it started.
 */
final class TokenReference {

	/**
	 * A reference: `var(--nldesign-...)`, nothing else.
	 *
	 * @var string
	 */
	private const PATTERN = '/^var\(\s*(--nldesign-[a-z0-9-]+)\s*\)$/i';

	/**
	 * The token a value refers to, or null when it is no reference.
	 *
	 * @param mixed $value The token value.
	 *
	 * @return string|null The referred token's name.
	 *
	 * @spec openspec/changes/token-references-stay-live/specs/token-editor-ui/spec.md#requirement-a-token-can-refer-to-another-token
	 */
	public static function targetOf(mixed $value): ?string {
		if (is_string($value) === false || preg_match(self::PATTERN, trim($value), $matches) !== 1) {
			return null;
		}

		return strtolower($matches[1]);
	}//end targetOf()

	/**
	 * The reference value for a token.
	 *
	 * @param string $tokenName The referred token's name.
	 *
	 * @return string `var(<tokenName>)`.
	 */
	public static function to(string $tokenName): string {
		return 'var(' . $tokenName . ')';
	}//end to()

	/**
	 * The cycles among the references of a token set: token name => the chain
	 * that comes back to it, e.g. `[a, b, a]`.
	 *
	 * @param array<array-key, mixed> $tokens Token name => value.
	 *
	 * @return array<string, list<string>> Every token on a cycle, with its chain.
	 *
	 * @spec openspec/changes/token-references-stay-live/specs/token-editor-ui/spec.md#scenario-a-cycle-is-refused
	 */
	public static function cycles(array $tokens): array {
		$cycles = [];
		foreach (array_keys($tokens) as $start) {
			$chain = self::chainFrom(start: (string)$start, tokens: $tokens);
			if ($chain !== null) {
				$cycles[(string)$start] = $chain;
			}
		}

		return $cycles;
	}//end cycles()

	/**
	 * The chain of references from a token back to itself, or null when it ends elsewhere.
	 *
	 * @param string $start The token to start from.
	 * @param array<array-key, mixed> $tokens Token name => value.
	 *
	 * @return list<string>|null The chain, start and end included.
	 */
	private static function chainFrom(string $start, array $tokens): ?array {
		$chain   = [$start];
		$current = self::targetOf(value: ($tokens[$start] ?? null));
		while ($current !== null) {
			$chain[] = $current;
			if ($current === $start) {
				return $chain;
			}

			if (count($chain) > count($tokens) + 1) {
				// A cycle that does not pass through the start: it is found from its own tokens.
				return null;
			}

			$current = self::targetOf(value: ($tokens[$current] ?? null));
		}

		return null;
	}//end chainFrom()
}//end class
