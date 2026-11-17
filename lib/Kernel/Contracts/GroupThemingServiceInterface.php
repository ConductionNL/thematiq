<?php

/**
 * Contract of GroupThemingService
 *
 * The public surface of GroupThemingService that other components call, so they depend
 * on this contract instead of the implementation.
 *
 * @category  Kernel
 * @package   OCA\Thematiq\Kernel\Contracts
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT: <git_id>
 * @link      https://github.com/ConductionNL/thematiq
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Thematiq\Kernel\Contracts;

/**
 * Contract of GroupThemingService.
 *
 * @category Kernel
 * @package  OCA\Thematiq\Kernel\Contracts
 * @author   Conduction b.v. <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://github.com/ConductionNL/thematiq
 *
 * @spec exclude Structural contract, no functional requirement.
 */
interface GroupThemingServiceInterface {

	/**
	 * Get the stored mapping in priority order.
	 *
	 * An absent or malformed stored value reads as an empty mapping — never
	 * an error — reproducing today's global-theming behavior exactly.
	 *
	 * @return array<int, array{group: string, tokenSet: string, delegated?: true, allowedTokenSets?: list<string>}>
	 *                                                                                                               The ordered mapping.
	 *
	 * @spec openspec/specs/per-group-theming/spec.md
	 */
	public function getMapping(): array;

	/**
	 * Resolve the effective token set for the current request.
	 *
	 * Wrapped fail-open to the instance default set — presentation, never
	 * security, mirroring `Application::isThemingDisabled()`'s catch-all: a
	 * broken group backend, cache, or malformed stored mapping must never
	 * brick theming or escape into `Application::boot()`.
	 *
	 * On a page of a branded app the caller passes the app's set: it wins over
	 * the group mapping, an admin preview still wins over it, and a sessionless
	 * page ignores it (openspec/specs/per-app-theming/spec.md).
	 *
	 * @param string|null $appBrandSet The token set of the rendered app's brand, or null.
	 *
	 * @return string The resolved token set id.
	 *
	 * @spec openspec/specs/per-group-theming/spec.md
	 */
	public function resolveTokenSetForRequest(?string $appBrandSet = null): string;
}//end interface
