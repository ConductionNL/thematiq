<?php

/**
 * Thematiq Package Content.
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


/**
 * What a branding package directory holds: the bundle and the font files.
 *
 * @spec openspec/specs/theme-as-code/spec.md
 */
final class PackageContent {

	/**
	 * Constructor.
	 *
	 * @param array<string, mixed> $bundle The bundle to write as bundle.json.
	 * @param array<string, string> $fonts Font bytes keyed by font id.
	 */
	public function __construct(
		public readonly array $bundle,
		public readonly array $fonts,
	) {
	}//end __construct()
}//end class
