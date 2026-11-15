<?php

/**
 * Thematiq Switch Window.
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
 * @spec openspec/specs/scheduled-switch/spec.md#requirement-an-administrator-plans-a-switch
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * When a planned switch starts and, optionally, ends.
 *
 * @spec openspec/specs/scheduled-switch/spec.md#requirement-an-administrator-plans-a-switch
 */
final class SwitchWindow {

	/**
	 * Constructor.
	 *
	 * @param string $startAt The start, ISO 8601 with an offset.
	 * @param string|null $endAt The optional end, ISO 8601 with an offset.
	 */
	public function __construct(
		public readonly string $startAt,
		public readonly ?string $endAt = null,
	) {
	}//end __construct()
}//end class
