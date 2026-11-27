<?php

/**
 * The two halves of the first-time setup contract.
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
 * @spec openspec/changes/first-time-setup/specs/first-time-setup/spec.md#requirement-the-setup-contract-is-for-administrators-only-req-tqfs-005
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * The status document and the actions, as the setup routes serve them.
 *
 * @spec openspec/changes/first-time-setup/specs/first-time-setup/spec.md#requirement-the-setup-contract-is-for-administrators-only-req-tqfs-005
 */
final class SetupContract {

	/**
	 * Constructor.
	 *
	 * @param SetupStatusService $status The status document.
	 * @param SetupActions $actions The apply and skip actions.
	 */
	public function __construct(
		public readonly SetupStatusService $status,
		public readonly SetupActions $actions,
	) {
	}//end __construct()
}//end class
