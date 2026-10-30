<?php

/**
 * What REUSE.toml says about a file of this repository.
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
 * @spec openspec/specs/shell-catalogue/spec.md#requirement-a-logo-is-redistributable-only-when-the-repository-says-so
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * Reads the licence annotations of `REUSE.toml`, for the shell catalogue.
 *
 * It tells a licence an annotation gives a file by name (`override`) from the
 * repository default, because only the first is a statement about that file.
 *
 * @spec openspec/specs/shell-catalogue/spec.md#requirement-a-logo-is-redistributable-only-when-the-repository-says-so
 */
class ReuseLicences {

	/**
	 * A file's content, or the empty string when it is not there.
	 *
	 * @param string $path The absolute path.
	 *
	 * @return string The content.
	 *
	 * @spec openspec/specs/shell-catalogue/spec.md#requirement-a-logo-is-redistributable-only-when-the-repository-says-so
	 */
	public function read(string $path): string {
		if (is_file($path) === false) {
			return '';
		}

		return (string)file_get_contents($path);
	}//end read()

	/**
	 * The licence `REUSE.toml` gives a file.
	 *
	 * An `override` annotation that names the file wins. Without one the
	 * repository default applies, which says no more than "nobody claimed
	 * this file".
	 *
	 * @param string $file The repository-relative path.
	 * @param array<int, array{paths: string[], licence: string, override: bool}> $annotations The annotations.
	 *
	 * @return array{licence: string|null, stated: bool} The SPDX identifier, and whether an override names the file.
	 *
	 * @spec openspec/specs/shell-catalogue/spec.md#requirement-a-logo-is-redistributable-only-when-the-repository-says-so
	 */
	public function licenceOf(string $file, array $annotations): array {
		$default = null;
		foreach ($annotations as $annotation) {
			foreach ($annotation['paths'] as $path) {
				$pattern = '#^' . str_replace(['\*\*', '\*'], ['.*', '[^/]*'], preg_quote($path, '#')) . '$#';
				if (preg_match($pattern, $file) !== 1) {
					continue;
				}

				if ($annotation['override'] === true) {
					return ['licence' => $annotation['licence'], 'stated' => true];
				}

				$default = $annotation['licence'];
			}
		}

		return ['licence' => $default, 'stated' => false];
	}//end licenceOf()

	/**
	 * The annotations of `REUSE.toml`: their paths, licence and precedence.
	 *
	 * Read with patterns, not a TOML parser: the file is ours, and its
	 * annotations all have the three keys read here.
	 *
	 * @param string $appPath The app root path.
	 *
	 * @return array<int, array{paths: string[], licence: string, override: bool}> The annotations, in file order.
	 *
	 * @spec openspec/specs/shell-catalogue/spec.md#requirement-a-logo-is-redistributable-only-when-the-repository-says-so
	 */
	public function annotations(string $appPath): array {
		$toml = (string)preg_replace('/^\s*#.*$/m', '', $this->read(path: $appPath . '/REUSE.toml'));

		$annotations = [];
		foreach (array_slice(explode('[[annotations]]', $toml), 1) as $block) {
			if (preg_match('/^SPDX-License-Identifier\s*=\s*"([^"]+)"/m', $block, $licence) !== 1
				|| preg_match('/^path\s*=\s*(\[[^\]]*\]|"[^"]*")/m', $block, $paths) !== 1
			) {
				continue;
			}

			preg_match_all('/"([^"]+)"/', $paths[1], $names);
			$annotations[] = [
				'paths' => $names[1],
				'licence' => $licence[1],
				'override' => (preg_match('/^precedence\s*=\s*"override"/m', $block) === 1),
			];
		}

		return $annotations;
	}//end reuseAnnotations()
}//end class
