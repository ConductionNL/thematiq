<?php

/**
 * The collaborators the settings endpoints list, preview and switch token sets through.
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

/**
 * The token set list, the preview of a set's tokens, the one write path for the active set, and Nextcloud's core theming
 * that a switch syncs.
 *
 * @spec openspec/specs/token-sets/spec.md
 */
final class SettingsTokenSetServices {

	/**
	 * Constructor.
	 *
	 * @param TokenSetService $tokenSetService The token set service.
	 * @param TokenSetPreviewService $previewService The token set preview service.
	 * @param ActiveTokenSetService $activeTokenSet The token set write path.
	 * @param ThemingService $themingService The theming service.
	 *
	 * @spec openspec/specs/token-sets/spec.md
	 */
	public function __construct(
		public readonly TokenSetService $tokenSetService,
		public readonly TokenSetPreviewService $previewService,
		public readonly ActiveTokenSetService $activeTokenSet,
		public readonly ThemingService $themingService,
	) {
	}//end __construct()
}//end class
