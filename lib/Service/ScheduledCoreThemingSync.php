<?php

/**
 * Thematiq Scheduled Core Theming Sync.
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
 * @spec openspec/specs/scheduled-switch/spec.md#requirement-a-planned-switch-applies-a-set-like-the-apply-dialog
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCA\Thematiq\AppInfo\Application;
use OCA\Thematiq\Controller\SettingsController;
use OCP\IConfig;

/**
 * What the theming-sync dialog does after an interactive switch, done
 * without a dialog for a planned switch: apply the token set's theming block
 * (its captured branding when it has one), or reset core theming for the
 * stock `nextcloud` set.
 *
 * Before a switch overwrites core theming it takes a snapshot of it, and the
 * end of the switch restores that snapshot rather than deriving core theming
 * from the set it goes back to: an administrator's own logo and colours, set
 * in Nextcloud's Theming app, belong to no token set and would otherwise be
 * lost at the end of the first campaign.
 *
 * @spec openspec/specs/scheduled-switch/spec.md#requirement-a-planned-switch-applies-a-set-like-the-apply-dialog
 */
class ScheduledCoreThemingSync {

	/**
	 * The theming keys the dialog can offer.
	 *
	 * @var array<int, string>
	 */
	private const KEYS = ['primary_color', 'background_color', 'logo', 'logoheader', 'favicon', 'background', 'background_mode'];

	/**
	 * The image slots whose synced file is remembered.
	 *
	 * @var array<int, string>
	 */
	private const IMAGE_KEYS = ['logo', 'logoheader', 'favicon', 'background'];

	/**
	 * The prefix of a switch's snapshot in the captured-branding store. No token
	 * set id starts with it, so a snapshot is never read as a set's branding.
	 *
	 * @var string
	 */
	public const SNAPSHOT_PREFIX = 'switch-';

	/**
	 * Constructor.
	 *
	 * @param ThemingService         $themingService Writes core theming.
	 * @param TokenSetService        $tokenSets      Reads a set's theming block.
	 * @param IConfig                $config         Remembers which image was synced.
	 * @param BrandingCaptureService $branding       Takes and keeps the snapshot of core theming.
	 */
	public function __construct(
		private readonly ThemingService $themingService,
		private readonly TokenSetService $tokenSets,
		private readonly IConfig $config,
		private readonly BrandingCaptureService $branding,
	) {
	}//end __construct()

	/**
	 * Take a snapshot of core theming before a switch overwrites it.
	 *
	 * @param string $switchId The planned switch.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/scheduled-switch/spec.md#requirement-a-planned-switch-applies-a-set-like-the-apply-dialog
	 */
	public function snapshot(string $switchId): void {
		$this->branding->capture(setId: self::SNAPSHOT_PREFIX . $switchId);
	}//end snapshot()

	/**
	 * Put core theming back as it was before a switch, and drop the snapshot.
	 *
	 * Reset first: the snapshot leaves out a colour or image that was
	 * Nextcloud's own default, and the reset is what brings those back.
	 *
	 * @param string $switchId The planned switch.
	 *
	 * @return array<int, string> The core settings written; empty when the switch has no snapshot.
	 *
	 * @spec openspec/specs/scheduled-switch/spec.md#requirement-a-planned-switch-applies-a-set-like-the-apply-dialog
	 */
	public function restore(string $switchId): array {
		$key = self::SNAPSHOT_PREFIX . $switchId;
		$snapshot = ($this->branding->all()[$key] ?? null);
		if (is_array($snapshot) === false) {
			return [];
		}

		$written = $this->themingService->resetToDefaults();
		foreach (self::IMAGE_KEYS as $imageKey) {
			$this->config->deleteAppValue(Application::APP_ID, SettingsController::SYNCED_IMAGE_PREFIX . $imageKey);
		}

		$written = array_values(array_unique(array_merge($written, $this->applyParams(params: $this->paramsOf(theming: $snapshot)))));
		$this->branding->forget(setId: $key);

		return $written;
	}//end restore()

	/**
	 * Sync core theming to a token set.
	 *
	 * @param string $tokenSetId The set now active.
	 *
	 * @return array<int, string> The core settings written (empty when the set has no theming or it does not validate).
	 *
	 * @spec openspec/specs/scheduled-switch/spec.md#requirement-a-planned-switch-applies-a-set-like-the-apply-dialog
	 */
	public function sync(string $tokenSetId): array {
		if ($tokenSetId === ActiveTokenSetService::DEFAULT_SET) {
			$reset = $this->themingService->resetToDefaults();
			foreach (self::IMAGE_KEYS as $imageKey) {
				$this->config->deleteAppValue(Application::APP_ID, SettingsController::SYNCED_IMAGE_PREFIX . $imageKey);
			}

			return $reset;
		}

		return $this->applyParams(params: $this->themingParams(tokenSetId: $tokenSetId));
	}//end sync()

	/**
	 * Write a theming block into core theming, when it validates.
	 *
	 * @param array<string, string> $params The block, limited to what the dialog would offer.
	 *
	 * @return array<int, string> The core settings written.
	 */
	private function applyParams(array $params): array {
		if ($params === []
			|| $this->themingService->validateColors(params: $params) !== null
			|| $this->themingService->validateImagePaths(params: $params) !== null
		) {
			return [];
		}

		$updatedImages = $this->themingService->applyImages(params: $params);
		foreach ($updatedImages as $imageKey) {
			$this->config->setAppValue(Application::APP_ID, SettingsController::SYNCED_IMAGE_PREFIX . $imageKey, (string)$params[$imageKey]);
		}

		return array_merge($this->themingService->applyColors(params: $params), $updatedImages);
	}//end applyParams()

	/**
	 * The set's theming block, limited to what the dialog would offer.
	 *
	 * Public so `occ thematiq:theme:set --sync-core` can tell a set without
	 * theming values from one whose values did not validate.
	 *
	 * @param string $tokenSetId The set.
	 *
	 * @return array<string, string> The params, empty when the set carries none.
	 *
	 * @spec openspec/specs/theme-cli/spec.md#requirement-core-theming-is-changed-only-on-request
	 */
	public function themingParams(string $tokenSetId): array {
		foreach ($this->tokenSets->getAvailableTokenSets() as $set) {
			if (($set['id'] ?? null) !== $tokenSetId || is_array($set['theming'] ?? null) === false) {
				continue;
			}

			return $this->paramsOf(theming: $set['theming']);
		}

		return [];
	}//end themingParams()

	/**
	 * A theming block limited to what the dialog would offer.
	 *
	 * @param array<string, mixed> $theming The block.
	 *
	 * @return array<string, string> The params.
	 */
	private function paramsOf(array $theming): array {
		$params = [];
		foreach (self::KEYS as $key) {
			if (is_string($theming[$key] ?? null) === true && $theming[$key] !== '') {
				$params[$key] = $theming[$key];
			}
		}

		return $params;
	}//end paramsOf()
}//end class
