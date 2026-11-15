<?php

/**
 * The collaborators the custom set endpoints read other sets and branding from.
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
 * @spec openspec/specs/custom-token-sets/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * The design system manifest, the optional branding capture for a theme saved from the editor, and the optional
 * multi-brand import.
 *
 * @spec openspec/specs/custom-token-sets/spec.md
 */
final class CustomSetSources {

	/**
	 * Constructor.
	 *
	 * @param DesignSystemService $designSystems The design-system manifest, for allow-listing a claimed id.
	 * @param BrandingCaptureService|null $brandingCapture Copies Nextcloud's branding into a theme saved from the editor.
	 * @param MultiBrandImportService|null $multiBrand Lists and imports the brands of a multi-brand source.
	 *
	 * @spec openspec/specs/custom-token-sets/spec.md
	 */
	public function __construct(
		public readonly DesignSystemService $designSystems,
		public readonly ?BrandingCaptureService $brandingCapture = null,
		public readonly ?MultiBrandImportService $multiBrand = null,
	) {
	}//end __construct()
}//end class
