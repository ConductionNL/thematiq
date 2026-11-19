<?php

/**
 * Shared base of the configuration bundle export and import commands.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Command
 * @package   OCA\Thematiq
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 *
 * @spec openspec/specs/config-portability/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Command;

use OCA\Thematiq\Kernel\Contracts\ConfigBundleServiceInterface;
use OCA\Thematiq\Service\BrandingPackageService;
use Symfony\Component\Console\Command\Command;

/**
 * The bundle and branding package services that `thematiq:config:export`
 * and `thematiq:config:import` both work with.
 *
 * @spec openspec/specs/config-portability/spec.md
 */
abstract class ConfigBundleCommand extends Command {

	/**
	 * The configuration bundle service.
	 *
	 * @var ConfigBundleServiceInterface
	 */
	protected ConfigBundleServiceInterface $service;

	/**
	 * The branding package service, for `--package` on export and a package directory or ZIP on import.
	 *
	 * @var BrandingPackageService
	 */
	protected BrandingPackageService $packages;

	/**
	 * Constructor.
	 *
	 * @param ConfigBundleServiceInterface $service The configuration bundle service.
	 * @param BrandingPackageService $packages The branding package service.
	 */
	public function __construct(ConfigBundleServiceInterface $service, BrandingPackageService $packages) {
		parent::__construct();
		$this->service = $service;
		$this->packages = $packages;
	}//end __construct()
}//end class
