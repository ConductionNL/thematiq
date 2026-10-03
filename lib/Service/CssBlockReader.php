<?php

/**
 * Thematiq CSS block reader.
 *
 * The top-level rule blocks of a stylesheet with their custom properties, for finding
 * brand classes in built theme CSS. Comments are removed; at-rules are skipped.
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
 * @spec openspec/specs/multi-brand-token-sources/spec.md#requirement-a-multi-brand-source-is-recognised-by-its-content
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * Selector blocks of a stylesheet.
 *
 * @spec openspec/specs/multi-brand-token-sources/spec.md#requirement-a-multi-brand-source-is-recognised-by-its-content
 */
class CssBlockReader {

	/**
	 * The top-level rule blocks of a stylesheet, comments removed; at-rules are skipped.
	 *
	 * @param string $css The stylesheet.
	 *
	 * @return array<int, array{selector: string, declarations: array<string, string>}>
	 *
	 * @spec openspec/specs/multi-brand-token-sources/spec.md#requirement-a-multi-brand-source-is-recognised-by-its-content
	 */
	public function blocks(string $css): array {
		$css = (string)preg_replace('#/\*.*?\*/#s', '', $css);
		preg_match_all('/([^{}@;]+)\{([^{}]*)\}/', $css, $matches, PREG_SET_ORDER);
		$blocks = [];
		foreach ($matches as $match) {
			preg_match_all('/(--[A-Za-z0-9_-]+)\s*:\s*([^;]+);?/', $match[2], $declarations, PREG_SET_ORDER);
			$map = [];
			foreach ($declarations as $declaration) {
				$map[$declaration[1]] = trim($declaration[2]);
			}

			$blocks[] = ['selector' => trim($match[1]), 'declarations' => $map];
		}

		return $blocks;
	}//end blocks()
}//end class
