<?php

/**
 * The logo files of one shipped set.
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
 * @spec openspec/specs/shell-catalogue/spec.md#requirement-a-logo-is-redistributable-only-when-the-repository-says-so
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * A set's logo files: the light one and the dark one, as repository-relative
 * paths under `img/logos/`.
 *
 * @spec openspec/specs/shell-catalogue/spec.md#requirement-a-logo-is-redistributable-only-when-the-repository-says-so
 */
final class CatalogueLogo {

	/**
	 * Constructor.
	 *
	 * @param string $id The set id.
	 * @param string|null $file The light logo, or null when the set has none.
	 * @param string|null $inverse The dark logo, or null when the set has none.
	 */
	public function __construct(
		public readonly string $id,
		public readonly ?string $file,
		public readonly ?string $inverse,
	) {
	}//end __construct()
}//end class
