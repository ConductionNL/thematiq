<?php

/**
 * Thematiq controller: the status of the declarative configuration source.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Controller
 * @package   OCA\Thematiq
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 *
 * @spec openspec/specs/theme-as-code/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Controller;

use OCA\Thematiq\Service\ConfigSourceService;
use OCA\Thematiq\Settings\Admin;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * `GET /settings/config-source`: whether the house style is managed from
 * deployment configuration, the last applied revision, the last error, drift
 * and the lock, for the configuration bundle block.
 *
 * @spec openspec/specs/theme-as-code/spec.md
 */
class ConfigSourceController extends Controller {

	/**
	 * Constructor.
	 *
	 * @param string              $appName The app name.
	 * @param IRequest            $request The request.
	 * @param ConfigSourceService $source  The configuration source service.
	 */
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly ConfigSourceService $source,
	) {
		parent::__construct(appName: $appName, request: $request);
	}//end __construct()

	/**
	 * The status of the configuration source.
	 *
	 * @return JSONResponse `{managed, path?, locked?, revision?, appliedAt?, lastError?, drift?}`.
	 *
	 * @spec openspec/specs/theme-as-code/spec.md
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function status(): JSONResponse {
		return new JSONResponse($this->source->getStatus());
	}//end status()
}//end class
