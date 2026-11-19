<?php

/**
 * One requested switch of the active token set.
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
 * @spec openspec/specs/theme-cli/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Command;

/**
 * The active token set a `thematiq:theme:set` run starts from and the one it asks for.
 *
 * @spec openspec/specs/theme-cli/spec.md
 */
final class ThemeSwitch {

	/**
	 * The token set that is active now.
	 *
	 * @var string
	 */
	public readonly string $from;

	/**
	 * The token set asked for.
	 *
	 * @var string
	 */
	public readonly string $to;

	/**
	 * Constructor.
	 *
	 * @param string $from The token set that is active now.
	 * @param string $to The token set asked for.
	 */
	public function __construct(string $from, string $to) {
		$this->from = $from;
		$this->to = $to;
	}//end __construct()

	/**
	 * Whether the asked-for set is already active.
	 *
	 * @return bool True when nothing would change.
	 *
	 * @spec openspec/specs/theme-cli/spec.md
	 */
	public function isNoOp(): bool {
		return $this->from === $this->to;
	}//end isNoOp()
}//end class
