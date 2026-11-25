<?php

/**
 * The path of a file under a directory, from a name that cannot leave it.
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
 * @spec openspec/specs/runtime-file-storage/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service\RuntimeFile;

/**
 * Joins a directory and an app-relative name, such as `css/tokens/utrecht.css`.
 *
 * Every file the app reads or writes by name goes through here. A name with
 * `..`, a scheme such as `php://` or any character outside letters, digits,
 * `.`, `_`, `/` and `-` gets no path, so no name reaches a file outside the
 * directory or a stream wrapper.
 *
 * @spec openspec/specs/runtime-file-storage/spec.md
 */
final class AppFilePath {

	/**
	 * A relative name: safe characters only, no `..` anywhere.
	 *
	 * @var string
	 */
	private const NAME = '#^(?!.*\.\.)[A-Za-z0-9._-][A-Za-z0-9._/-]*$#D';

	/**
	 * The path of a name under a directory, or null when the name is not a plain relative name.
	 *
	 * @param string $root The directory.
	 * @param string $name The app-relative name; a leading `/` is ignored.
	 *
	 * @return string|null The absolute path.
	 *
	 * @psalm-taint-escape file
	 * @psalm-taint-escape ssrf
	 * @psalm-taint-escape url_component
	 * @psalm-taint-escape url_path
	 *
	 * @spec openspec/specs/runtime-file-storage/spec.md
	 */
	public function join(string $root, string $name): ?string {
		$name = ltrim($name, '/');
		if (preg_match(self::NAME, $name) !== 1) {
			return null;
		}

		return rtrim($root, '/') . '/' . $name;
	}//end join()
}//end class
