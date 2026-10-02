<?php

/**
 * The administrator's own tokens as they go into an overrides file.
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
 * @spec openspec/changes/authoring-token-lifecycle/tasks.md#task-2.2
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * Light values, dark values and the deprecation comment above each deprecated token.
 *
 * @spec openspec/changes/authoring-token-lifecycle/tasks.md#task-2.2
 */
class OwnTokenCss {

	/**
	 * Constructor.
	 *
	 * @param array<string, string> $light    Token name => light value.
	 * @param array<string, string> $dark     Token name => dark value.
	 * @param array<string, string> $comments Token name => the deprecation notice written above it.
	 */
	public function __construct(
		private readonly array $light = [],
		private readonly array $dark = [],
		private readonly array $comments = [],
	) {
	}//end __construct()

	/**
	 * The `:root` lines, each deprecated token preceded by its notice.
	 *
	 * @return array<string> CSS lines.
	 *
	 * @SuppressWarnings(PHPMD.StaticAccess) - the declaration writer is a pure function shared with the editor overrides
	 *
	 * @spec openspec/changes/authoring-token-lifecycle/tasks.md#task-3.3
	 */
	public function lightLines(): array {
		$lines = [];
		foreach ($this->light as $name => $value) {
			$declaration = OverridesCssBuilder::declarationLines(tokens: [$name => $value], important: false);
			if (isset($this->comments[$name]) === true && $declaration !== []) {
				$lines[] = '  /* ' . str_replace(['/*', '*/'], '', $this->comments[$name]) . ' */';
			}

			$lines = array_merge($lines, $declaration);
		}

		return $lines;
	}//end lightLines()

	/**
	 * The dark-scope lines.
	 *
	 * @return array<string> CSS lines.
	 *
	 * @SuppressWarnings(PHPMD.StaticAccess) - the declaration writer is a pure function shared with the editor overrides
	 *
	 * @spec openspec/changes/authoring-token-lifecycle/tasks.md#task-2.2
	 */
	public function darkLines(): array {
		return OverridesCssBuilder::declarationLines(tokens: $this->dark, important: false);
	}//end darkLines()
}//end class
