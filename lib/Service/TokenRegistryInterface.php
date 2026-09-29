<?php

/**
 * NL Design Token Registry Interface.
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

/**
 * Interface for the token registry.
 *
 * Defines the contract for querying editable CSS custom properties.
 */
interface TokenRegistryInterface {
	/**
	 * Returns the full registry of editable tokens.
	 *
	 * `group` is `brand` for Nextcloud's own globals and the component id for a
	 * `--nldesign-component-*` token; `primary` marks a component token the brand
	 * primary used to drive, which the `primary_drives_components` setting locks.
	 *
	 * @return array<string, array{tab: string, type: string, label: string, group: string, primary: bool, global?: string}> The token registry.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-45
	 */
	public static function getTokens(): array;

	/**
	 * Returns the display labels for each tab.
	 *
	 * @return array<string, string> Map of tab id to display label.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-46
	 */
	public static function getTabLabels(): array;

	/**
	 * Checks whether a given token name is editable.
	 *
	 * @param string $tokenName The CSS custom property name.
	 *
	 * @return bool True if the token is in the registry.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-47
	 */
	public static function isEditable(string $tokenName): bool;
}//end interface
