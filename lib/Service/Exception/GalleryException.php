<?php

/**
 * Thematiq Gallery Exception.
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
 * @spec openspec/specs/theme-gallery/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service\Exception;

/**
 * Thrown when a gallery install is refused: the entry is unknown (404), the file cannot be
 * downloaded (502), or it does not match the index or the upload path refuses it (422).
 * The message is translated and shown to the administrator as is; the code is the HTTP status.
 *
 * @spec openspec/specs/theme-gallery/spec.md
 */
class GalleryException extends \RuntimeException {
}//end class
