<?php

/**
 * Thematiq repair step: the older menu labels option moves into "Show titles in the top bar".
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category Repair
 * @package  OCA\Thematiq\Repair
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git_id>
 *
 * @link https://github.com/ConductionNL/thematiq
 *
 * @spec openspec/changes/apps-menu-show-titles/specs/menu-labels/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Repair;

use OCA\Thematiq\AppInfo\Application;
use OCA\Thematiq\Service\AppMenuTopBarTitles;
use OCP\IConfig;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Moves the app value `show_menu_labels` ('1' or '0') once into the menu setting
 * `menu.topBarTitles` (true or false) and removes it. A value the administrator already stored as
 * `menu.topBarTitles` wins and is never overwritten; the older value is removed either way. A repair
 * step, not a move on the first page, because with the classic menu style no menu script runs. It
 * runs after MigrateAppConfigKeys, which moves `show_menu_labels` from the old app id first.
 * Idempotent and never throws: a failure is logged and the reader keeps falling back to the older
 * value.
 *
 * @spec openspec/changes/apps-menu-show-titles/specs/menu-labels/spec.md
 */
class MigrateMenuLabelsToTopBarTitles implements IRepairStep {

	/**
	 * Constructor.
	 *
	 * @param IConfig $config The app values.
	 * @param LoggerInterface $logger Records a failure.
	 */
	public function __construct(
		private readonly IConfig $config,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * The step's name in the upgrade output.
	 *
	 * @return string The name.
	 */
	public function getName(): string {
		return 'Move the menu labels option into Show titles in the top bar';
	}//end getName()

	/**
	 * Move the value once and remove the older key.
	 *
	 * @param IOutput $output The upgrade output.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/apps-menu-show-titles/specs/menu-labels/spec.md
	 */
	public function run(IOutput $output): void {
		try {
			$titles = new AppMenuTopBarTitles(config: $this->config);
			$legacy = $titles->legacy();
			if ($legacy === null) {
				return;
			}

			if ($titles->stored() === null) {
				$titles->set(on: $legacy);
				$output->info('Moved show_menu_labels into menu.topBarTitles.');
			}

			$this->config->deleteAppValue(Application::APP_ID, AppMenuTopBarTitles::LEGACY_KEY);
		} catch (Throwable $e) {
			$this->logger->warning(
				'thematiq: the menu labels option could not be moved; it keeps being read as it is.',
				['app' => Application::APP_ID, 'exception' => $e]
			);
		}//end try
	}//end run()
}//end class
