<?php

/**
 * Contract of ContrastService
 *
 * The public surface of ContrastService that other components call, so they depend
 * on this contract instead of the implementation.
 *
 * @category  Kernel
 * @package   OCA\Thematiq\Kernel\Contracts
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT: <git_id>
 * @link      https://github.com/ConductionNL/thematiq
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Thematiq\Kernel\Contracts;

/**
 * Contract of ContrastService.
 *
 * @category Kernel
 * @package  OCA\Thematiq\Kernel\Contracts
 * @author   Conduction b.v. <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://github.com/ConductionNL/thematiq
 *
 * @spec exclude Structural contract, no functional requirement.
 */
interface ContrastServiceInterface {

	/**
	 * Evaluate an arbitrary list of candidate colors against one background,
	 * generalising `check()` beyond its two fixed token pairs.
	 *
	 * Reuses `relativeLuminance()`/`ratio()`/`parseColor()` unchanged — only
	 * the pairing/threshold selection is generalised, so this method can
	 * never disagree with `check()`'s underlying math. Threshold by role:
	 * `text` → 4.5:1, `ui` → 3.0:1. A candidate (or the background) that is
	 * not a parseable literal color is reported `unevaluated: true`,
	 * `ratio: null`, and is NEVER reported as `pass: true` — an unresolvable
	 * value must never be treated as passing. The response never contains a
	 * `blocked`/`allowed`/`verdict` field: this method reports facts only,
	 * the caller decides what to do with them.
	 *
	 * @param array<int, array{name: string, value: string, role: string}> $candidates The candidate colors to evaluate.
	 * @param string $background The background color (hex or rgb()/rgba()).
	 *
	 * One result per candidate, in the given order.
	 *
	 * @return array<int, array{name: string, ratio: float|null, threshold: float, level: string, pass: bool, unevaluated?: bool}>
	 *
	 * @spec openspec/specs/app-token-set-selection/spec.md
	 */
	public function evaluate(array $candidates, string $background): array;
}//end interface
