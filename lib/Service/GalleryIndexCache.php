<?php

/**
 * The collaborators that hold the cached gallery index.
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
 * @spec openspec/specs/theme-gallery/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IAppConfig;

/**
 * The app config the cache lives in as a lazy value, and the clock that ages it.
 *
 * @spec openspec/specs/theme-gallery/spec.md
 */
final class GalleryIndexCache {

	/**
	 * Constructor.
	 *
	 * @param IAppConfig $appConfig Holds the cached index as a lazy value.
	 * @param ITimeFactory $time The clock.
	 *
	 * @spec openspec/specs/theme-gallery/spec.md
	 */
	public function __construct(
		public readonly IAppConfig $appConfig,
		public readonly ITimeFactory $time,
	) {
	}//end __construct()
}//end class
