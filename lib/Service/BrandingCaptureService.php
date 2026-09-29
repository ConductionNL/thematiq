<?php

/**
 * NL Design Branding Capture Service.
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
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCA\Thematiq\AppInfo\Application;
use OCA\Thematiq\Controller\SettingsController;
use OCA\Theming\ImageManager;
use OCP\App\IAppManager;
use OCP\IConfig;

/**
 * Keeps Nextcloud's own branding with a theme.
 *
 * Nextcloud's Theming panel ("Background and color") holds the primary
 * colour, the background colour, whether the background image was removed,
 * and the uploaded background, logo, navigation-bar logo and favicon. None of
 * that lives in a token set, so a theme saved from the editor used to come
 * back without it: applying the theme later re-coloured the components and
 * left the branding of whatever theme happened to be on before.
 *
 * {@see capture()} copies that branding into the theme when it is saved, and
 * the theming sync ({@see ThemingService}) puts it back when the theme is
 * applied. Images are copied as files, because core takes an image only as
 * a file on disk (`ImageManager::updateImage()`), and they are copied rather
 * than referenced because the slot they came from is overwritten by the next
 * theme that brings an image of its own.
 *
 * The captured block is kept in one app value for every set — shipped and
 * custom alike — because a shipped set's metadata is a read-only file in the
 * app, and one store for both keeps "what this theme remembers" in one place.
 * {@see TokenSetService} lays it over the set's own theming.
 */
class BrandingCaptureService {

	/**
	 * The app value holding every set's captured branding, by set id.
	 */
	public const CAPTURED_KEY = 'captured_theming';

	/**
	 * The image slots Nextcloud's Theming panel offers.
	 */
	public const IMAGE_KEYS = ['logo', 'logoheader', 'favicon', 'background'];

	/**
	 * The file extension for each image type core accepts.
	 */
	private const EXTENSIONS = [
		'image/svg+xml' => 'svg',
		'image/svg' => 'svg',
		'image/png' => 'png',
		'image/jpeg' => 'jpg',
		'image/jpg' => 'jpg',
		'image/gif' => 'gif',
		'image/webp' => 'webp',
		'image/x-icon' => 'ico',
		'image/vnd.microsoft.icon' => 'ico',
	];

	/**
	 * Core's theming image store.
	 *
	 * @var ImageManager
	 */
	private ImageManager $imageManager;

	/**
	 * The app config: core's theming values, and the captured store.
	 *
	 * @var IConfig
	 */
	private IConfig $config;

	/**
	 * The app manager, for the app's own image directories.
	 *
	 * @var IAppManager
	 */
	private IAppManager $appManager;

	/**
	 * Constructor.
	 *
	 * @param ImageManager $imageManager Core's theming image store.
	 * @param IConfig      $config       The app config.
	 * @param IAppManager  $appManager   The app manager.
	 */
	public function __construct(
		ImageManager $imageManager,
		IConfig $config,
		IAppManager $appManager,
	) {
		$this->imageManager = $imageManager;
		$this->config = $config;
		$this->appManager = $appManager;
	}//end __construct()

	/**
	 * Copy Nextcloud's current branding into a theme, and remember it.
	 *
	 * The result is the theme's theming block from now on. `captured` marks it
	 * as a snapshot of a whole panel rather than a set's suggestion: applying
	 * it restores exactly this, including a background image that was
	 * removed, or the default one that was never replaced.
	 *
	 * The slots are then recorded as synced to the copied files, because they
	 * ARE those files right now — without it the next apply of this very theme
	 * would offer to "change" every image to itself.
	 *
	 * @param string $setId The token set the branding belongs to.
	 *
	 * @return array<string, mixed> The captured theming block.
	 */
	public function capture(string $setId): array {
		$this->deleteFiles(setId: $setId);

		$theming = ['captured' => true];

		foreach (['primary_color', 'background_color'] as $colorKey) {
			$value = $this->config->getAppValue('theming', $colorKey, '');
			if ($value !== '') {
				$theming[$colorKey] = $value;
			}
		}

		// Three states, not two: an image of the admin's own, the image
		// removed in favour of the plain colour, or Nextcloud's default image.
		$backgroundMime = $this->config->getAppValue('theming', 'backgroundMime', '');
		$theming['background_mode'] = 'default';
		if ($backgroundMime === 'backgroundColor') {
			$theming['background_mode'] = 'color';
		}

		foreach (self::IMAGE_KEYS as $imageKey) {
			$path = $this->copyImage(setId: $setId, imageKey: $imageKey);
			if ($path === null) {
				continue;
			}

			$theming[$imageKey] = $path;
			if ($imageKey === 'background') {
				$theming['background_mode'] = 'image';
			}

			$this->config->setAppValue(Application::APP_ID, SettingsController::SYNCED_IMAGE_PREFIX . $imageKey, $path);
		}

		$captured = $this->all();
		$captured[$setId] = $theming;
		$this->save(captured: $captured);

		return $theming;
	}//end capture()

	/**
	 * Every set's captured branding, by set id.
	 *
	 * @return array<string, array<string, mixed>> The captured blocks.
	 */
	public function all(): array {
		$decoded = json_decode((string)$this->config->getAppValue(Application::APP_ID, self::CAPTURED_KEY, '{}'), true);
		if (is_array($decoded) === false) {
			return [];
		}

		return $decoded;
	}//end all()

	/**
	 * Forget a set's captured branding and remove its copied images.
	 *
	 * @param string $setId The token set.
	 *
	 * @return void
	 */
	public function forget(string $setId): void {
		$this->deleteFiles(setId: $setId);

		$captured = $this->all();
		if (isset($captured[$setId]) === true) {
			unset($captured[$setId]);
			$this->save(captured: $captured);
		}
	}//end forget()

	/**
	 * Copy one of core's images into the app, for a set.
	 *
	 * Named after the set and the slot, with `-captured-` in between, so a
	 * copy can never overwrite a shipped municipality logo or a logo the
	 * theme converter extracted (`img/logos/{id}.{ext}`).
	 *
	 * @param string $setId    The token set.
	 * @param string $imageKey The image slot.
	 *
	 * @return string|null The app-relative path written, or null when the slot holds no image of the admin's.
	 */
	private function copyImage(string $setId, string $imageKey): ?string {
		if ($this->imageManager->hasImage($imageKey) === false) {
			return null;
		}

		$mime = $this->config->getAppValue('theming', $imageKey . 'Mime', '');
		$extension = (self::EXTENSIONS[$mime] ?? null);
		if ($extension === null) {
			return null;
		}

		try {
			$contents = $this->imageManager->getImage($imageKey)->getContent();
		} catch (\Throwable $e) {
			// An unreadable slot is left out of the theme rather than failing
			// the save it rides on: the tokens still saved, only this image
			// will not come back with the theme.
			return null;
		}

		$directory = $this->directoryFor(imageKey: $imageKey);
		$absolute = $this->appManager->getAppPath(Application::APP_ID) . '/' . $directory;
		if (is_dir($absolute) === false && mkdir($absolute, 0755, true) === false && is_dir($absolute) === false) {
			return null;
		}

		$file = $this->fileName(setId: $setId, imageKey: $imageKey) . '.' . $extension;
		if (file_put_contents($absolute . '/' . $file, $contents) === false) {
			return null;
		}

		return $directory . '/' . $file;
	}//end copyImage()

	/**
	 * Remove every image copied for a set, whatever its extension.
	 *
	 * @param string $setId The token set.
	 *
	 * @return void
	 */
	private function deleteFiles(string $setId): void {
		$appPath = $this->appManager->getAppPath(Application::APP_ID);

		foreach (self::IMAGE_KEYS as $imageKey) {
			$base = $appPath . '/' . $this->directoryFor(imageKey: $imageKey) . '/' . $this->fileName(setId: $setId, imageKey: $imageKey);
			foreach (array_unique(array_values(self::EXTENSIONS)) as $extension) {
				if (is_file($base . '.' . $extension) === true) {
					unlink($base . '.' . $extension);
				}
			}
		}
	}//end deleteFiles()

	/**
	 * The app directory an image slot is copied into: the two ThemingService
	 * accepts a sync from.
	 *
	 * @param string $imageKey The image slot.
	 *
	 * @return string The app-relative directory.
	 */
	private function directoryFor(string $imageKey): string {
		if ($imageKey === 'background') {
			return 'img/backgrounds';
		}

		return 'img/logos';
	}//end directoryFor()

	/**
	 * The file name (without extension) a set's copy of a slot is kept under.
	 *
	 * @param string $setId    The token set.
	 * @param string $imageKey The image slot.
	 *
	 * @return string The file name.
	 */
	private function fileName(string $setId, string $imageKey): string {
		return $setId . '-captured-' . $imageKey;
	}//end fileName()

	/**
	 * Persist every set's captured branding.
	 *
	 * @param array<string, array<string, mixed>> $captured The captured blocks, by set id.
	 *
	 * @return void
	 */
	private function save(array $captured): void {
		$this->config->setAppValue(
			Application::APP_ID,
			self::CAPTURED_KEY,
			json_encode($captured, JSON_UNESCAPED_SLASHES)
		);
	}//end save()
}//end class
