<?php

/**
 * OpenProject's address and modules for the apps menu.
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
 */

declare(strict_types=1);

namespace OCA\Thematiq\Controller;

use OCA\Thematiq\Service\OpenProjectModules;
use OCA\Thematiq\Settings\Admin;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IL10N;
use OCP\IRequest;

/**
 * Read and save `openproject_url` and `openproject_modules`.
 *
 * @spec openspec/changes/openproject-in-the-app-menu/specs/openproject-in-the-app-menu/spec.md#requirement-the-apps-menu-lists-openprojects-modules
 */
class OpenProjectLinkController extends Controller {

	/**
	 * Constructor.
	 *
	 * @param string $appName The app name.
	 * @param IRequest $request The request.
	 * @param OpenProjectModules $modules Reads and saves the address and the modules.
	 * @param IL10N $l The messages.
	 */
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly OpenProjectModules $modules,
		private readonly IL10N $l,
	) {
		parent::__construct(appName: $appName, request: $request);
	}//end __construct()

	/**
	 * The stored address and the modules the menu lists.
	 *
	 * @return JSONResponse `{url, modules, available}`.
	 *
	 * @spec openspec/changes/openproject-in-the-app-menu/specs/openproject-in-the-app-menu/spec.md#requirement-the-apps-menu-lists-openprojects-modules
	 */
	#[AuthorizedAdminSetting(settings: Admin::class)]
	public function show(): JSONResponse {
		return new JSONResponse($this->modules->stored());
	}//end show()

	/**
	 * Save the address, and the modules when they are given. An empty address removes the section.
	 *
	 * @param string $url OpenProject's address, or empty.
	 * @param string|null $modules The module keys, comma separated, or null to leave them.
	 *
	 * @return JSONResponse The stored values, or 400 for an address that is not http or https.
	 *
	 * @spec openspec/changes/openproject-in-the-app-menu/specs/openproject-in-the-app-menu/spec.md#requirement-the-apps-menu-lists-openprojects-modules
	 */
	#[AuthorizedAdminSetting(settings: Admin::class)]
	public function save(string $url = '', ?string $modules = null): JSONResponse {
		if ($this->modules->save(url: $url, modules: $modules) === false) {
			return new JSONResponse(['error' => $this->l->t('Enter an http or https address, or leave the field empty.')], 400);
		}

		return $this->show();
	}//end save()
}//end class
