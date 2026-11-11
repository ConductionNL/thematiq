<?php

/**
 * One restore of a kept configuration version.
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
 * @spec openspec/specs/theme-versions/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Command;

/**
 * The version a `thematiq:config:restore` run restores and whether it only previews.
 *
 * @spec openspec/specs/theme-versions/spec.md
 */
final class RestoreRequest {

	/**
	 * The version id.
	 *
	 * @var string
	 */
	public readonly string $id;

	/**
	 * Whether the run only previews the changes.
	 *
	 * @var bool
	 */
	public readonly bool $dryRun;

	/**
	 * Constructor.
	 *
	 * @param string $id The version id.
	 * @param bool $dryRun Whether the run only previews the changes.
	 */
	public function __construct(string $id, bool $dryRun) {
		$this->id = $id;
		$this->dryRun = $dryRun;
	}//end __construct()
}//end class
