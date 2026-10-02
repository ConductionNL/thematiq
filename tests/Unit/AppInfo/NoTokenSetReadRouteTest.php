<?php

/**
 * The active token set has no separate admin read route.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Test
 * @package   OCA\Thematiq\Tests\Unit\AppInfo
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\AppInfo;

use OCA\Thematiq\Controller\SettingsController;
use PHPUnit\Framework\TestCase;

/**
 * GET /settings/tokenset had no reader (#664): the admin page gets the active
 * set from initial state and scripts read the public capability. It is gone,
 * and this keeps it gone, together with the POST that the page does use.
 *
 * @spec openspec/specs/token-sets/spec.md#requirement-route-configuration
 */
class NoTokenSetReadRouteTest extends TestCase {

	/**
	 * The routes registered in appinfo/routes.php.
	 *
	 * @return array<int, array{name: string, url: string, verb: string}> The routes.
	 */
	private function routes(): array {
		$routes = require __DIR__ . '/../../../appinfo/routes.php';

		return $routes['routes'];
	}//end routes()

	/**
	 * Only the POST is registered on /settings/tokenset, and the controller has no reader.
	 *
	 * @return void
	 */
	public function testOnlyThePostIsRegistered(): void {
		$verbs = [];
		foreach ($this->routes() as $route) {
			if ($route['url'] === '/settings/tokenset') {
				$verbs[$route['name']] = $route['verb'];
			}
		}

		$this->assertSame(['settings#setTokenSet' => 'POST'], $verbs);
		$this->assertFalse(method_exists(SettingsController::class, 'getTokenSet'));
	}//end testOnlyThePostIsRegistered()
}//end class
