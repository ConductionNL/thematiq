<?php

/**
 * The collaborators that turn a token set into shell role values.
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
 * @spec openspec/changes/shell-tokens-endpoint/specs/shell-tokens/spec.md#requirement-another-product-reads-the-active-shell-tokens
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * The set's resolved declarations, the stock set's live values and the role mapper.
 *
 * @spec openspec/changes/shell-tokens-endpoint/specs/shell-tokens/spec.md#requirement-another-product-reads-the-active-shell-tokens
 */
final class ShellValueSources {

	/**
	 * Constructor.
	 *
	 * @param ShippedTokenSetAuditService $declarations Resolves the set's declarations.
	 * @param StockTokensService $stockTokens The stock set's values on this instance.
	 * @param ShellRoleMapper $mapper Maps the declarations onto the roles.
	 *
	 * @spec openspec/changes/shell-tokens-endpoint/specs/shell-tokens/spec.md#requirement-another-product-reads-the-active-shell-tokens
	 */
	public function __construct(
		public readonly ShippedTokenSetAuditService $declarations,
		public readonly StockTokensService $stockTokens,
		public readonly ShellRoleMapper $mapper,
	) {
	}//end __construct()
}//end class
