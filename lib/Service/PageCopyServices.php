<?php

/**
 * The collaborators that put the theme's own words on the login and not-found pages.
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
 * @spec openspec/specs/css-architecture/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * Each is optional: without one, Nextcloud's own words stay on that page.
 *
 * @spec openspec/specs/css-architecture/spec.md
 */
final class PageCopyServices {

	/**
	 * Constructor.
	 *
	 * @param LoginCopyService|null $loginCopy The words and the SSO button on the login page; null leaves Nextcloud's.
	 * @param NotFoundCopyService|null $notFoundCopy The words and links on the not-found page; null leaves Nextcloud's.
	 *
	 * @spec openspec/specs/css-architecture/spec.md
	 */
	public function __construct(
		public readonly ?LoginCopyService $loginCopy = null,
		public readonly ?NotFoundCopyService $notFoundCopy = null,
	) {
	}//end __construct()
}//end class
