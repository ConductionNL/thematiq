<?php

/**
 * The state of writing one Tokens Studio file.
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
 * @spec openspec/specs/figma-token-files/spec.md#requirement-any-token-set-can-be-downloaded-for-figma
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * One run of the Tokens Studio part of {@see FigmaTokenWriter}: the set's
 * declarations, the keys the file format uses, and the tree it builds up.
 *
 * @spec openspec/specs/figma-token-files/spec.md#requirement-any-token-set-can-be-downloaded-for-figma
 */
final class StudioWriteRun {

	/**
	 * The Tokens Studio tree, filled token by token.
	 *
	 * @var array<string, mixed>
	 */
	public array $tree = [];

	/**
	 * Constructor.
	 *
	 * @param array<string, string> $declarations The set's own declarations, name to CSS value.
	 * @param string $valueKey The key that holds a token's value, and marks a token.
	 * @param string $typeKey The key that holds a token's type.
	 */
	public function __construct(
		public readonly array $declarations,
		public readonly string $valueKey,
		public readonly string $typeKey,
	) {
	}//end __construct()
}//end class
