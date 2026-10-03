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
	 * The header logo, more specific than any design system's rule for it
	 * (`#header .logo` in La Suite, `#nextcloud .logo` in nldesign, and
	 * Nextcloud's `#header #nextcloud .logo`).
	 *
	 * @var string
	 */
	private const HEADER_LOGO_SELECTOR = 'body #header #nextcloud .logo';

	/**
	 * Constructor.
	 *
	 * @param IAppData $appData The app's data store.
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
	 * The logo variable for a branded app's pages: the large logo, and the small
	 * one below 1024 px, the breakpoint at which Nextcloud's header goes narrow.
	 * Unquoted, like the other logo URLs of LogoLayerService::layer(). Followed
	 * by a header-logo rule that draws it on every design system.
	 *
	 * @param string $large The large logo URL.
	 * @param string|null $small The small logo URL, or null to use the large one at every width.
	 *
	 * @return string The stylesheet body.
	 *
	 * @spec openspec/specs/per-app-theming/spec.md
	 */
	public function logoCss(string $large, ?string $small): string {
		$css = ':root{--nldesign-logo-url:url(' . $large . ');--nldesign-logo-filter:none}';
		if ($small !== null) {
			$css .= '@media (max-width:1024px){:root{--nldesign-logo-url:url(' . $small . ')}}';
		}

		// Only the nldesign bundle reads the variable. The La Suite element
		// overrides, which Cunningham shares, paint the header logo as a mask
		// of Nextcloud's logo with `background-image: none !important`, and
		// Nextcloud's own rule reads --image-logoheader (#942). This rule
		// outranks all of them and draws the brand through the same variable,
		// so the small logo still swaps in below the breakpoint.
		return $css . self::HEADER_LOGO_SELECTOR . '{'
			. 'background-image:var(--nldesign-logo-url) !important;'
			. 'background-color:transparent !important;'
			. '-webkit-mask:none !important;'
			. 'mask:none !important;'
			. 'filter:none !important}';
	}//end logoCss()

	/**
	 * The logo layer for a page, when the rendered app's brand applies to it:
	 * the brand resolved to the page's set (no preview won) and has a large logo.
	 *
	 * @param array{tokenSet: string, large: string|null, small: string|null}|null $brand The app's brand.
	 * @param string $tokenSet The set the page resolved to.
	 * @param string $styleId The id of the inline logo style block.
	 *
	 * @return array{layer: string, kind: string, css: string, id: string}|null The layer, or null.
	 *
	 * @spec openspec/specs/per-app-theming/spec.md
	 */
	public function layer(?array $brand, string $tokenSet, string $styleId): ?array {
		if ($brand === null || $brand['tokenSet'] !== $tokenSet || $brand['large'] === null) {
			return null;
		}

		return ['layer' => 'logo-url', 'kind' => 'inline', 'css' => $this->logoCss(large: $brand['large'], small: $brand['small']), 'id' => $styleId];
	}//end layer()

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
