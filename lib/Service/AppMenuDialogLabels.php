<?php

/**
 * Thematiq apps menu: the texts of the Menu settings dialog.
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
 * @spec openspec/changes/apps-menu-create-pins-settings/specs/apps-menu-settings/spec.md#requirement-the-menu-settings-dialog
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCP\IL10N;

/**
 * The dialog's texts in the request's language, sent when the dialog opens so pages that never
 * open it do not carry them.
 *
 * @spec openspec/changes/apps-menu-create-pins-settings/specs/apps-menu-settings/spec.md#requirement-the-menu-settings-dialog
 */
final class AppMenuDialogLabels {

	/**
	 * The texts, by key.
	 *
	 * @param IL10N $l The language of this request.
	 *
	 * @return array<string, string> The texts.
	 *
	 * @SuppressWarnings(PHPMD.ExcessiveMethodLength) - a list of texts, one per line.
	 *
	 * @spec openspec/changes/apps-menu-create-pins-settings/specs/apps-menu-settings/spec.md#requirement-the-menu-settings-dialog
	 */
	public static function labels(IL10N $l): array {
		return [
			'title' => $l->t('Menu settings'),
			'close' => $l->t('Close'),
			'scope' => $l->t('Set for'),
			'everyone' => $l->t('For everyone'),
			'onlyMe' => $l->t('Only for me'),
			'everyoneHelp' => $l->t('You set the default for the whole organisation. People change their own menu items themselves.'),
			'onlyMeHelp' => $l->t('You change your own menu items. The rest follows the organisation\'s settings.'),
			'tabs' => $l->t('Parts of the menu settings'),
			'tabItems' => $l->t('Menu items'),
			'tabConfiguration' => $l->t('Configuration'),
			'tabTheming' => $l->t('Theming'),
			'apps' => $l->t('Apps'),
			'appsHelp' => $l->t('Drag or use Alt with an arrow key to reorder. Pinned to the top bar: {count} of {max}.'),
			'moveUp' => $l->t('Move {app} up'),
			'moveDown' => $l->t('Move {app} down'),
			'pin' => $l->t('Pin {app} to the top bar'),
			'show' => $l->t('Show {app}'),
			'edit' => $l->t('Edit {app}'),
			'delete' => $l->t('Delete {app}'),
			'external' => $l->t('External apps'),
			'externalHelp' => $l->t('Links to other systems. They always open in a new tab.'),
			'addExternal' => $l->t('Add external app'),
			'noExternal' => $l->t('No external apps yet.'),
			'menuStyle' => $l->t('Menu style'),
			'workplace' => $l->t('Workplace menu'),
			'workplaceHelp' => $l->t('A grid of apps under the All apps button, with sections and create actions.'),
			'classic' => $l->t('Classic Nextcloud menu'),
			'classicHelp' => $l->t('A row of app icons in the top bar, as Nextcloud ships it.'),
			'grid' => $l->t('Grid'),
			'gridHelp' => $l->t('The preview follows the numbers.'),
			'columns' => $l->t('Columns'),
			'columnsHelp' => $l->t('2 to 5 side by side'),
			'fewerColumns' => $l->t('Fewer columns'),
			'moreColumns' => $l->t('More columns'),
			'rows' => $l->t('Rows'),
			'rowsHelp' => $l->t('The total, after that the menu scrolls'),
			'fewerRows' => $l->t('Fewer rows'),
			'moreRows' => $l->t('More rows'),
			'rowsAboveFold' => $l->t('Rows above More apps'),
			'rowsAboveFoldHelp' => $l->t('The rest sits below the fold'),
			'fewerRowsAboveFold' => $l->t('Fewer rows above More apps'),
			'moreRowsAboveFold' => $l->t('More rows above More apps'),
			'moreApps' => $l->t('More apps'),
			'gridPreview' => $l->t('Preview: {columns} columns, {fold} rows above More apps, {rows} rows in total. More apps scrolls.'),
			'sections' => $l->t('Sections'),
			'showCreateActions' => $l->t('Show create actions'),
			'showCreateActionsHelp' => $l->t('The Create row at the bottom of the menu.'),
			'showExternalApps' => $l->t('Show external apps'),
			'showExternalAppsHelp' => $l->t('The External apps section under the apps.'),
			'manageApps' => $l->t('Manage apps'),
			'newTab' => $l->t('(opens in a new tab)'),
			'adminsOnly' => $l->t('Admins only'),
			'manageHelp' => $l->t('Installing or disabling apps happens in the Nextcloud admin settings.'),
			'setByAdmin' => $l->t('Your administrator sets the configuration.'),
			'shape' => $l->t('Shape of the items'),
			'round' => $l->t('Round'),
			'square' => $l->t('Square'),
			'look' => $l->t('Display'),
			'tiles' => $l->t('Show items as tiles'),
			'tilesHelp' => $l->t('Each item gets a card with a border and background.'),
			'payoff' => $l->t('Show payoff'),
			'payoffHelp' => $l->t('The line under the name, for example Cases and Woo.'),
			'iconSize' => $l->t('Icon size'),
			'small' => $l->t('Small'),
			'large' => $l->t('Large'),
			'iconColour' => $l->t('Icon colour'),
			'brand' => $l->t('Brand colour'),
			'neutral' => $l->t('Neutral'),
			'preview' => $l->t('Preview'),
			'reset' => $l->t('Reset to default'),
			'resetEveryone' => $l->t('Reset the menu for everyone to the shipped default?'),
			'resetOnlyMe' => $l->t('Reset your menu items to the organisation\'s default?'),
			'cancel' => $l->t('Cancel'),
			'save' => $l->t('Save'),
			'saveFailed' => $l->t('The settings could not be saved.'),
			'loadFailed' => $l->t('The settings could not be loaded.'),
			'editExternal' => $l->t('Edit external app'),
			'fieldTitle' => $l->t('Title'),
			'fieldTitleHelp' => $l->t('The name in the menu.'),
			'fieldPayoff' => $l->t('Payoff'),
			'fieldPayoffHelp' => $l->t('The line under the name. May stay empty.'),
			'fieldUrl' => $l->t('URL'),
			'fieldUrlHelp' => $l->t('Starts with https://.'),
			'icon' => $l->t('Icon'),
			'iconLibrary' => $l->t('Icon from the library'),
			'upload' => $l->t('Upload your own icon'),
			'uploadHelp' => $l->t('SVG or PNG, square, up to 100 kB'),
			'uploaded' => $l->t('Your own icon'),
			'uploadFailed' => $l->t('The icon could not be uploaded. Use an SVG or PNG of at most 100 kB.'),
			'formPreview' => $l->t('How it looks in the menu'),
			'alwaysNewTab' => $l->t('An external app always opens in a new tab.'),
			'add' => $l->t('Add'),
			'titleRequired' => $l->t('Enter a title.'),
			'urlRequired' => $l->t('Enter an address that starts with https://.'),
			'iconLink' => $l->t('Link'),
			'iconGlobe' => $l->t('Globe'),
			'iconBuilding' => $l->t('Building'),
			'iconNews' => $l->t('News'),
			'iconBook' => $l->t('Book'),
			'iconChart' => $l->t('Chart'),
			'iconMap' => $l->t('Map'),
			'iconPeople' => $l->t('People'),
			'iconSupport' => $l->t('Support'),
			'iconEuro' => $l->t('Euro'),
			'iconScales' => $l->t('Scales'),
			'iconTruck' => $l->t('Truck'),
			'iconKey' => $l->t('Key'),
			'iconShield' => $l->t('Shield'),
			'iconTree' => $l->t('Tree'),
			'iconStar' => $l->t('Star'),
		];
	}//end labels()
}//end class
