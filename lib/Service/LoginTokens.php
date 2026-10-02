<?php

/**
 * NL Design Login Token Definitions.
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
 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-39
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * Login and branding tab token definitions.
 *
 * Primary brand colors that drive login page buttons, links,
 * navigation accents, and interactive highlights.
 *
 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-39
 */
class LoginTokens {
	/**
	 * Returns the login and branding tab tokens.
	 *
	 * @return array<string, array{tab: string, type: string, label: string}> Login tokens.
	 *
	 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-39
	 */
	public static function getTokens(): array {
		return [
			'--color-primary' => [
				'tab' => 'login',
				'type' => 'color',
				'label' => 'Primary color',
			],
			'--color-primary-text' => [
				'tab' => 'login',
				'type' => 'color',
				'label' => 'Primary text color',
			],
			'--color-primary-hover' => [
				'tab' => 'login',
				'type' => 'color',
				'label' => 'Primary hover color',
			],
			'--color-primary-element' => [
				'tab' => 'login',
				'type' => 'color',
				'label' => 'Primary element color',
			],
			'--color-primary-element-hover' => [
				'tab' => 'login',
				'type' => 'color',
				'label' => 'Primary element hover',
			],
			'--color-primary-element-text' => [
				'tab' => 'login',
				'type' => 'color',
				'label' => 'Primary element text',
			],
			'--color-primary-light' => [
				'tab' => 'login',
				'type' => 'color',
				'label' => 'Primary light',
			],
			'--color-primary-light-hover' => [
				'tab' => 'login',
				'type' => 'color',
				'label' => 'Primary light hover',
			],
			'--color-primary-light-text' => [
				'tab' => 'login',
				'type' => 'color',
				'label' => 'Primary light text',
			],
			'--color-primary-element-light' => [
				'tab' => 'login',
				'type' => 'color',
				'label' => 'Primary element light',
			],
			'--color-primary-element-light-text' => [
				'tab' => 'login',
				'type' => 'color',
				'label' => 'Primary element light text',
			],
			'--color-primary-element-light-hover' => [
				'tab' => 'login',
				'type' => 'color',
				'label' => 'Primary element light hover',
			],
		];
	}//end getTokens()
}//end class
