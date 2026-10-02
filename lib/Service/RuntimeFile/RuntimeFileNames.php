<?php

/**
 * The names thematiq may store and serve as runtime files.
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

use InvalidArgumentException;

/**
 * The closed set of names a runtime file may have.
 *
 * A runtime file is one thematiq writes after it was installed: admin
 * overrides, freeform custom CSS, an uploaded token set and its dark variant,
 * and the logos and backgrounds a set carries or captures. Its name is the
 * path it used to have inside the app directory, so code and tests that think
 * in those paths keep working, but it is stored in app data instead.
 *
 * Every name the store accepts and the public route serves passes {@see self::isAllowed()}.
 * It is an ordinary instance rather than static calls so every user can be
 * given one; it has no state.
 * That one check is why a request can never name a file outside the set:
 * there is no `..`, no absolute path and no other directory it will match.
 *
 * @spec openspec/specs/runtime-file-storage/spec.md
 */
final class RuntimeFileNames {

	/**
	 * One segment of an id: lowercase letters, digits and dashes, not starting with a dash.
	 */
	private const ID = '[a-z0-9][a-z0-9-]{0,127}';

	/**
	 * The allowed names, as anchored patterns.
	 *
	 * @var array<int, string>
	 */
	private const PATTERNS = [
		'#^css/custom-overrides(-' . self::ID . ')?\.css$#',
		'#^css/custom-css\.css$#',
		'#^css/tokens/' . self::ID . '\.css$#',
		'#^css/tokens/dark/' . self::ID . '\.css$#',
		'#^img/(logos|backgrounds)/' . self::ID . '\.(svg|png|jpg|gif|webp|ico)$#',
	];

	/**
	 * The content type served for each extension.
	 *
	 * @var array<string, string>
	 */
	private const CONTENT_TYPES = [
		'css' => 'text/css',
		'svg' => 'image/svg+xml',
		'png' => 'image/png',
		'jpg' => 'image/jpeg',
		'gif' => 'image/gif',
		'webp' => 'image/webp',
		'ico' => 'image/x-icon',
	];

	/**
	 * Whether a name is one thematiq writes at runtime.
	 *
	 * @param string $name The app-relative name, such as `css/tokens/example.css`.
	 *
	 * @return bool True when the store may hold it and the route may serve it.
	 *
	 * @spec openspec/specs/runtime-file-storage/spec.md
	 */
	public function isAllowed(string $name): bool {
		foreach (self::PATTERNS as $pattern) {
			if (preg_match($pattern, $name) === 1) {
				return true;
			}
		}

		return false;
	}//end isAllowed()

	/**
	 * Refuse a name outside the set.
	 *
	 * @param string $name The app-relative name.
	 *
	 * @return void
	 *
	 * @throws InvalidArgumentException When the name is not a runtime file name.
	 *
	 * @spec openspec/specs/runtime-file-storage/spec.md
	 */
	public function assertAllowed(string $name): void {
		if ($this->isAllowed(name: $name) === false) {
			throw new InvalidArgumentException('Not a runtime file name: ' . $name);
		}
	}//end assertAllowed()

	/**
	 * The content type to serve a runtime file with.
	 *
	 * @param string $name An allowed name.
	 *
	 * @return string The MIME type.
	 *
	 * @spec openspec/specs/runtime-file-storage/spec.md
	 */
	public function contentType(string $name): string {
		$extension = strtolower((string)pathinfo($name, PATHINFO_EXTENSION));

		return (self::CONTENT_TYPES[$extension] ?? 'application/octet-stream');
	}//end contentType()

	/**
	 * Split a name into its directory and file part.
	 *
	 * @param string $name An allowed name.
	 *
	 * @return array{0: string, 1: string} The directory (`css/tokens/dark`) and the file (`example.css`).
	 *
	 * @spec openspec/specs/runtime-file-storage/spec.md
	 */
	public function split(string $name): array {
		$slash = strrpos($name, '/');

		return [substr($name, 0, (int)$slash), substr($name, ((int)$slash + 1))];
	}//end split()
}//end class
