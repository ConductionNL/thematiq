<?php

/**
 * The collaborators a configuration bundle reads and writes app settings through.
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

use OCP\App\IAppManager;
use OCP\IConfig;

/**
 * The app config, the app manager, the per-app theming and the upstream freshness toggle.
 *
 * @spec openspec/specs/config-portability/spec.md
 */
final class ConfigBundleAppSettings {

	/**
	 * Constructor.
	 *
	 * @param IConfig $config The config service.
	 * @param IAppManager $appManager The app manager.
	 * @param AppThemingService $appThemingService The per-app theming service.
	 * @param UpstreamFreshnessService $freshnessService The upstream freshness toggle service.
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 */
	public function __construct(
		public readonly IConfig $config,
		public readonly IAppManager $appManager,
		public readonly AppThemingService $appThemingService,
		public readonly UpstreamFreshnessService $freshnessService,
	) {
	}//end __construct()
}//end class
