<?php

/**
 * Thematiq brand per app: the logo files.
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
 * @spec openspec/specs/per-app-theming/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCA\Thematiq\Service\Exception\AppBrandException;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Files\SimpleFS\ISimpleFolder;

/**
 * Stores the large and small logo of a branded app in app data
 * `app-brands/<app>-<size>.<ext>`: PNG, JPEG, WebP or SVG up to 1 MB, the type
 * read from the first bytes, an SVG with active content refused.
 *
 * @spec openspec/specs/per-app-theming/spec.md
 */
class AppBrandLogoStore {

	/**
	 * The app data folder.
	 *
	 * @var string
	 */
	public const FOLDER = 'app-brands';

	/**
	 * The largest logo.
	 *
	 * @var int
	 */
	public const MAX_BYTES = (1024 * 1024);

	/**
	 * Accepted types and their extension.
	 *
	 * @var array<string, string>
	 */
	private const TYPES = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/svg+xml' => 'svg'];

	/**
	 * Constructor.
	 *
	 * @param IAppData     $appData The app's data store.
	 * @param ImageSniffer $sniffer The image checks.
	 */
	public function __construct(
		private readonly IAppData $appData,
		private readonly ImageSniffer $sniffer,
	) {
	}//end __construct()

	/**
	 * Validate and store a logo, replacing the previous one.
	 *
	 * @param string $appId The app id.
	 * @param string $size `large` or `small`.
	 * @param string $bytes The image.
	 *
	 * @return array{mime: string, size: int, uploadedAt: int} The metadata.
	 *
	 * @throws AppBrandException 413 when too large, 422 when not an accepted image.
	 *
	 * @spec openspec/specs/per-app-theming/spec.md
	 */
	public function store(string $appId, string $size, string $bytes): array {
		if (strlen($bytes) > self::MAX_BYTES) {
			throw new AppBrandException(reason: AppBrandException::TOO_LARGE, status: 413);
		}

		$mime = $this->sniffer->type(bytes: $bytes);
		if ($mime === null || ($mime === 'image/svg+xml' && $this->sniffer->isSafeSvg(svg: $bytes) === false)) {
			throw new AppBrandException(reason: AppBrandException::BAD_IMAGE);
		}

		$this->remove(appId: $appId, size: $size);
		$this->folder()->newFile($this->base(appId: $appId, size: $size) . '.' . self::TYPES[$mime], $bytes);

		return ['mime' => $mime, 'size' => strlen($bytes), 'uploadedAt' => time()];
	}//end store()

	/**
	 * Read a logo.
	 *
	 * @param string $appId The app id.
	 * @param string $size `large` or `small`.
	 * @param string $mime The stored type.
	 *
	 * @return string|null The bytes, or null when there is no such file.
	 *
	 * @spec openspec/specs/per-app-theming/spec.md
	 */
	public function read(string $appId, string $size, string $mime): ?string {
		if (isset(self::TYPES[$mime]) === false) {
			return null;
		}

		try {
			return $this->folder()->getFile($this->base(appId: $appId, size: $size) . '.' . self::TYPES[$mime])->getContent();
		} catch (NotFoundException $e) {
			return null;
		}
	}//end read()

	/**
	 * Remove a logo whatever its extension.
	 *
	 * @param string $appId The app id.
	 * @param string $size `large` or `small`.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/per-app-theming/spec.md
	 */
	public function remove(string $appId, string $size): void {
		$folder = $this->folder();
		$base = $this->base(appId: $appId, size: $size);
		foreach (self::TYPES as $extension) {
			if ($folder->fileExists($base . '.' . $extension) === true) {
				$folder->getFile($base . '.' . $extension)->delete();
			}
		}
	}//end remove()

	/**
	 * The file name of a logo without extension.
	 *
	 * @param string $appId The app id.
	 * @param string $size The size.
	 *
	 * @return string The name.
	 *
	 * @spec openspec/specs/per-app-theming/spec.md
	 */
	private function base(string $appId, string $size): string {
		return preg_replace('/[^a-z0-9_]/i', '', $appId) . '-' . preg_replace('/[^a-z]/', '', $size);
	}//end base()

	/**
	 * The app data folder, created on first use.
	 *
	 * @return ISimpleFolder The folder.
	 *
	 * @spec openspec/specs/per-app-theming/spec.md
	 */
	private function folder(): ISimpleFolder {
		try {
			return $this->appData->getFolder(self::FOLDER);
		} catch (NotFoundException $e) {
			return $this->appData->newFolder(self::FOLDER);
		}
	}//end folder()
}//end class
