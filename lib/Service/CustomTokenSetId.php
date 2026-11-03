<?php

/**
 * The id format of an uploaded token set.
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
 * An uploaded set's id is its slug behind one fixed prefix, so it can never take a shipped set's id.
 *
 * @spec openspec/specs/custom-token-sets/spec.md
 */
final class CustomTokenSetId {

	/**
	 * The id prefix every custom set carries.
	 *
	 * @var string
	 */
	public const PREFIX = 'custom-';
}//end class
