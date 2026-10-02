<?php

/**
 * NL Design Content Token Definitions.
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
 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-26
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * Content area tab token definitions.
 *
 * Content area colors including backgrounds, borders, border radii,
 * placeholders, and animation timings.
 *
 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-26
 */
class ContentTokens {
	/**
	 * Returns the content area tab tokens.
	 *
	 * @return array<string, array{tab: string, type: string, label: string}> Content tokens.
	 *
	 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-26
	 */
	public static function getTokens(): array {
		return [
			'--color-background-hover' => ['tab' => 'content', 'type' => 'color', 'label' => 'Background hover'],
			'--color-background-dark' => ['tab' => 'content', 'type' => 'color', 'label' => 'Background dark'],
			'--color-background-darker' => ['tab' => 'content', 'type' => 'color', 'label' => 'Background darker'],
			'--color-placeholder-light' => ['tab' => 'content', 'type' => 'color', 'label' => 'Placeholder light'],
			'--color-placeholder-dark' => ['tab' => 'content', 'type' => 'color', 'label' => 'Placeholder dark'],
			'--color-border' => ['tab' => 'content', 'type' => 'color', 'label' => 'Border color'],
			'--color-border-dark' => ['tab' => 'content', 'type' => 'color', 'label' => 'Border dark'],
			'--color-border-maxcontrast' => ['tab' => 'content', 'type' => 'color', 'label' => 'Border max contrast'],
			'--border-radius' => ['tab' => 'content', 'type' => 'text',  'label' => 'Border radius'],
			'--border-radius-small' => ['tab' => 'content', 'type' => 'text',  'label' => 'Border radius small'],
			'--border-radius-element' => ['tab' => 'content', 'type' => 'text',  'label' => 'Border radius element'],
			'--border-radius-large' => ['tab' => 'content', 'type' => 'text',  'label' => 'Border radius large'],
			'--border-radius-rounded' => ['tab' => 'content', 'type' => 'text',  'label' => 'Border radius rounded'],
			'--border-radius-pill' => ['tab' => 'content', 'type' => 'text',  'label' => 'Border radius pill'],
			'--body-container-radius' => ['tab' => 'content', 'type' => 'text',  'label' => 'Body container radius'],
			'--animation-quick' => ['tab' => 'content', 'type' => 'text',  'label' => 'Animation quick'],
			'--animation-slow' => ['tab' => 'content', 'type' => 'text',  'label' => 'Animation slow'],
		];
	}//end getTokens()
}//end class
