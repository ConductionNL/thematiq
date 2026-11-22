<?php

/**
 * The addresses of the external apps in the apps menu.
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

use OCA\Thematiq\Service\ExternalApps;
use OCA\Thematiq\Settings\Admin;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IL10N;
use OCP\IRequest;

/**
 * Read and save the address of each external app, admin only.
 *
 * @spec openspec/changes/apps-menu-ordered-list/specs/external-apps-in-the-app-menu/spec.md#requirement-the-apps-menu-lists-the-external-apps-that-have-an-address
 */
class ExternalAppsController extends Controller {

	/**
	 * Constructor.
	 *
	 * @param string $appName The app name.
	 * @param IRequest $request The request.
	 * @param ExternalApps $externalApps Reads and saves the addresses.
	 * @param IL10N $l The messages.
	 */
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly ExternalApps $externalApps,
		private readonly IL10N $l,
	) {
		parent::__construct(appName: $appName, request: $request);
	}//end __construct()

	/**
	 * Every external app with its address.
	 *
	 * @return JSONResponse `{apps: [{key, name, line, url}]}`.
	 *
	 * @spec openspec/changes/apps-menu-ordered-list/specs/external-apps-in-the-app-menu/spec.md#requirement-the-apps-menu-lists-the-external-apps-that-have-an-address
	 */
	#[AuthorizedAdminSetting(settings: Admin::class)]
	public function show(): JSONResponse {
		return new JSONResponse(['apps' => $this->externalApps->stored(l: $this->l)]);
	}//end show()

	/**
	 * Save the addresses. One that is not http or https refuses the whole save.
	 *
	 * @param array<mixed> $urls The addresses by key.
	 *
	 * @return JSONResponse The stored apps, or 400 with the refused keys.
	 *
	 * @spec openspec/changes/apps-menu-ordered-list/specs/external-apps-in-the-app-menu/spec.md#requirement-the-apps-menu-lists-the-external-apps-that-have-an-address
	 */
	#[AuthorizedAdminSetting(settings: Admin::class)]
	public function save(array $urls = []): JSONResponse {
		$refused = $this->externalApps->save(urls: $urls);
		if ($refused !== []) {
			return new JSONResponse(
				['error' => $this->l->t('Enter an http or https address, or leave the field empty.'), 'refused' => $refused],
				Http::STATUS_BAD_REQUEST
			);
		}

		return $this->show();
	}//end save()
}//end class
