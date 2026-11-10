<?php

/**
 * What the repository holds that the shell catalogue judges a logo or a font by.
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
 * @spec openspec/specs/shell-catalogue/spec.md#requirement-every-shipped-set-is-in-the-catalogue
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * The repository evidence the shell catalogue reads: where the app lives, the
 * entries of `token-sets.json` and the annotations of `REUSE.toml`.
 *
 * @spec openspec/specs/shell-catalogue/spec.md#requirement-every-shipped-set-is-in-the-catalogue
 */
final class CatalogueEvidence {

	/**
	 * Constructor.
	 *
	 * @param string $appPath The app root path.
	 * @param array<string, array<string, mixed>> $manifest The manifest entries, by id.
	 * @param array<int, array{paths: string[], licence: string, override: bool}> $annotations The `REUSE.toml` annotations.
	 */
	public function __construct(
		public readonly string $appPath,
		public readonly array $manifest,
		public readonly array $annotations,
	) {
	}//end __construct()

	/**
	 * A set's manifest entry, or nothing when `token-sets.json` has none.
	 *
	 * @param string $id The set id.
	 *
	 * @return array<string, mixed> The entry.
	 */
	public function metaOf(string $id): array {
		return ($this->manifest[$id] ?? []);
	}//end metaOf()
}//end class
