<?php

/**
 * The collaborators that apply a branding package from the configured source.
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

/**
 * The reader that hashes the package, the service that applies it, the bundle export for drift and the audit log.
 *
 * @spec openspec/specs/theme-as-code/spec.md
 */
final class ConfigSourceSteps {

	/**
	 * Constructor.
	 *
	 * @param BrandingPackageReader $reader Hashes the package.
	 * @param BrandingPackageService $packageService Applies the package.
	 * @param ConfigBundleService $bundleService Exports the running configuration, for drift.
	 * @param ThemingAuditService $audit The audit log.
	 *
	 * @spec openspec/specs/theme-as-code/spec.md
	 */
	public function __construct(
		public readonly BrandingPackageReader $reader,
		public readonly BrandingPackageService $packageService,
		public readonly ConfigBundleService $bundleService,
		public readonly ThemingAuditService $audit,
	) {
	}//end __construct()
}//end class
