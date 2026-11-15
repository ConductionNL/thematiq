<?php

/**
 * What the admin settings page publishes to the browser, and the request it answers.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Settings
 * @package   OCA\Thematiq
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 *
 * @spec openspec/specs/admin-settings/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Settings;

use OCA\Thematiq\Service\PlaygroundStateService;
use OCP\AppFramework\Services\IInitialState;
use OCP\IRequest;

/**
 * The page side of the admin settings form: the initial state it hands to admin.js, the
 * component playground's boot state, and the request (for the presentation-mock switch).
 *
 * @spec openspec/specs/admin-settings/spec.md
 */
final class AdminPageState {

	/**
	 * The page's collaborators.
	 *
	 * @param IInitialState $initialState Carries server state to admin.js.
	 * @param IRequest $request The current request.
	 * @param PlaygroundStateService $playground What the component playground reads at boot.
	 */
	public function __construct(
		public readonly IInitialState $initialState,
		public readonly IRequest $request,
		public readonly PlaygroundStateService $playground,
	) {
	}//end __construct()
}//end class
