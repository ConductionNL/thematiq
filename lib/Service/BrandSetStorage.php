<?php

/**
 * The collaborators that store a converted brand as a custom token set.
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
 * @spec openspec/specs/multi-brand-token-sources/spec.md#requirement-a-brand-is-a-custom-token-set-linked-to-its-source
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * Custom set storage, and the contrast warnings and dark variants an updated set gets.
 *
 * @spec openspec/specs/multi-brand-token-sources/spec.md#requirement-a-brand-is-a-custom-token-set-linked-to-its-source
 */
final class BrandSetStorage {

	/**
	 * Constructor.
	 *
	 * @param CustomTokenSetService $customSets Stores and replaces sets.
	 * @param ContrastService $contrast Contrast warnings on update.
	 * @param DarkPaletteService $darkPalette Dark variants on update.
	 *
	 * @spec openspec/specs/multi-brand-token-sources/spec.md#requirement-a-brand-is-a-custom-token-set-linked-to-its-source
	 */
	public function __construct(
		public readonly CustomTokenSetService $customSets,
		public readonly ContrastService $contrast,
		public readonly DarkPaletteService $darkPalette,
	) {
	}//end __construct()
}//end class
