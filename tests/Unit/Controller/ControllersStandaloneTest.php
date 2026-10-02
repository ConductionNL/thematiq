<?php

/**
 * Regression guard: no controller names another app's class in code.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/adopt-apphost-2026-06-16/tasks.md#task-3
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Controller;

use PHPUnit\Framework\TestCase;

/**
 * Thematiq must serve its routes on an instance without OpenRegister
 * (thematiq#207).
 *
 * Nextcloud's router reflects every registered controller while MATCHING a
 * route, so a controller that extends, implements, imports or type-hints a
 * class from another app takes down EVERY Thematiq route when that app is
 * absent, not only its own. The autoloader resolves those names, not the DI
 * container, so lazy registration cannot rescue them.
 *
 * CI installs OpenRegister into its E2E instance, so the E2E job cannot see
 * this regression. This test reads the controller sources instead, which
 * needs no second app at all. Another app's class may still be named as a
 * STRING (the FQCN constants HealthController resolves out of the container
 * at dispatch time), because a string is never autoloaded.
 */
class ControllersStandaloneTest extends TestCase {

	/**
	 * Every class name a controller uses in code, outside comments and strings.
	 *
	 * @param string $file The controller file.
	 *
	 * @return array<int, string> The names, as written.
	 */
	private function namesIn(string $file): array {
		$names = [];
		foreach (token_get_all((string)file_get_contents($file)) as $token) {
			if (is_array($token) === false) {
				continue;
			}

			if (in_array($token[0], [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true) === true) {
				$names[] = ltrim($token[1], '\\');
			}
		}

		return $names;
	}//end namesIn()

	/**
	 * Every name under `OCA\` belongs to this app.
	 *
	 * @return void
	 */
	public function testNoControllerNamesAnotherAppsClass(): void {
		$files = glob(dirname(__DIR__, 3) . '/lib/Controller/*.php');
		$this->assertNotEmpty($files, 'the controller directory was found');

		$foreign = [];
		foreach ($files as $file) {
			foreach ($this->namesIn(file: $file) as $name) {
				if (str_starts_with($name, 'OCA\\') === true && str_starts_with($name, 'OCA\\Thematiq\\') === false) {
					$foreign[] = basename($file) . ': ' . $name;
				}
			}
		}

		$this->assertSame([], $foreign, 'a controller names another app\'s class, so every route 500s without that app');
	}//end testNoControllerNamesAnotherAppsClass()
}//end class
