<?php

/**
 * Test double for the server theming app's `OCA\Theming\ImageManager`.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Loaded by tests/bootstrap.php only when no Nextcloud server is booted, so
 * tests that mock this class run standalone. It declares the members thematiq
 * uses, with the signatures of apps/theming on stable32 to stable35;
 * tests/Server/Theming/ThemingDoublesParityTest checks them against the real
 * class in the Nextcloud CI job. The bodies refuse: a test mocks this class.
 */

declare(strict_types=1);

namespace OCA\Theming;

use OCP\Files\SimpleFS\ISimpleFile;

/**
 * Declaration double of OCA\Theming\ImageManager.
 */
class ImageManager {
	/**
	 * The URL of a stored image.
	 *
	 * @param string $key The image key.
	 *
	 * @return string
	 */
	public function getImageUrl(string $key): string {
		throw new \LogicException('getImageUrl() is a test double: mock it.');
	}//end getImageUrl()

	/**
	 * A stored image.
	 *
	 * @param string $key The image key.
	 * @param bool $useSvg Prefer the svg variant.
	 *
	 * @return ISimpleFile
	 */
	public function getImage(string $key, bool $useSvg = true): ISimpleFile {
		throw new \LogicException('getImage() is a test double: mock it.');
	}//end getImage()

	/**
	 * Whether a custom image is stored.
	 *
	 * @param string $key The image key.
	 *
	 * @return bool
	 */
	public function hasImage(string $key): bool {
		throw new \LogicException('hasImage() is a test double: mock it.');
	}//end hasImage()

	/**
	 * Store an uploaded image and return its mime type.
	 *
	 * @param string $key The image key.
	 * @param string $tmpFile Path of the uploaded file.
	 *
	 * @return string
	 */
	public function updateImage(string $key, string $tmpFile): string {
		throw new \LogicException('updateImage() is a test double: mock it.');
	}//end updateImage()
}//end class
