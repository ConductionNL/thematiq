<?php

/**
 * Thematiq group house style in the personal Appearance section.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category Settings
 * @package  OCA\Thematiq
 * @author   Conduction <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://github.com/ConductionNL/thematiq
 *
 * @copyright 2026 Conduction B.V.
 *
 * @spec openspec/changes/admin-pages-as-the-tq-boards/specs/admin-settings/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Settings;

use OCP\AppFramework\Http\TemplateResponse;

/**
 * "House style of my groups" in the personal Appearance and accessibility section (whose id
 * is also `theming`), after Nextcloud's own theme settings, as the TqPersoonlijk board draws
 * it. The same block, the same visibility (a subadmin of a delegated group only) and the same
 * endpoints as {@see GroupHouseStyle}; only the form is drawn in its personal shape.
 *
 * @spec openspec/specs/per-group-theming/spec.md#requirement-a-group-subadmin-chooses-the-house-style-of-a-delegated-group
 */
class PersonalGroupHouseStyle extends GroupHouseStyle {
	/**
	 * The block in its personal shape; js/admin-group-house-style.js fills it.
	 *
	 * @return TemplateResponse The template.
	 *
	 * @spec openspec/changes/admin-pages-as-the-tq-boards/specs/admin-settings/spec.md
	 */
	public function getForm(): TemplateResponse {
		$form = parent::getForm();
		$form->setParams(['personal' => true]);

		return $form;
	}//end getForm()
}//end class
