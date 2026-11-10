<?php

/**
 * One font family as the shell catalogue sees it.
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
 * @spec openspec/specs/shell-catalogue/spec.md#requirement-a-font-is-redistributable-only-under-a-stated-licence
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * A font family the shipped sets name: which sets, the files thematiq ships
 * for it, and the one licence `REUSE.toml` names for all of those files.
 *
 * @spec openspec/specs/shell-catalogue/spec.md#requirement-a-font-is-redistributable-only-under-a-stated-licence
 */
final class CatalogueFont {

	/**
	 * Constructor.
	 *
	 * @param string $family The family name.
	 * @param string[] $sets The ids of the sets that name it.
	 * @param string[] $files The font files thematiq ships for it.
	 * @param string|null $licence The SPDX identifier, or null when the files have no one licence of their own.
	 */
	public function __construct(
		public readonly string $family,
		public readonly array $sets,
		public readonly array $files,
		public readonly ?string $licence,
	) {
	}//end __construct()
}//end class
