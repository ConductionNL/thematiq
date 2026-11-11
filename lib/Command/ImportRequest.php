<?php

/**
 * One import of a configuration bundle or branding package.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Command
 * @package   OCA\Thematiq
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 *
 * @spec openspec/specs/config-portability/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Command;

/**
 * The bundle file or package a `thematiq:config:import` run reads and whether it only validates.
 *
 * @spec openspec/specs/config-portability/spec.md
 */
final class ImportRequest {

	/**
	 * The bundle file, or the package directory or ZIP.
	 *
	 * @var string
	 */
	public readonly string $path;

	/**
	 * Whether the run only validates and writes nothing.
	 *
	 * @var bool
	 */
	public readonly bool $dryRun;

	/**
	 * Constructor.
	 *
	 * @param string $path The bundle file, or the package directory or ZIP.
	 * @param bool $dryRun Whether the run only validates and writes nothing.
	 */
	public function __construct(string $path, bool $dryRun) {
		$this->path = $path;
		$this->dryRun = $dryRun;
	}//end __construct()
}//end class
