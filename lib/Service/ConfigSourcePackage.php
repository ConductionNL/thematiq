<?php

/**
 * The deployment package the config source applies: where it is and its content hash.
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
 * @spec openspec/specs/theme-as-code/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * One read of the deployment package: its path and the hash of what is there.
 *
 * @spec openspec/specs/theme-as-code/spec.md
 */
final class ConfigSourcePackage {

	/**
	 * Constructor.
	 *
	 * @param string $path The package path.
	 * @param string $hash The package hash.
	 */
	public function __construct(
		public readonly string $path,
		public readonly string $hash,
	) {
	}//end __construct()
}//end class
