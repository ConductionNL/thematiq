<?php

/**
 * The `--nldesign-org-` prefix belongs to administrators' own tokens: no shipped stylesheet
 * may declare or read such a name, so a release can never collide with one.
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
 * @spec openspec/changes/authoring-token-lifecycle/tasks.md#task-2.4
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit;

use OCA\Thematiq\Service\OwnTokenService;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Scans `css/` for the reserved prefix.
 *
 * @spec openspec/changes/authoring-token-lifecycle/tasks.md#task-2.4
 */
final class OrgTokenPrefixReservedTest extends TestCase {

	/**
	 * The stylesheets under a directory that use the reserved prefix, the overrides files excepted.
	 *
	 * @param string $dir The directory.
	 *
	 * @return array<int, string> Relative paths.
	 */
	private function offenders(string $dir): array {
		$found = [];
		$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS));
		foreach ($files as $file) {
			$name = $file->getFilename();
			if (str_ends_with($name, '.css') === false || str_starts_with($name, 'custom-overrides') === true) {
				continue;
			}

			if (str_contains((string)file_get_contents($file->getPathname()), OwnTokenService::PREFIX) === true) {
				$found[] = substr($file->getPathname(), strlen($dir) + 1);
			}
		}

		return $found;
	}//end offenders()

	/**
	 * No shipped stylesheet uses the prefix.
	 *
	 * @return void
	 */
	public function testNoShippedStylesheetUsesThePrefix(): void {
		$this->assertSame([], $this->offenders(dir: \dirname(__DIR__, 2) . '/css'));
	}//end testNoShippedStylesheetUsesThePrefix()

	/**
	 * The scan finds a declaration and a read in a fixture, and skips the overrides file.
	 *
	 * @return void
	 */
	public function testTheScanFindsOne(): void {
		$dir = sys_get_temp_dir() . '/thematiq-org-prefix-' . bin2hex(random_bytes(4));
		mkdir($dir . '/tokens', 0777, true);
		file_put_contents($dir . '/tokens/x.css', ':root { --nldesign-org-accent: red; }');
		file_put_contents($dir . '/theme.css', 'a { color: var(--nldesign-org-accent); }');
		file_put_contents($dir . '/custom-overrides.css', ':root { --nldesign-org-accent: red; }');

		$found = $this->offenders(dir: $dir);
		sort($found);
		exec('rm -rf ' . escapeshellarg($dir));

		$this->assertSame(['theme.css', 'tokens/x.css'], $found);
	}//end testTheScanFindsOne()
}//end class
