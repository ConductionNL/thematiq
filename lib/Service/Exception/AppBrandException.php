<?php

/**
 * Thrown when an app brand cannot be saved.
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
 * @spec openspec/specs/per-app-theming/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service\Exception;

use RuntimeException;

/**
 * The code is the HTTP status: 404 unknown app, 413 too large, 422 refused.
 *
 * @spec openspec/specs/per-app-theming/spec.md
 */
class AppBrandException extends RuntimeException {

	/**
	 * A protected app (thematiq, settings, theming).
	 *
	 * @var string
	 */
	public const PROTECTED = 'protected';

	/**
	 * An app that is not installed.
	 *
	 * @var string
	 */
	public const NOT_INSTALLED = 'not-installed';

	/**
	 * An app excluded from theming.
	 *
	 * @var string
	 */
	public const EXCLUDED = 'excluded';

	/**
	 * A token set that is not available.
	 *
	 * @var string
	 */
	public const UNKNOWN_SET = 'unknown-set';

	/**
	 * A logo that is not an accepted image.
	 *
	 * @var string
	 */
	public const BAD_IMAGE = 'bad-image';

	/**
	 * A logo over the size limit.
	 *
	 * @var string
	 */
	public const TOO_LARGE = 'too-large';

	/**
	 * Constructor.
	 *
	 * @param string $reason One of the reason constants.
	 * @param int $status The HTTP status.
	 */
	public function __construct(
		public readonly string $reason,
		int $status = 422,
	) {
		parent::__construct(message: $reason, code: $status);
	}//end __construct()
}//end class
