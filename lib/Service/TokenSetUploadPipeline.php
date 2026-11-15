<?php

/**
 * The collaborators every uploaded token set runs through.
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
 * @spec openspec/specs/theme-gallery/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * The converter, the CSS whitelist and the CSS parser: the upload path's checks, in the order it runs them.
 *
 * @spec openspec/specs/theme-gallery/spec.md
 */
final class TokenSetUploadPipeline {

	/**
	 * Constructor.
	 *
	 * @param TokenSetConverterService $converter The converter every upload runs through.
	 * @param CustomTokenSetValidator $validator The CSS whitelist.
	 * @param CssParserService $cssParser The CSS parser.
	 *
	 * @spec openspec/specs/theme-gallery/spec.md
	 */
	public function __construct(
		public readonly TokenSetConverterService $converter,
		public readonly CustomTokenSetValidator $validator,
		public readonly CssParserService $cssParser,
	) {
	}//end __construct()
}//end class
