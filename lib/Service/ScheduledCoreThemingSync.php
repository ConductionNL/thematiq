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
 * @spec openspec/specs/scheduled-switch/spec.md#requirement-core-theming-is-synced-only-when-asked
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCA\Thematiq\AppInfo\Application;
use OCA\Thematiq\Controller\SettingsController;
use OCP\IConfig;

/**
 * What the theming-sync dialog does after an interactive switch, done
 * without a dialog for a planned switch whose administrator ticked "also
 * update the Nextcloud logo and colours": apply the token set's theming
 * block (its captured branding when it has one), or reset core theming for
 * the stock `nextcloud` set.
 *
 * @spec openspec/specs/scheduled-switch/spec.md#requirement-core-theming-is-synced-only-when-asked
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
	 * Constructor.
	 *
	 * @param ThemingService  $themingService Writes core theming.
	 * @param TokenSetService $tokenSets      Reads a set's theming block.
	 * @param IConfig         $config         Remembers which image was synced.
	 */
	public function __construct(
		private readonly ThemingService $themingService,
		private readonly TokenSetService $tokenSets,
		private readonly IConfig $config,
	) {
	}//end __construct()

	/**
	 * Sync core theming to a token set.
	 *
	 * @param string $tokenSetId The set now active.
	 *
	 * @return array<int, string> The core settings written (empty when the set has no theming or it does not validate).
	 *
	 * @spec openspec/specs/scheduled-switch/spec.md#requirement-core-theming-is-synced-only-when-asked
	 */
	public function sync(string $tokenSetId): array {
		if ($tokenSetId === ActiveTokenSetService::DEFAULT_SET) {
			$reset = $this->themingService->resetToDefaults();
			foreach (self::IMAGE_KEYS as $imageKey) {
				$this->config->deleteAppValue(Application::APP_ID, SettingsController::SYNCED_IMAGE_PREFIX . $imageKey);
			}

			return $reset;
		}

		$params = $this->themingParams(tokenSetId: $tokenSetId);
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
	}//end sync()

	/**
	 * The set's theming block, limited to what the dialog would offer.
	 *
	 * @param string $tokenSetId The set.
	 *
	 * @return array<string, string> The params.
	 */
	private function themingParams(string $tokenSetId): array {
		foreach ($this->tokenSets->getAvailableTokenSets() as $set) {
			if (($set['id'] ?? null) !== $tokenSetId || is_array($set['theming'] ?? null) === false) {
				continue;
			}

			$params = [];
			foreach (self::KEYS as $key) {
				if (is_string($set['theming'][$key] ?? null) === true && $set['theming'][$key] !== '') {
					$params[$key] = $set['theming'][$key];
				}
			}

			return $params;
		}

		return [];
	}//end themingParams()
}//end class
