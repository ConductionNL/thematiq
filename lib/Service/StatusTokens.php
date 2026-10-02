<?php

/**
 * NL Design Status Token Definitions.
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
 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-40
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * Status and feedback tab token definitions.
 *
 * Status and feedback colors including error, warning, success, info,
 * and semantic border/element variants.
 *
 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-40
 */
class StatusTokens {
	/**
	 * Returns the status and feedback tab tokens.
	 *
	 * @return array<string, array{tab: string, type: string, label: string}> Status tokens.
	 *
	 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-40
	 */
	public static function getTokens(): array {
		return [
			'--color-error' => ['tab' => 'status', 'type' => 'color', 'label' => 'Error color'],
			'--color-error-hover' => ['tab' => 'status', 'type' => 'color', 'label' => 'Error hover'],
			'--color-error-rgb' => ['tab' => 'status', 'type' => 'text',  'label' => 'Error color (RGB)'],
			'--color-element-error' => ['tab' => 'status', 'type' => 'color', 'label' => 'Element error'],
			'--color-border-error' => ['tab' => 'status', 'type' => 'color', 'label' => 'Border error'],
			'--color-warning' => ['tab' => 'status', 'type' => 'color', 'label' => 'Warning color'],
			'--color-warning-rgb' => ['tab' => 'status', 'type' => 'text',  'label' => 'Warning color (RGB)'],
			'--color-element-warning' => ['tab' => 'status', 'type' => 'color', 'label' => 'Element warning'],
			'--color-success' => ['tab' => 'status', 'type' => 'color', 'label' => 'Success color'],
			'--color-success-rgb' => ['tab' => 'status', 'type' => 'text',  'label' => 'Success color (RGB)'],
			'--color-element-success' => ['tab' => 'status', 'type' => 'color', 'label' => 'Element success'],
			'--color-border-success' => ['tab' => 'status', 'type' => 'color', 'label' => 'Border success'],
			'--color-info' => ['tab' => 'status', 'type' => 'color', 'label' => 'Info color'],
			'--color-element-info' => ['tab' => 'status', 'type' => 'color', 'label' => 'Element info'],
			'--color-favorite' => ['tab' => 'status', 'type' => 'color', 'label' => 'Favorite (star) color'],
		];
	}//end getTokens()
}//end class
