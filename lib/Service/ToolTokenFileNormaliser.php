<?php

/**
 * Turns a token file written by a design tool into a document the converter reads.
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
 * @spec openspec/changes/nlds-theme-converter/specs/token-set-converter/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * The OpenProject plugin's set file, then a file from a Figma tool: token sets merged, the `value`
 * spelling respelled, thematiq's own paths given their CSS names back. Any other content is untouched.
 *
 * @spec openspec/changes/nlds-theme-converter/specs/token-set-converter/spec.md
 */
final class ToolTokenFileNormaliser {

	/**
	 * Normalise one uploaded document.
	 *
	 * @param string $content The uploaded content.
	 * @param array<int, array{path: string, reason: string}> $skipped What was left out and why, appended to.
	 *
	 * @return string The rewritten document, or the content itself when no tool wrote it.
	 *
	 * @spec openspec/changes/nlds-theme-converter/specs/token-set-converter/spec.md
	 */
	public function normalise(string $content, array &$skipped): string {
		$content = (new OpenProjectSetFile())->normalise(content: $content, skipped: $skipped);

		return (new FigmaTokenReader())->normalise(content: $content, skipped: $skipped);
	}//end normalise()
}//end class
