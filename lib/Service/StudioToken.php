<?php

/**
 * One token placed in a Tokens Studio file.
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
 * @spec openspec/specs/figma-token-files/spec.md#requirement-any-token-set-can-be-downloaded-for-figma
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * A Tokens Studio token and the path it goes to.
 *
 * @spec openspec/specs/figma-token-files/spec.md#requirement-any-token-set-can-be-downloaded-for-figma
 */
final class StudioToken {

	/**
	 * Constructor.
	 *
	 * @param array<int, string> $path The path of the token.
	 * @param array<string, string> $token The token: its value and its type, under the file format's keys.
	 */
	public function __construct(
		public readonly array $path,
		public readonly array $token,
	) {
	}//end __construct()
}//end class
