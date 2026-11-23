<?php

/**
 * Repair step: move the admin page's external apps into the menu settings.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Repair
 * @package   OCA\Thematiq
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 *
 * @spec openspec/changes/apps-menu-create-pins-settings/specs/apps-menu-settings/spec.md#requirement-the-external-apps-of-the-admin-page-move-into-the-menu-settings
 */

declare(strict_types=1);

namespace OCA\Thematiq\Repair;

use OCA\Thematiq\AppInfo\Application;
use OCA\Thematiq\Service\AppMenuLegacyExternalApps;
use OCP\L10N\IFactory;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

/**
 * Runs {@see AppMenuLegacyExternalApps::migrateOnce()} on upgrade, in the instance's default
 * language. The menu and the Menu settings dialog run it too, so an instance whose version did not
 * change still moves its apps on the first page that draws the menu.
 *
 * @spec openspec/changes/apps-menu-create-pins-settings/specs/apps-menu-settings/spec.md#requirement-the-external-apps-of-the-admin-page-move-into-the-menu-settings
 */
class MigrateMenuExternalApps implements IRepairStep {

	/**
	 * Constructor.
	 *
	 * @param AppMenuLegacyExternalApps $legacy The move.
	 * @param IFactory $l10nFactory The instance's default language.
	 */
	public function __construct(
		private readonly AppMenuLegacyExternalApps $legacy,
		private readonly IFactory $l10nFactory,
	) {
	}//end __construct()

	/**
	 * The step's name.
	 *
	 * @return string The name.
	 *
	 * @spec openspec/changes/apps-menu-create-pins-settings/specs/apps-menu-settings/spec.md#requirement-the-external-apps-of-the-admin-page-move-into-the-menu-settings
	 */
	public function getName(): string {
		return 'Move the external apps of the admin page into the apps menu settings';
	}//end getName()

	/**
	 * Move the apps, once.
	 *
	 * @param IOutput $output The output.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/apps-menu-create-pins-settings/specs/apps-menu-settings/spec.md#requirement-the-external-apps-of-the-admin-page-move-into-the-menu-settings
	 */
	public function run(IOutput $output): void {
		$moved = $this->legacy->migrateOnce(l: $this->l10nFactory->get(Application::APP_ID));
		$output->info('MigrateMenuExternalApps: ' . $moved . ' external app(s) moved into the menu settings.');
	}//end run()
}//end class
