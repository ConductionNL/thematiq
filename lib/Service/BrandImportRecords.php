<?php

/**
 * The collaborators that record a brand import.
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

use OCP\IConfig;

/**
 * The app config that holds the source records, and the audit trail.
 *
 * @spec openspec/specs/multi-brand-token-sources/spec.md#requirement-a-brand-is-a-custom-token-set-linked-to-its-source
 */
final class BrandImportRecords {

	/**
	 * Constructor.
	 *
	 * @param IConfig $config The source records.
	 * @param ThemingAuditService $audit The audit trail.
	 *
	 * @spec openspec/specs/multi-brand-token-sources/spec.md#requirement-a-brand-is-a-custom-token-set-linked-to-its-source
	 */
	public function __construct(
		public readonly IConfig $config,
		public readonly ThemingAuditService $audit,
	) {
	}//end __construct()
}//end class
