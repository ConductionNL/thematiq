<?php

/**
 * Tests for AppFilePath.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Tests
 * @package   OCA\Thematiq
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 *
 * @spec openspec/specs/runtime-file-storage/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service\RuntimeFile;

use OCA\Thematiq\Service\RuntimeFile\AppFilePath;
use PHPUnit\Framework\TestCase;

/**
 * A plain relative name gets a path under the directory; anything that could leave it gets none.
 *
 * @spec openspec/specs/runtime-file-storage/spec.md
 */
final class AppFilePathTest extends TestCase {

	/**
	 * The names the app reads and writes join the directory as they are.
	 *
	 * @return void
	 */
	public function testAnAppRelativeNameJoinsTheDirectory(): void {
		$paths = new AppFilePath();

		$this->assertSame(expected: '/app/css/tokens/utrecht.css', actual: $paths->join(root: '/app/', name: 'css/tokens/utrecht.css'));
		$this->assertSame(expected: '/app/css/tokens/dark/custom-a1.css', actual: $paths->join(root: '/app', name: '/css/tokens/dark/custom-a1.css'));
		$this->assertSame(expected: '/app/img/logos/Gemeente_Logo.v2.svg', actual: $paths->join(root: '/app', name: 'img/logos/Gemeente_Logo.v2.svg'));
	}//end testAnAppRelativeNameJoinsTheDirectory()

	/**
	 * A name that climbs out, names a stream or carries other characters gets no path.
	 *
	 * @return void
	 */
	public function testANameThatCouldLeaveTheDirectoryGetsNoPath(): void {
		$paths = new AppFilePath();

		foreach (['../config/config.php', 'css/../../config.php', 'css/tokens/..', 'php://filter/resource=x', 'css/tokens/a b.css', "css/tokens/a\0.css", ''] as $name) {
			$this->assertNull(actual: $paths->join(root: '/app', name: $name), message: $name);
		}
	}//end testANameThatCouldLeaveTheDirectoryGetsNoPath()
}//end class
