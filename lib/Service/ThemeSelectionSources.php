<?php

/**
 * The collaborators that say which theme an instance renders.
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
 * @spec openspec/specs/compliance-evidence/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * The custom overrides, the shipped design systems and the admin's own token sets: the three
 * sources the active configuration is read from.
 *
 * @spec openspec/specs/compliance-evidence/spec.md
 */
final class ThemeSelectionSources {

	/**
	 * Constructor.
	 *
	 * @param CustomOverridesService $overrides The custom overrides, the top cascade layer.
	 * @param DesignSystemService $designSystems The shipped token sets and their design systems.
	 * @param CustomTokenSetService $customSets The admin's own token sets.
	 *
	 * @spec openspec/specs/compliance-evidence/spec.md
	 */
	public function __construct(
		public readonly CustomOverridesService $overrides,
		public readonly DesignSystemService $designSystems,
		public readonly CustomTokenSetService $customSets,
	) {
	}//end __construct()
}//end class
