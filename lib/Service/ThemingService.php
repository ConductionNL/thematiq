<?php

/**
 * NL Design Theming Service.
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
 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-41
 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-42
 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-43
 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-44
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCA\Theming\ImageManager;
use OCA\Theming\Service\BackgroundService;
use OCA\Theming\ThemingDefaults;
use OCP\App\IAppManager;

/**
 * Service for managing Nextcloud theming values.
 *
 * Handles validation and application of color and image changes
 * to the Nextcloud theming system.
 *
 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-41
 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-42
 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-43
 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-44
 */
class ThemingService {

	/**
	 * The theming image manager.
	 *
	 * @var ImageManager
	 */
	private ImageManager $imageManager;

	/**
	 * The theming defaults service.
	 *
	 * @var ThemingDefaults
	 */
	private ThemingDefaults $themingDefaults;

	/**
	 * The app manager for resolving paths.
	 *
	 * @var IAppManager
	 */
	private IAppManager $appManager;

	/**
	 * Constructor.
	 *
	 * @param ImageManager $imageManager The theming image manager.
	 * @param ThemingDefaults $themingDefaults The theming defaults service.
	 * @param IAppManager $appManager The app manager for resolving paths.
	 */
	public function __construct(
		ImageManager $imageManager,
		ThemingDefaults $themingDefaults,
		IAppManager $appManager,
	) {
		$this->imageManager = $imageManager;
		$this->themingDefaults = $themingDefaults;
		$this->appManager = $appManager;
	}//end __construct()

	/**
	 * Validate a hex color string.
	 *
	 * @param string $color The color to validate.
	 *
	 * @return bool True if valid hex color.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-41
	 */
	public function isValidHexColor(string $color): bool {
		return (bool)preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $color);
	}//end isValidHexColor()

	/**
	 * Validate color parameters from the request.
	 *
	 * @param array $params The request parameters.
	 *
	 * @return string|null An error message if validation fails, or null on success.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-41
	 */
	public function validateColors(array $params): ?string {
		foreach (['primary_color', 'background_color'] as $colorKey) {
			if (isset($params[$colorKey]) === true && $params[$colorKey] !== '') {
				if ($this->isValidHexColor(color: $params[$colorKey]) === false) {
					return "Invalid hex color for $colorKey: {$params[$colorKey]}";
				}
			}
		}

		return null;
	}//end validateColors()

	/**
	 * Validate image path parameters from the request.
	 *
	 * @param array $params The request parameters.
	 *
	 * @return string|null An error message if validation fails, or null on success.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-42
	 * @spec openspec/specs/theming-sync/spec.md
	 */
	public function validateImagePaths(array $params): ?string {
		// `logo_dark` follows the exact same traversal/prefix/existence rules
		// as `logo` (see the theming-sync spec's dark-logo requirement), even
		// though it is deliberately never passed to applyImages()/core
		// theming — Nextcloud core has a single logo slot
		// (nextcloud/server#47357); the dark logo is delivered by nldesign's
		// own generated dark stylesheet instead.
		foreach (['logo', 'background', 'logo_dark'] as $imageKey) {
			if (isset($params[$imageKey]) === true && $params[$imageKey] !== '') {
				$error = $this->validateSinglePath(
					imageKey: $imageKey,
					imagePath: $params[$imageKey]
				);
				if ($error !== null) {
					return $error;
				}
			}
		}

		return null;
	}//end validateImagePaths()

	/**
	 * Validate a single image path for security and existence.
	 *
	 * @param string $imageKey The image key name.
	 * @param string $imagePath The image path to validate.
	 *
	 * @return string|null An error message if validation fails, or null on success.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-42
	 */
	private function validateSinglePath(string $imageKey, string $imagePath): ?string {
		$hasDotDot = str_contains($imagePath, '..');
		$startsSlash = str_starts_with($imagePath, '/');
		if ($hasDotDot === true || $startsSlash === true) {
			return "Invalid image path for $imageKey: path traversal not allowed";
		}

		$inLogos = str_starts_with($imagePath, 'img/logos/');
		$inBgs = str_starts_with($imagePath, 'img/backgrounds/');
		if ($inLogos === false && $inBgs === false) {
			return "Invalid image path for $imageKey: must be in img/logos/ or img/backgrounds/";
		}

		$appPath = $this->appManager->getAppPath(appId: 'thematiq');
		$fullPath = $appPath . '/' . $imagePath;
		if (file_exists(filename: $fullPath) === false) {
			return "Image file not found: $imagePath";
		}

		return null;
	}//end validateSinglePath()

	/**
	 * Apply color changes to the theming defaults.
	 *
	 * @param array $params The request parameters.
	 *
	 * @return array The list of updated color keys.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-43
	 */
	public function applyColors(array $params): array {
		$updated = [];

		foreach (['primary_color', 'background_color'] as $colorKey) {
			if (isset($params[$colorKey]) === true && $params[$colorKey] !== '') {
				$this->themingDefaults->set(setting: $colorKey, value: $params[$colorKey]);
				$updated[] = $colorKey;
			}
		}

		// A plain background colour only shows when there is no background
		// IMAGE: core paints its default image blob over the colour until the
		// admin presses "Remove background image", which is exactly this
		// app value. Set it here unless the same request also brings a
		// background image, which applyImages() writes (and whose mime then
		// overrides this) right after.
		if (in_array('background_color', $updated, true) === true
			&& (isset($params['background']) === false || $params['background'] === '')
		) {
			$this->themingDefaults->set(setting: 'backgroundMime', value: 'backgroundColor');
		}

		return $updated;
	}//end applyColors()

	/**
	 * Apply image changes to the theming image manager.
	 *
	 * `ImageManager::updateImage()` only stores the FILE and hands back the
	 * mime type it detected; the `{key}Mime` app value is the caller's job, and
	 * it is what `ThemingDefaults::getLogo()` reads to decide whether a custom
	 * image exists at all. Dropping the return value therefore looked like a
	 * successful sync — `applyImages()` reported `["logo"]`, the file was
	 * written, `ImageManager::hasImage()` said true — while every page kept
	 * rendering the stock Nextcloud logo. Core's own
	 * `ThemingController::uploadImage()` pairs the two calls the same way.
	 *
	 * @param array $params The request parameters.
	 *
	 * @return array The list of updated image keys.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-44
	 * @spec openspec/specs/theming-sync/spec.md
	 */
	public function applyImages(array $params): array {
		$updated = [];

		foreach (['logo', 'background'] as $imageKey) {
			if (isset($params[$imageKey]) === true && $params[$imageKey] !== '') {
				$appPath = $this->appManager->getAppPath(appId: 'thematiq');
				$fullPath = $appPath . '/' . $params[$imageKey];
				$mime = $this->imageManager->updateImage(key: $imageKey, tmpFile: $fullPath);
				$this->themingDefaults->set(setting: $imageKey . 'Mime', value: $mime);
				$updated[] = $imageKey;
			}
		}

		return $updated;
	}//end applyImages()

	/**
	 * The settings a reset to stock Nextcloud undoes, in the order core's own
	 * panel would: colours first, then the two image slots.
	 *
	 * @var string[]
	 */
	public const RESETTABLE = ['primary_color', 'background_color', 'logo', 'background'];

	/**
	 * Undo everything Thematiq may have synced into Nextcloud theming.
	 *
	 * Selecting the stock `nextcloud` set means "Nextcloud's own colours and
	 * logo" — not "match the values in a manifest entry", which is what the
	 * sync did before and why switching back to stock kept the previous set's
	 * logo. Core's `ThemingDefaults::undo()` is the same call its panel's undo
	 * arrows make: it deletes the app value, and for an image slot deletes the
	 * stored image and its `{key}Mime`, so `getLogo()` falls back to core's own.
	 *
	 * @return array<int, string> The settings that were reset (always the full list; undo is idempotent).
	 *
	 * @spec openspec/changes/apply-without-reload/specs/theming-sync/spec.md
	 */
	public function resetToDefaults(): array {
		foreach (self::RESETTABLE as $setting) {
			$this->themingDefaults->undo(setting: $setting);
		}

		return self::RESETTABLE;
	}//end resetToDefaults()

	/**
	 * The colours core falls back to when nothing is configured, so a reset
	 * dialog can show what "default" will look like before it is applied.
	 *
	 * Read from `BackgroundService`'s constants, not from
	 * `ThemingDefaults::getDefaultColorPrimary()`: that method returns the
	 * ADMIN-configured primary (the default for users), which is exactly the
	 * value a reset removes.
	 *
	 * @return array{primary_color: string, background_color: string} The stock colours.
	 */
	public function getDefaultColors(): array {
		return [
			'primary_color' => BackgroundService::DEFAULT_COLOR,
			'background_color' => BackgroundService::DEFAULT_BACKGROUND_COLOR,
		];
	}//end getDefaultColors()

	/**
	 * Get the current Nextcloud theming image manager.
	 *
	 * @return ImageManager The image manager instance.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-44
	 */
	public function getImageManager(): ImageManager {
		return $this->imageManager;
	}//end getImageManager()
}//end class
