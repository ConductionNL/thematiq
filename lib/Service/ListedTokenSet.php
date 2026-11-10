<?php

/**
 * One token set as the catalogue lists it, while its entry is being built.
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
 * @spec openspec/specs/token-sets/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * A set of the catalogue: its id, the app it ships in, its merged manifest
 * metadata and its catalogue entry so far.
 *
 * @spec openspec/specs/token-sets/spec.md#requirement-incomplete-sets-are-surfaced-in-the-admin-dropdown
 */
final class ListedTokenSet {

	/**
	 * Constructor.
	 *
	 * @param string $id The token set id.
	 * @param string $appPath The app directory path.
	 * @param array<string, mixed> $meta The merged manifest metadata for this id.
	 * @param array<string, mixed> $entry The token set entry being built.
	 */
	public function __construct(
		public readonly string $id,
		public readonly string $appPath,
		public readonly array $meta,
		public readonly array $entry,
	) {
	}//end __construct()
}//end class
