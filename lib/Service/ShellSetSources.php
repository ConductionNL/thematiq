<?php

/**
 * The collaborators that say which token set the shell wears.
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
 * The active set, whether it still exists, its name and the administrator's own values.
 *
 * @spec openspec/changes/shell-tokens-endpoint/specs/shell-tokens/spec.md#requirement-another-product-reads-the-active-shell-tokens
 */
final class ShellSetSources {

	/**
	 * Constructor.
	 *
	 * @param ActiveTokenSetService $activeSet Says which set is active.
	 * @param TokenSetService $tokenSets Says whether that set still exists, and where the app is.
	 * @param DesignSystemService $designSystems The set's name.
	 * @param CustomOverridesService $overrides The administrator's own values.
	 *
	 * @spec openspec/changes/shell-tokens-endpoint/specs/shell-tokens/spec.md#requirement-another-product-reads-the-active-shell-tokens
	 */
	public function __construct(
		public readonly ActiveTokenSetService $activeSet,
		public readonly TokenSetService $tokenSets,
		public readonly DesignSystemService $designSystems,
		public readonly CustomOverridesService $overrides,
	) {
	}//end __construct()
}//end class
