<?php

/**
 * Thrown when a delegated group house style choice is not allowed.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Exception
 * @package   OCA\Thematiq
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 *
 * @spec openspec/specs/per-group-theming/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service\Exception;

use RuntimeException;

/**
 * The user is not a subadmin of the group, the group is not delegated, or the
 * set is not on the group's allowed list. The controller answers 403.
 *
 * @spec openspec/specs/per-group-theming/spec.md
 */
class DelegationRefusedException extends RuntimeException {
}//end class
