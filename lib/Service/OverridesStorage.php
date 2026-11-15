<?php

/**
 * The collaborators that store and render the custom overrides file.
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
 * @spec openspec/specs/custom-css-overrides/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCA\Thematiq\Service\RuntimeFile\RuntimeFileStore;

/**
 * The runtime store the overrides files live in, the parser that reads their root block, and the dark palette that
 * derives each colour override's dark value.
 *
 * @spec openspec/specs/custom-css-overrides/spec.md
 */
final class OverridesStorage {

	/**
	 * Constructor.
	 *
	 * @param RuntimeFileStore $store Where the overrides files are stored.
	 * @param CssParserService $cssParser CSS parser for root block extraction.
	 * @param DarkPaletteService $darkPalette Derives each colour override's dark value.
	 *
	 * @spec openspec/specs/custom-css-overrides/spec.md
	 */
	public function __construct(
		public readonly RuntimeFileStore $store,
		public readonly CssParserService $cssParser,
		public readonly DarkPaletteService $darkPalette,
	) {
	}//end __construct()
}//end class
