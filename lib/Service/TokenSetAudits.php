<?php

/**
 * The audits the token set list reports per set.
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
 * @spec openspec/specs/token-sets/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * The shipped set contrast audit, the vocabulary completeness audit and the typeface audit.
 *
 * @spec openspec/specs/token-sets/spec.md
 */
final class TokenSetAudits {

	/**
	 * Constructor.
	 *
	 * @param ShippedTokenSetAuditService $audit The shipped-set contrast audit service.
	 * @param TokenSetVocabularyAuditService $vocabularyAudit The vocabulary-completeness audit service.
	 * @param TokenSetFontAuditService $fontAudit The typeface audit service.
	 *
	 * @spec openspec/specs/token-sets/spec.md
	 */
	public function __construct(
		public readonly ShippedTokenSetAuditService $audit,
		public readonly TokenSetVocabularyAuditService $vocabularyAudit,
		public readonly TokenSetFontAuditService $fontAudit = new TokenSetFontAuditService(),
	) {
	}//end __construct()
}//end class
