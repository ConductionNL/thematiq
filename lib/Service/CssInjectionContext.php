<?php

/**
 * The settings, design systems, links and log the stylesheet cascade is injected with.
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
 * @spec openspec/specs/css-architecture/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCP\IConfig;
use OCP\IURLGenerator;
use Psr\Log\LoggerInterface;

/**
 * What every layer of the stylesheet cascade reads: the app config, the design systems, the URL generator and the log.
 *
 * @spec openspec/specs/css-architecture/spec.md
 */
final class CssInjectionContext {

	/**
	 * Constructor.
	 *
	 * @param IConfig $config The config service.
	 * @param DesignSystemService $designSystemService The design system resolver.
	 * @param IURLGenerator $urlGenerator The URL generator.
	 * @param LoggerInterface $logger The logger for skipped layers.
	 *
	 * @spec openspec/specs/css-architecture/spec.md
	 */
	public function __construct(
		public readonly IConfig $config,
		public readonly DesignSystemService $designSystemService,
		public readonly IURLGenerator $urlGenerator,
		public readonly LoggerInterface $logger,
	) {
	}//end __construct()
}//end class
