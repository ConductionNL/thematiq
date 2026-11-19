<?php

/**
 * The collaborators that supply a document's fonts, footer and images.
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
 * @spec openspec/specs/document-house-style/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCP\IURLGenerator;

/**
 * Uploaded fonts by role, the footer settings, the document logo, cover and footer line, and their URLs.
 *
 * @spec openspec/specs/document-house-style/spec.md
 */
final class DocumentBrandAssets {

	/**
	 * Constructor.
	 *
	 * @param FontService $fonts Uploaded fonts by role.
	 * @param EmailThemingService $emailTheming The footer settings.
	 * @param DocumentAssetService $assets The document logo, cover and footer line.
	 * @param IURLGenerator $urls URLs.
	 *
	 * @spec openspec/specs/document-house-style/spec.md
	 */
	public function __construct(
		public readonly FontService $fonts,
		public readonly EmailThemingService $emailTheming,
		public readonly DocumentAssetService $assets,
		public readonly IURLGenerator $urls,
	) {
	}//end __construct()
}//end class
