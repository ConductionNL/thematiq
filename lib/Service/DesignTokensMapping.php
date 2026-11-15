<?php

/**
 * The outcome of one DesignTokensMapper::map() run, collected leaf by leaf.
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
 * @spec openspec/specs/custom-token-sets/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * The declarations, skips, errors and warnings one map() run has produced so far.
 *
 * @spec openspec/specs/custom-token-sets/spec.md
 */
final class DesignTokensMapping {

	/**
	 * Target custom property => serialized value; the first token for a target wins.
	 *
	 * @var array<string, string>
	 */
	public array $declarations = [];

	/**
	 * Tokens left out on purpose (no target, or a target already taken).
	 *
	 * @var array<int, array<string, string>>
	 */
	public array $skipped = [];

	/**
	 * Tokens that could not be read.
	 *
	 * @var array<int, array<string, string>>
	 */
	public array $errors = [];

	/**
	 * Deprecation notices of imported tokens.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	public array $warnings = [];
}//end class
