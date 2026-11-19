<?php

/**
 * The collaborators that measure colour contrast in a stylesheet.
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
 * @spec openspec/specs/compliance-evidence/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * The contrast maths and the custom property parser, which every contrast report uses together.
 *
 * @spec openspec/specs/compliance-evidence/spec.md
 */
final class ContrastTools {

	/**
	 * Constructor.
	 *
	 * @param ContrastService $contrast The WCAG contrast maths.
	 * @param CssParserService $cssParser The shared custom property parser.
	 *
	 * @spec openspec/specs/compliance-evidence/spec.md
	 */
	public function __construct(
		public readonly ContrastService $contrast,
		public readonly CssParserService $cssParser,
	) {
	}//end __construct()
}//end class
