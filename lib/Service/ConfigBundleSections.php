<?php

/**
 * The sections of a configuration bundle that have their own class.
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
 * @spec openspec/specs/config-portability/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * The custom token sets, the approved mark, document style and brand per app sections, and the own tokens and deprecations section.
 *
 * @spec openspec/specs/config-portability/spec.md
 */
final class ConfigBundleSections {

	/**
	 * Constructor.
	 *
	 * @param CustomTokenSetsBundleSection $customTokenSets The custom token sets section.
	 * @param BundleExtraSections $extraSections The approved mark, document style and brand per app sections.
	 * @param TokenLifecycleBundleSection|null $lifecycle The own tokens and deprecations section.
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 */
	public function __construct(
		public readonly CustomTokenSetsBundleSection $customTokenSets,
		public readonly BundleExtraSections $extraSections,
		public readonly ?TokenLifecycleBundleSection $lifecycle = null,
	) {
	}//end __construct()
}//end class
