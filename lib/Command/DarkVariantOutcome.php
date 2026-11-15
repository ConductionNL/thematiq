<?php

/**
 * The outcome of generating one dark variant.
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
 * @spec openspec/specs/dark-mode/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Command;

/**
 * One token set's result from `thematiq:generate-dark-variants`.
 *
 * @spec openspec/specs/dark-mode/spec.md
 */
final class DarkVariantOutcome {

	/**
	 * The token set id.
	 *
	 * @var string
	 */
	public readonly string $setId;

	/**
	 * The generator's result: `written`, `reason` and `warnings`.
	 *
	 * @var array<string, mixed>
	 */
	public readonly array $result;

	/**
	 * Constructor.
	 *
	 * @param string $setId The token set id.
	 * @param array<string, mixed> $result The generator's result.
	 */
	public function __construct(string $setId, array $result) {
		$this->setId = $setId;
		$this->result = $result;
	}//end __construct()

	/**
	 * Whether the file could not be written.
	 *
	 * @return bool True for a failed write.
	 *
	 * @spec openspec/specs/dark-mode/spec.md
	 */
	public function writeFailed(): bool {
		return $this->result['reason'] === 'write-failed';
	}//end writeFailed()
}//end class
