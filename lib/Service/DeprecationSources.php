<?php

/**
 * The collaborators the token deprecation endpoints read and record deprecations through.
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
 * The deprecation records and the custom overrides a deprecated token may still be set in.
 *
 * @spec openspec/specs/token-sets/spec.md
 */
final class DeprecationSources {

	/**
	 * Constructor.
	 *
	 * @param TokenDeprecationService $deprecations The records.
	 * @param CustomOverridesService $overrides Rewrites the overrides files after a change.
	 *
	 * @spec openspec/specs/token-sets/spec.md
	 */
	public function __construct(
		public readonly TokenDeprecationService $deprecations,
		public readonly CustomOverridesService $overrides,
	) {
	}//end __construct()
}//end class
