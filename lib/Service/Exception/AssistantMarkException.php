<?php

/**
 * Thrown when the approved mark settings cannot be saved.
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
 * @spec openspec/specs/assistant-approved-mark/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service\Exception;

use RuntimeException;

/**
 * The mark cannot be turned on without an organisation name, or the logo is not a usable address.
 *
 * @spec openspec/specs/assistant-approved-mark/spec.md
 */
class AssistantMarkException extends RuntimeException {
}//end class
