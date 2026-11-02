<?php

/**
 * The collaborators that time and log an apply.
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
 * @spec openspec/specs/theme-as-code/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCP\AppFramework\Utility\ITimeFactory;
use Psr\Log\LoggerInterface;

/**
 * The clock and the logger an apply and its failure write to.
 *
 * @spec openspec/specs/theme-as-code/spec.md
 */
final class ConfigSourceClock {

	/**
	 * Constructor.
	 *
	 * @param ITimeFactory $time The clock.
	 * @param LoggerInterface $logger The logger.
	 *
	 * @spec openspec/specs/theme-as-code/spec.md
	 */
	public function __construct(
		public readonly ITimeFactory $time,
		public readonly LoggerInterface $logger,
	) {
	}//end __construct()
}//end class
