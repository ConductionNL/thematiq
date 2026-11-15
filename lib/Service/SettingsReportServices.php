<?php

/**
 * The collaborators behind the compliance, e-mail and freshness settings endpoints.
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
 * @spec openspec/specs/token-sets/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * The compliance evidence report, the e-mail theming settings and the upstream token freshness check.
 *
 * @spec openspec/specs/token-sets/spec.md
 */
final class SettingsReportServices {

	/**
	 * Constructor.
	 *
	 * @param ComplianceReportService $complianceService The compliance evidence report service.
	 * @param EmailThemingSettings $emailTheming The email theming settings.
	 * @param UpstreamFreshnessService $freshnessService The upstream token freshness service.
	 *
	 * @spec openspec/specs/token-sets/spec.md
	 */
	public function __construct(
		public readonly ComplianceReportService $complianceService,
		public readonly EmailThemingSettings $emailTheming,
		public readonly UpstreamFreshnessService $freshnessService,
	) {
	}//end __construct()
}//end class
