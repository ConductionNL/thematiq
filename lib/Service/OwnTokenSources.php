<?php

/**
 * The collaborators the own token endpoints read and change tokens through.
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
 * @spec openspec/specs/custom-token-sets/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * The administrator's own tokens, the deprecation records and the custom overrides an own token may appear in.
 *
 * @spec openspec/specs/custom-token-sets/spec.md
 */
final class OwnTokenSources {

	/**
	 * Constructor.
	 *
	 * @param OwnTokenService $ownTokens The own token store.
	 * @param TokenDeprecationService $deprecations Marks a removed token's deprecation as removed.
	 * @param CustomOverridesService $overrides Rewrites the overrides files after a change.
	 *
	 * @spec openspec/specs/custom-token-sets/spec.md
	 */
	public function __construct(
		public readonly OwnTokenService $ownTokens,
		public readonly TokenDeprecationService $deprecations,
		public readonly CustomOverridesService $overrides,
	) {
	}//end __construct()
}//end class
