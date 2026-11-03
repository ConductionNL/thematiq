<?php

/**
 * The collaborators a configuration bundle reads token sets and overrides through.
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
 * The token sets, the custom overrides and the planned switches a bundle exports and checks, and the parser that reads an override file.
 *
 * @spec openspec/specs/config-portability/spec.md
 */
final class ConfigBundleTokenSources {

	/**
	 * Constructor.
	 *
	 * @param TokenSetService $tokenSetService The token set service.
	 * @param CustomOverridesService $overridesService The custom overrides service.
	 * @param ScheduledSwitchStore $scheduledSwitches The planned token set switches.
	 * @param CssParserService $cssParser The CSS parser service.
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 */
	public function __construct(
		public readonly TokenSetService $tokenSetService,
		public readonly CustomOverridesService $overridesService,
		public readonly ScheduledSwitchStore $scheduledSwitches,
		public readonly CssParserService $cssParser,
	) {
	}//end __construct()
}//end class
