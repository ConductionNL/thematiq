<?php

/**
 * The workplace top bar parts the stylesheet cascade themes.
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
 * The workplace layout and the top bar parts; each optional, a missing one leaves that part as Nextcloud draws it.
 *
 * @spec openspec/specs/css-architecture/spec.md
 */
final class CssInjectionChrome {

	/**
	 * Constructor.
	 *
	 * @param LayoutOptionsService|null $layoutOptions Resolves the workplace layout and the brand stripe; null reads them from the context config.
	 * @param HeaderUserService|null $headerUser The name and role in the workplace top bar; null leaves the avatar alone.
	 * @param AppMenuService|null $appMenu The sections of the apps menu in the workplace top bar; null keeps one grid.
	 * @param SearchPlaceholderService|null $searchPlaceholder The words in the top bar's search field per app; null keeps Nextcloud's.
	 *
	 * @spec openspec/specs/css-architecture/spec.md
	 */
	public function __construct(
		public readonly ?LayoutOptionsService $layoutOptions = null,
		public readonly ?HeaderUserService $headerUser = null,
		public readonly ?AppMenuService $appMenu = null,
		public readonly ?SearchPlaceholderService $searchPlaceholder = null,
	) {
	}//end __construct()
}//end class
