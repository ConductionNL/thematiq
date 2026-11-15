<?php

/**
 * The collaborators the overrides endpoints keep Nextcloud theming in step with.
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
 * @spec openspec/specs/custom-css-overrides/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCP\IConfig;

/**
 * The app config, Nextcloud's core theming, and the optional branding capture that copies Nextcloud's branding into the theme on save.
 *
 * @spec openspec/specs/custom-css-overrides/spec.md
 */
final class OverridesThemingSync {

	/**
	 * Constructor.
	 *
	 * @param IConfig $config The app config.
	 * @param ThemingService $themingService The theming service.
	 * @param BrandingCaptureService|null $brandingCapture Copies Nextcloud's branding into the theme on save.
	 *
	 * @spec openspec/specs/custom-css-overrides/spec.md
	 */
	public function __construct(
		public readonly IConfig $config,
		public readonly ThemingService $themingService,
		public readonly ?BrandingCaptureService $brandingCapture = null,
	) {
	}//end __construct()
}//end class
