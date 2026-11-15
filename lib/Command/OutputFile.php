<?php

/**
 * A file a command writes its output to.
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
 * Where a command's rendered output goes and what it is. An empty path
 * means the console instead of a file.
 *
 * @spec openspec/specs/config-portability/spec.md
 */
final class OutputFile {

	/**
	 * The destination path; empty for the console.
	 *
	 * @var string
	 */
	public readonly string $path;

	/**
	 * The rendered output.
	 *
	 * @var string
	 */
	public readonly string $content;

	/**
	 * Constructor.
	 *
	 * @param string $path The destination path; empty for the console.
	 * @param string $content The rendered output.
	 */
	public function __construct(string $path, string $content) {
		$this->path = $path;
		$this->content = $content;
	}//end __construct()

	/**
	 * Whether the output goes to a file rather than the console.
	 *
	 * @return bool True when a path was given.
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 */
	public function toFile(): bool {
		return $this->path !== '';
	}//end toFile()
}//end class
