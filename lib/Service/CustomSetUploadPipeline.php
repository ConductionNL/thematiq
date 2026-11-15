<?php

/**
 * The collaborators an uploaded token set runs through before it is stored.
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
 * @spec openspec/specs/custom-token-sets/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * The custom set storage, the CSS whitelist, the CSS parser and the converter that runs before the whitelist.
 *
 * @spec openspec/specs/custom-token-sets/spec.md
 */
final class CustomSetUploadPipeline {

	/**
	 * Constructor.
	 *
	 * @param CustomTokenSetService $service The storage/lifecycle service.
	 * @param CustomTokenSetValidator $validator The CSS validator.
	 * @param CssParserService $cssParser The CSS parser service.
	 * @param TokenSetConverterService $converter The theme converter, which runs before the validator.
	 *
	 * @spec openspec/specs/custom-token-sets/spec.md
	 */
	public function __construct(
		public readonly CustomTokenSetService $service,
		public readonly CustomTokenSetValidator $validator,
		public readonly CssParserService $cssParser,
		public readonly TokenSetConverterService $converter,
	) {
	}//end __construct()
}//end class
