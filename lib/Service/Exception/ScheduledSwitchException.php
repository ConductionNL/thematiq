<?php

/**
 * Thematiq Scheduled Switch Exception.
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
 * @spec openspec/specs/scheduled-switch/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service\Exception;

/**
 * Thrown when a planned theme switch is refused: its token set does not exist, a time does
 * not parse, its end is not after its start, or its window overlaps another planned switch.
 * The message is translated and shown to the administrator as is.
 *
 * @spec openspec/specs/scheduled-switch/spec.md
 */
class ScheduledSwitchException extends \RuntimeException {
}//end class
