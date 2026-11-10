<?php

/**
 * Thematiq Core Theming State.
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
 * @spec openspec/specs/theming-sync/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCA\Thematiq\AppInfo\Application;
use OCA\Thematiq\Controller\SettingsController;
use OCA\Theming\ImageManager;
use OCP\IConfig;

/**
 * Core theming's current state as the sync dialog compares it, and the
 * bookkeeping of which file the sync put in each image slot. Built by
 * SettingsController.
 *
 * @spec openspec/specs/theming-sync/spec.md
 */
final class CoreThemingState {

	/**
	 * Constructor.
	 *
	 * @param IConfig $config The app config.
	 * @param ThemingService $theming Core theming: the image manager and the default colours.
	 */
	public function __construct(
		private readonly IConfig $config,
		private readonly ThemingService $theming,
	) {
	}//end __construct()

	/**
	 * Build a snapshot of the current theming state.
	 *
	 * @return array<string, mixed> The theming snapshot.
	 *
	 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-24
	 */
	public function snapshot(): array {
		$imgManager = $this->theming->getImageManager();

		$defaults = $this->theming->getDefaultColors();

		return [
			'primary_color' => $this->config->getAppValue('theming', 'primary_color', ''),
			'background_color' => $this->config->getAppValue('theming', 'background_color', ''),
		] + $this->imageSnapshot(imgManager: $imgManager) + [
			// 'backgroundColor' when "Remove background image" is on — the
			// state a theme that captured the panel may need to put back.
			'background_mime' => $this->config->getAppValue('theming', 'backgroundMime', ''),
			// What a reset lands on, so the stock set's dialog can show it
			// before it is applied rather than guess.
			'default_primary_color' => $defaults['primary_color'],
			'default_background_color' => $defaults['background_color'],
		] + $this->syncedImageSnapshot();
	}//end snapshot()

	/**
	 * Remember WHICH image was synced into each slot.
	 *
	 * Core stores only that a custom image exists (`{key}Mime`), never which
	 * file it came from, so without this the sync dialog cannot tell "this
	 * set's logo is already the one in place" from "this set has a logo" — and
	 * it offered the same unchanged logo on every single apply.
	 *
	 * @param array<int, string> $imageKeys The image slots the apply wrote.
	 * @param array<string, mixed> $params The request parameters, holding each slot's file.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/theming-sync/spec.md
	 */
	public function rememberSyncedImages(array $imageKeys, array $params): void {
		foreach ($imageKeys as $imageKey) {
			$this->config->setAppValue(
				Application::APP_ID,
				SettingsController::SYNCED_IMAGE_PREFIX . $imageKey,
				(string)$params[$imageKey]
			);
		}
	}//end rememberSyncedImages()

	/**
	 * Forget every synced image: after a reset nothing is synced any more.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/apply-without-reload/specs/theming-sync/spec.md
	 */
	public function forgetSyncedImages(): void {
		foreach (['logo', 'logoheader', 'favicon', 'background'] as $imageKey) {
			$this->config->deleteAppValue(Application::APP_ID, SettingsController::SYNCED_IMAGE_PREFIX . $imageKey);
		}
	}//end forgetSyncedImages()

	/**
	 * Increment the theming sync counter exposed by MetricsController as
	 * nldesign_theming_syncs_total. Only counted on success (after both
	 * apply* calls completed without throwing).
	 *
	 * @return void
	 *
	 * @spec openspec/specs/theming-sync/spec.md
	 */
	public function countSync(): void {
		$current = (int)$this->config->getAppValue(Application::APP_ID, 'theming_syncs_total', '0');
		$this->config->setAppValue(Application::APP_ID, 'theming_syncs_total', (string)($current + 1));
	}//end countSync()

	/**
	 * The core image URLs and which image slots hold a custom image.
	 *
	 * @param ImageManager $imgManager Core theming's image manager.
	 *
	 * @return array<string, mixed> The image part of the snapshot.
	 */
	private function imageSnapshot(ImageManager $imgManager): array {
		return [
			'logo_url' => $imgManager->getImageUrl('logo'),
			'background_url' => $imgManager->getImageUrl('background'),
			'has_custom_logo' => $imgManager->hasImage('logo'),
			'has_custom_logoheader' => $imgManager->hasImage('logoheader'),
			'has_custom_favicon' => $imgManager->hasImage('favicon'),
			'has_custom_background' => $imgManager->hasImage('background'),
		];
	}//end imageSnapshot()

	/**
	 * Which file the sync last put in each image slot, so the dialog can
	 * leave out a logo that is already the one in place.
	 *
	 * @return array<string, string> `synced_{slot}` => the file, '' when none.
	 */
	private function syncedImageSnapshot(): array {
		$synced = [];
		foreach (['logo', 'background', 'logoheader', 'favicon'] as $slot) {
			$synced['synced_' . $slot] = $this->config->getAppValue(
				Application::APP_ID,
				SettingsController::SYNCED_IMAGE_PREFIX . $slot,
				''
			);
		}

		return $synced;
	}//end syncedImageSnapshot()
}//end class
