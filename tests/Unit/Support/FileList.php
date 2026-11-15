<?php

/**
 * Directory listings for tests, empty when the listing fails.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Support;

/**
 * glob() and scandir() return false on failure; a test iterates over nothing instead.
 */
final class FileList {
	/**
	 * The paths matching a glob pattern.
	 *
	 * @param string $pattern The glob pattern.
	 *
	 * @return array<int, string>
	 */
	public static function glob(string $pattern): array {
		$found = glob($pattern);

		return match ($found) {
			false => [],
			default => $found,
		};
	}//end glob()

	/**
	 * The entries of a directory, `.` and `..` included, as scandir() lists them.
	 *
	 * @param string $directory The directory.
	 *
	 * @return array<int, string>
	 */
	public static function entries(string $directory): array {
		$found = scandir($directory);

		return match ($found) {
			false => [],
			default => $found,
		};
	}//end entries()
}//end class
