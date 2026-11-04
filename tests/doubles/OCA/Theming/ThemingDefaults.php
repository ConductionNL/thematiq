<?php

/**
 * Test double for the server theming app's `OCA\Theming\ThemingDefaults`.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Loaded by tests/bootstrap.php only when no Nextcloud server is booted, so
 * tests that mock this class run standalone. It declares the members thematiq
 * uses, with the signatures of apps/theming on stable32 to stable35;
 * tests/Server/Theming/ThemingDoublesParityTest checks them against the real
 * class in the Nextcloud CI job. The bodies refuse: a test mocks this class.
 */

declare(strict_types=1);

namespace OCA\Theming;

/**
 * Declaration double of OCA\Theming\ThemingDefaults.
 */
class ThemingDefaults {
	/**
	 * Store one theming setting.
	 *
	 * @param string $setting The setting key.
	 * @param string $value The new value.
	 *
	 * @return void
	 */
	public function set($setting, $value): void {
		throw new \LogicException('set() is a test double: mock it.');
	}//end set()

	/**
	 * Revert one theming setting and return its default.
	 *
	 * @param string $setting The setting to revert.
	 *
	 * @return string
	 */
	public function undo($setting): string {
		throw new \LogicException('undo() is a test double: mock it.');
	}//end undo()
}//end class
