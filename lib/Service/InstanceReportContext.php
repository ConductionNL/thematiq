<?php

/**
 * The collaborators that describe the instance a report is made on.
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

use OCP\App\IAppManager;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;
use OCP\IURLGenerator;

/**
 * The app's files on disk, the instance config, its base URL and the clock: what a report's
 * metadata block reads.
 *
 * @spec openspec/specs/compliance-evidence/spec.md
 */
final class InstanceReportContext {

	/**
	 * Constructor.
	 *
	 * @param IAppManager $appManager The app manager, for the app's CSS files on disk.
	 * @param IConfig $config The config, for the active token set and the instance id.
	 * @param IURLGenerator $urlGenerator The URL generator, for the instance base URL.
	 * @param ITimeFactory $timeFactory The clock, for the generation timestamp.
	 *
	 * @spec openspec/specs/compliance-evidence/spec.md
	 */
	public function __construct(
		public readonly IAppManager $appManager,
		public readonly IConfig $config,
		public readonly IURLGenerator $urlGenerator,
		public readonly ITimeFactory $timeFactory,
	) {
	}//end __construct()
}//end class
