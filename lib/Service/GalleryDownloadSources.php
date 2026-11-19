<?php

/**
 * The collaborators that fetch a gallery theme.
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

use OCP\Http\Client\IClientService;

/**
 * The gallery index and the HTTP client a theme file is downloaded with.
 *
 * @spec openspec/specs/theme-gallery/spec.md
 */
final class GalleryDownloadSources {

	/**
	 * Constructor.
	 *
	 * @param ThemeGalleryService $gallery The index.
	 * @param IClientService $clientService The HTTP client service.
	 *
	 * @spec openspec/specs/theme-gallery/spec.md
	 */
	public function __construct(
		public readonly ThemeGalleryService $gallery,
		public readonly IClientService $clientService,
	) {
	}//end __construct()
}//end class
