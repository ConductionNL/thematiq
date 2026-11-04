<?php

/**
 * The collaborators that turn one brand of a source into a checked token set file.
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
 * @spec openspec/specs/multi-brand-token-sources/spec.md#requirement-a-brand-is-a-custom-token-set-linked-to-its-source
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * The source that finds and cuts brands, the converter, the CSS whitelist and the parser that reads the result.
 *
 * @spec openspec/specs/multi-brand-token-sources/spec.md#requirement-a-brand-is-a-custom-token-set-linked-to-its-source
 */
final class BrandConversion {

	/**
	 * Constructor.
	 *
	 * @param MultiBrandSource $source Finds and cuts brands.
	 * @param TokenSetConverterService $converter Converts one brand.
	 * @param CustomTokenSetValidator $validator Judges the converted file.
	 * @param CssParserService $cssParser Reads the converted file.
	 *
	 * @spec openspec/specs/multi-brand-token-sources/spec.md#requirement-a-brand-is-a-custom-token-set-linked-to-its-source
	 */
	public function __construct(
		public readonly MultiBrandSource $source,
		public readonly TokenSetConverterService $converter,
		public readonly CustomTokenSetValidator $validator,
		public readonly CssParserService $cssParser,
	) {
	}//end __construct()
}//end class
