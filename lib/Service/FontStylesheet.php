<?php

/**
 * A stylesheet the shell catalogue reads font faces from.
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
 * @spec openspec/specs/shell-catalogue/spec.md#requirement-a-font-is-redistributable-only-under-a-stated-licence
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * A stylesheet under `css/`, with the app root its paths are relative to.
 *
 * @spec openspec/specs/shell-catalogue/spec.md#requirement-a-font-is-redistributable-only-under-a-stated-licence
 */
final class FontStylesheet {

	/**
	 * Constructor.
	 *
	 * @param string $appPath The app root path.
	 * @param string $path The stylesheet's absolute path, under the app root.
	 */
	public function __construct(
		public readonly string $appPath,
		public readonly string $path,
	) {
	}//end __construct()

	/**
	 * The stylesheet's directory, relative to the app root.
	 *
	 * @return string The repository-relative directory.
	 */
	public function directory(): string {
		return dirname(substr($this->path, (strlen($this->appPath) + 1)));
	}//end directory()
}//end class
