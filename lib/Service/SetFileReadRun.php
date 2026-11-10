<?php

/**
 * The state of reading one OpenProject plugin set file.
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
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
/**
 * One read of a set file: the declarations of the block being read, and what
 * was left out and why.
 *
 * @spec openspec/specs/openproject-set-file/spec.md#requirement-the-plugins-set-file-becomes-a-custom-token-set
 */
final class SetFileReadRun {

	/**
	 * The values kept from the block being read, by CSS name.
	 *
	 * @var array<string, string>
	 */
	public array $declarations = [];

	/**
	 * Constructor.
	 *
	 * @param array<int, array{path: string, reason: string}> $skipped What was left out and why so far; appended to.
	 */
	public function __construct(
		public array $skipped,
	) {
	}//end __construct()
}//end class
