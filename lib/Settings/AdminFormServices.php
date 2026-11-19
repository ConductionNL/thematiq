<?php

/**
 * The services the admin settings form reads its state from.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Settings
 * @package   OCA\Thematiq
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 *
 * @spec openspec/specs/admin-settings/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Settings;

use OCA\Thematiq\Service\DesignSystemService;
use OCA\Thematiq\Service\EmailThemingService;
use OCA\Thematiq\Service\ThemePreviewService;
use OCA\Thematiq\Service\TokenSetService;

/**
 * The services the admin settings form reads: the token sets, the e-mail theming, the
 * active preview and the design systems.
 *
 * @spec openspec/specs/admin-settings/spec.md
 */
final class AdminFormServices {

	/**
	 * The form's read services.
	 *
	 * @param TokenSetService $tokenSets The token set service.
	 * @param EmailThemingService $emailTheming The e-mail theming service.
	 * @param ThemePreviewService $previews The theme preview service.
	 * @param DesignSystemService $designSystems Resolves the active icon pack and the documentation links.
	 */
	public function __construct(
		public readonly TokenSetService $tokenSets,
		public readonly EmailThemingService $emailTheming,
		public readonly ThemePreviewService $previews,
		public readonly DesignSystemService $designSystems,
	) {
	}//end __construct()
}//end class
