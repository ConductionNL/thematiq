<?php

/**
 * The collaborators the overrides endpoints read, write and record overrides through.
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
 * @spec openspec/specs/custom-css-overrides/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * The custom overrides service, the parser for an imported overrides file, and the audit trail every change is written to.
 *
 * @spec openspec/specs/custom-css-overrides/spec.md
 */
final class OverridesWrites {

	/**
	 * Constructor.
	 *
	 * @param CustomOverridesService $overridesService The custom overrides service.
	 * @param CssParserService $cssParser The CSS parser service.
	 * @param ThemingAuditService $auditService The theming audit trail service.
	 *
	 * @spec openspec/specs/custom-css-overrides/spec.md
	 */
	public function __construct(
		public readonly CustomOverridesService $overridesService,
		public readonly CssParserService $cssParser,
		public readonly ThemingAuditService $auditService,
	) {
	}//end __construct()
}//end class
