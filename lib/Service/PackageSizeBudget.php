<?php

/**
 * Thematiq Package Size Budget.
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
 * @spec openspec/specs/theme-as-code/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;


use RuntimeException;

/**
 * The size limits of one package being read: each file at most 4 MB, the
 * package at most 64 MB. Built by BrandingPackageReader for each read.
 *
 * @spec openspec/specs/theme-as-code/spec.md
 */
final class PackageSizeBudget {

	/**
	 * The size of the files admitted so far.
	 *
	 * @var integer
	 */
	private int $total = 0;

	/**
	 * Admit one more file, refusing a file or a package over the limits.
	 *
	 * @param string $name The relative path, for the message.
	 * @param int $size The file size.
	 *
	 * @return void
	 *
	 * @throws RuntimeException When a limit is exceeded.
	 *
	 * @spec openspec/specs/theme-as-code/spec.md
	 */
	public function admit(string $name, int $size): void {
		$this->total += $size;
		if ($size > BrandingPackageReader::MAX_FILE_BYTES) {
			throw new RuntimeException('The package file ' . $name . ' is larger than 4 MB.');
		}

		if ($this->total > BrandingPackageReader::MAX_TOTAL_BYTES) {
			throw new RuntimeException('The branding package is larger than 64 MB.');
		}
	}//end admit()
}//end class
