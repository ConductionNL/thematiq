<?php

/**
 * Test double for the server theming app's `OCA\Theming\Service\BackgroundService`.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Loaded by tests/bootstrap.php only when no Nextcloud server is booted, so
 * tests that mock this class run standalone. It declares the constants thematiq
 * uses, with the signatures of apps/theming on stable32 to stable35;
 * tests/Server/Theming/ThemingDoublesParityTest checks them against the real
 * class in the Nextcloud CI job.
 */

declare(strict_types=1);

namespace OCA\Theming\Service;

/**
 * Declaration double of OCA\Theming\Service\BackgroundService.
 */
class BackgroundService {
	/**
	 * The default primary colour.
	 */
	public const DEFAULT_COLOR = '#00679e';

	/**
	 * The default background colour.
	 */
	public const DEFAULT_BACKGROUND_COLOR = '#00679e';
}//end class
