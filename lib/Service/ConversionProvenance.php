<?php

/**
 * Where a converted token set came from, as its provenance comment records it.
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
 * @spec openspec/changes/nlds-theme-converter/specs/token-set-converter/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * The provenance of one conversion: the set and its source, the detected input
 * kind and the tally of the report.
 *
 * @spec openspec/changes/nlds-theme-converter/specs/token-set-converter/spec.md
 *
 * @phpstan-import-type SetSource from TokenSetConverterService
 * @psalm-import-type   SetSource from TokenSetConverterService
 */
final class ConversionProvenance {

	/**
	 * Constructor.
	 *
	 * @param SetSource $source The set slug and display name, the source label and the source version.
	 * @param string $inputKind The detected input kind.
	 * @param array<string, int> $counts The action tally.
	 */
	public function __construct(
		public readonly array $source,
		public readonly string $inputKind,
		public readonly array $counts,
	) {
	}//end __construct()
}//end class
