<?php

/**
 * The name a gallery install gives its custom set, and the slug made from it.
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
 * @spec openspec/specs/theme-gallery/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * A custom set's display name and its slug.
 *
 * @spec openspec/specs/theme-gallery/spec.md
 */
final class GallerySetName {

	/**
	 * Constructor.
	 *
	 * @param string $name The display name.
	 * @param string $slug The slug.
	 */
	public function __construct(
		public readonly string $name,
		public readonly string $slug,
	) {
	}//end __construct()
}//end class
