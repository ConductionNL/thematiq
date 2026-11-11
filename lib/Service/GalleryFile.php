<?php

/**
 * A downloaded and verified gallery file: its content and where it came from.
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
 * The bytes of a gallery entry's file and its URL.
 *
 * @spec openspec/specs/theme-gallery/spec.md
 */
final class GalleryFile {

	/**
	 * Constructor.
	 *
	 * @param string $content The verified content.
	 * @param string $fileUrl The URL it was downloaded from.
	 */
	public function __construct(
		public readonly string $content,
		public readonly string $fileUrl,
	) {
	}//end __construct()
}//end class
