<?php

/**
 * Thematiq Copy Values.
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
 * @spec openspec/changes/not-found-page-board/specs/workplace-layout/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * The rules every piece of page copy a token set carries follows: the login
 * page's ({@see LoginCopyService}) and the not-found page's
 * ({@see NotFoundCopyService}).
 *
 * A value is one string, used in every language, or a map from a language
 * code to a string. A language the map does not name gets nothing, so the
 * page keeps Nextcloud's own words. A value longer than {@see self::MAX_LENGTH}
 * is dropped, not cut, and an address must be an http(s) address or a path on
 * this server.
 *
 * @spec openspec/changes/not-found-page-board/specs/workplace-layout/spec.md
 */
class CopyValues {

	/**
	 * The longest value accepted; anything longer is dropped, not cut.
	 *
	 * @var int
	 */
	public const MAX_LENGTH = 300;

	/**
	 * The string a value stands for in a language: the value itself, or the
	 * map's entry for the language, else for its primary subtag (`nl` for
	 * `nl_NL`). Null when there is none, or when it is empty or too long.
	 *
	 * @param mixed $value A string or a map from language code to string.
	 * @param string $language The request's language code.
	 *
	 * @return string|null The trimmed string, or null.
	 *
	 * @spec openspec/changes/login-page-board-parity/specs/workplace-layout/spec.md#requirement-a-token-set-may-carry-the-login-copy
	 */
	public static function pick(mixed $value, string $language): ?string {
		if (is_array($value) === true) {
			$primary = strtolower((string)preg_replace('/[-_].*$/', '', $language));
			$value = ($value[$language] ?? $value[$primary] ?? null);
		}

		if (is_string($value) === false) {
			return null;
		}

		$value = trim($value);
		if ($value === '' || mb_strlen($value) > self::MAX_LENGTH) {
			return null;
		}

		return $value;
	}//end pick()

	/**
	 * Whether an address may be linked: an absolute http(s) address, or a
	 * path on this server (not a protocol-relative one).
	 *
	 * @param string $value The trimmed address.
	 *
	 * @return bool True when it may be linked.
	 *
	 * @spec openspec/changes/login-page-board-parity/specs/workplace-layout/spec.md#requirement-a-token-set-may-carry-the-login-copy
	 */
	public static function isAddress(string $value): bool {
		return (preg_match('#^(https?:/{2}[^\s]+|/(?!/)[^\s]*)$#i', $value) === 1);
	}//end isAddress()

	/**
	 * The camel-case name a key goes by in an initial state: `sso_label` is `ssoLabel`.
	 *
	 * @param string $key The key, as written in token-sets.json.
	 *
	 * @return string The camel-case name.
	 *
	 * @spec openspec/changes/not-found-page-board/specs/workplace-layout/spec.md#requirement-a-token-set-may-carry-the-not-found-copy
	 */
	public static function camel(string $key): string {
		return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $key))));
	}//end camel()
}//end class
