<?php

/**
 * The entries of the app's token-sets.json.
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
 * @spec openspec/specs/token-set-contrast-audit/spec.md#requirement-automated-contrast-audit-over-all-shipped-token-sets
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * Reads `token-sets.json` from an app root: its entries, or none when the
 * file is unreadable or not a list.
 *
 * @spec openspec/specs/token-set-contrast-audit/spec.md#requirement-automated-contrast-audit-over-all-shipped-token-sets
 */
final class TokenSetManifestFile {

	/**
	 * The entries of the app's `token-sets.json`, or none when it is unreadable or not a list.
	 *
	 * @param string $appPath The app's install path.
	 *
	 * @return array<mixed> The entries.
	 *
	 * @spec openspec/specs/token-set-contrast-audit/spec.md#requirement-automated-contrast-audit-over-all-shipped-token-sets
	 */
	public static function entries(string $appPath): array {
		$manifestPath = $appPath . '/token-sets.json';
		if (is_readable($manifestPath) === false) {
			return [];
		}

		$manifest = json_decode((string)file_get_contents($manifestPath), true);
		if (is_array($manifest) === false) {
			return [];
		}

		return $manifest;
	}//end entries()
}//end class
