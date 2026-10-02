<?php

/**
 * NL Design Typography Token Definitions.
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
 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-54
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * Typography tab token definitions.
 *
 * Text colors and font family settings.
 *
 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-54
 */
class TypographyTokens {
	/**
	 * Returns the typography tab tokens.
	 *
	 * @return array<string, array{tab: string, type: string, label: string}> Typography tokens.
	 *
	 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-54
	 */
	public static function getTokens(): array {
		return [
			'--color-main-text' => ['tab' => 'typography', 'type' => 'color', 'label' => 'Main text color'],
			'--color-text-maxcontrast' => ['tab' => 'typography', 'type' => 'color', 'label' => 'Text max contrast'],
			'--color-text-light' => ['tab' => 'typography', 'type' => 'color', 'label' => 'Text light'],
			'--color-text-lighter' => ['tab' => 'typography', 'type' => 'color', 'label' => 'Text lighter'],
			'--color-text-error' => ['tab' => 'typography', 'type' => 'color', 'label' => 'Text error'],
			'--color-text-success' => ['tab' => 'typography', 'type' => 'color', 'label' => 'Text success'],
			'--color-text-warning' => ['tab' => 'typography', 'type' => 'color', 'label' => 'Text warning'],
			'--font-face' => ['tab' => 'typography', 'type' => 'text',  'label' => 'Font family'],
		];
	}//end getTokens()
}//end class
