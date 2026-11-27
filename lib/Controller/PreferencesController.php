<?php

/**
 * The signed-in person's walkthrough preferences.
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

use OCA\Thematiq\Service\WalkthroughPreferences;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * The nextcloud-vue preference contract (`GET` / `PUT /api/preferences/{key}`, answering `{value}`)
 * for the two keys of the apps menu tour. The routes take no user id: a person reads and writes only
 * their own values. Any other key answers 404.
 *
 * @spec openspec/changes/apps-menu-walkthrough/specs/apps-menu-walkthrough/spec.md#requirement-finishing-or-closing-records-the-seen-version-req-tqwt-004
 */
class PreferencesController extends Controller {

	/**
	 * Constructor.
	 *
	 * @param string $appName The app name.
	 * @param IRequest $request The request.
	 * @param WalkthroughPreferences $preferences Reads and writes the tour keys.
	 * @param IUserSession $userSession The signed-in user.
	 */
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly WalkthroughPreferences $preferences,
		private readonly IUserSession $userSession,
	) {
		parent::__construct(appName: $appName, request: $request);
	}//end __construct()

	/**
	 * A tour key's value for the signed-in person.
	 *
	 * @param string $key The key.
	 *
	 * @return JSONResponse `{value}` (null when none is stored), 404 for another key, 401 without a user.
	 *
	 * @spec openspec/changes/apps-menu-walkthrough/specs/apps-menu-walkthrough/spec.md#requirement-finishing-or-closing-records-the-seen-version-req-tqwt-004
	 */
	#[NoAdminRequired]
	public function show(string $key): JSONResponse {
		if ($this->preferences->serves(key: $key) === false) {
			return new JSONResponse(['message' => 'Not found'], Http::STATUS_NOT_FOUND);
		}

		$user = $this->userSession->getUser();
		if ($user === null) {
			return new JSONResponse(['value' => null], Http::STATUS_UNAUTHORIZED);
		}

		return new JSONResponse(['value' => $this->preferences->get(userId: $user->getUID(), key: $key)]);
	}//end show()

	/**
	 * Store a tour key's value for the signed-in person; an empty value removes it.
	 *
	 * @param string $key The key.
	 * @param mixed $value The value: a string, or a scalar read as one.
	 *
	 * @return JSONResponse `{value}` as stored, 404 for another key, 400 for a value that is not text, 401 without a user.
	 *
	 * @spec openspec/changes/apps-menu-walkthrough/specs/apps-menu-walkthrough/spec.md#requirement-finishing-or-closing-records-the-seen-version-req-tqwt-004
	 */
	#[NoAdminRequired]
	public function update(string $key, mixed $value = null): JSONResponse {
		if ($this->preferences->serves(key: $key) === false) {
			return new JSONResponse(['message' => 'Not found'], Http::STATUS_NOT_FOUND);
		}

		$user = $this->userSession->getUser();
		if ($user === null) {
			return new JSONResponse(['value' => null], Http::STATUS_UNAUTHORIZED);
		}

		if ($value !== null && is_scalar($value) === false) {
			return new JSONResponse(['message' => 'The value must be text.'], Http::STATUS_BAD_REQUEST);
		}

		$stored = $this->preferences->set(userId: $user->getUID(), key: $key, value: (string)$value);
		return new JSONResponse(['value' => $stored]);
	}//end update()
}//end class
