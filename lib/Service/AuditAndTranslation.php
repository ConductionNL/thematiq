<?php

/**
 * The audit trail and the translator a controller answers and records with.
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

use OCP\IL10N;

/**
 * The audit trail every change is written to, and the translator for the answers.
 *
 * @spec openspec/specs/custom-token-sets/spec.md
 */
final class AuditAndTranslation {

	/**
	 * Constructor.
	 *
	 * @param ThemingAuditService $audit The theming audit log.
	 * @param IL10N $l Translations.
	 *
	 * @spec openspec/specs/custom-token-sets/spec.md
	 */
	public function __construct(
		public readonly ThemingAuditService $audit,
		public readonly IL10N $l,
	) {
	}//end __construct()
}//end class
