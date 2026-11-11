<?php

/**
 * What an uploaded font is called and the role it plays.
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
 * @spec openspec/specs/custom-fonts/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * The display name and the role (`body`, `heading`, ...) an administrator gives an uploaded font.
 *
 * @spec openspec/specs/custom-fonts/spec.md
 */
final class FontLabel {

	/**
	 * Constructor.
	 *
	 * @param string $name The display name.
	 * @param string $role The role.
	 */
	public function __construct(
		public readonly string $name,
		public readonly string $role,
	) {
	}//end __construct()
}//end class
