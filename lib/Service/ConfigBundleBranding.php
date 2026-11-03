<?php

/**
 * The collaborators a configuration bundle reads mail and font branding through, and logs with.
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

use Psr\Log\LoggerInterface;

/**
 * The e-mail footer settings and the custom fonts a bundle carries, and the logger an import writes to.
 *
 * @spec openspec/specs/config-portability/spec.md
 */
final class ConfigBundleBranding {

	/**
	 * Constructor.
	 *
	 * @param EmailThemingService $emailThemingService The email theming footer service.
	 * @param FontService $fontService The custom font metadata service.
	 * @param LoggerInterface $logger The logger.
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 */
	public function __construct(
		public readonly EmailThemingService $emailThemingService,
		public readonly FontService $fontService,
		public readonly LoggerInterface $logger,
	) {
	}//end __construct()
}//end class
