<?php

/**
 * One block of the OpenProject plugin's set file: its roles and its vocabulary.
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
 * The light block or the dark block of a set file, with its place in the file.
 *
 * @spec openspec/specs/openproject-set-file/spec.md#requirement-the-plugins-set-file-becomes-a-custom-token-set
 */
final class SetFileBlock {

	/**
	 * Constructor.
	 *
	 * @param string $path The block's place in the file, for the report: empty, or `dark.`.
	 * @param array<int|string, mixed> $roles Role => value.
	 * @param array<int|string, mixed> $vocabulary Key without the prefix => value.
	 */
	public function __construct(
		public readonly string $path,
		public readonly array $roles,
		public readonly array $vocabulary,
	) {
	}//end __construct()
}//end class
