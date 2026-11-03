<?php

/**
 * The collaborators the token set list runs with.
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

use OCP\ICacheFactory;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

/**
 * The app config, the factory for the WCAG level and warnings caches, and the logger.
 *
 * @spec openspec/specs/token-sets/spec.md
 */
final class TokenSetRuntime {

	/**
	 * Constructor.
	 *
	 * @param IConfig $config The config service.
	 * @param ICacheFactory $cacheFactory Creates the WCAG-level and warnings caches.
	 * @param LoggerInterface $logger The logger.
	 *
	 * @spec openspec/specs/token-sets/spec.md
	 */
	public function __construct(
		public readonly IConfig $config,
		public readonly ICacheFactory $cacheFactory,
		public readonly LoggerInterface $logger,
	) {
	}//end __construct()
}//end class
