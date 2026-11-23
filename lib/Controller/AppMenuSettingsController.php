<?php

/**
 * The apps menu settings: a person's own, the defaults for everyone, and the uploaded icons.
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

use InvalidArgumentException;
use OCA\Thematiq\Service\AppMenuDialogLabels;
use OCA\Thematiq\Service\AppMenuExternalApp;
use OCA\Thematiq\Service\AppMenuIcons;
use OCA\Thematiq\Service\AppMenuKeys;
use OCA\Thematiq\Service\AppMenuSettings;
use OCA\Thematiq\Settings\Admin;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserSession;

/**
 * The Menu settings dialog's server side. A person reads the settings and writes only their own
 * personal keys; the defaults for everyone are an administrator's. Every value is checked before
 * anything is stored.
 *
 * @SuppressWarnings(PHPMD.StaticAccess) - AppMenuExternalApp holds the external app rule.
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) - one controller for the dialog's endpoints.
 *
 * @spec openspec/changes/apps-menu-create-pins-settings/specs/apps-menu-settings/spec.md#requirement-menu-settings-are-checked-before-they-are-stored
 */
class AppMenuSettingsController extends Controller {

	/**
	 * Constructor.
	 *
	 * @param string $appName The app name.
	 * @param IRequest $request The request.
	 * @param AppMenuSettings $settings Reads and saves the settings.
	 * @param AppMenuKeys $keys The entries the person can see.
	 * @param AppMenuIcons $icons Keeps the uploaded icons.
	 * @param IUserSession $userSession The signed-in person.
	 * @param IGroupManager $groupManager Whether the person is an administrator.
	 * @param IURLGenerator $urls Builds the Manage apps address.
	 * @param IL10N $l The language of this request.
	 */
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly AppMenuSettings $settings,
		private readonly AppMenuKeys $keys,
		private readonly AppMenuIcons $icons,
		private readonly IUserSession $userSession,
		private readonly IGroupManager $groupManager,
		private readonly IURLGenerator $urls,
		private readonly IL10N $l,
	) {
		parent::__construct(appName: $appName, request: $request);
	}//end __construct()

	/**
	 * What the dialog opens with: the effective settings and the person's own keys; for an
	 * administrator also the defaults for everyone and the Manage apps address.
	 *
	 * @return JSONResponse `{labels, library, iconUrl, admin, effective, overrides, defaults?, manageUrl?}`,
	 *                      or 401 without a session.
	 *
	 * @spec openspec/changes/apps-menu-create-pins-settings/specs/apps-menu-settings/spec.md#requirement-menu-settings-are-admin-defaults-with-per-user-overrides
	 */
	#[NoAdminRequired]
	public function show(): JSONResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new JSONResponse(['error' => 'unauthorized'], Http::STATUS_UNAUTHORIZED);
		}

		$admin = $this->groupManager->isAdmin($user->getUID());
		$data = [
			'labels' => AppMenuDialogLabels::labels(l: $this->l),
			'library' => AppMenuExternalApp::LIBRARY,
			'iconUrl' => $this->urls->linkToRoute('thematiq.appMenuSettings.icon', ['id' => '__id__']),
			'admin' => $admin,
			'effective' => $this->settings->effective(userId: $user->getUID()),
			'overrides' => $this->settings->overrides(userId: $user->getUID()),
		];
		if ($admin === true) {
			$data['defaults'] = array_merge(AppMenuSettings::DEFAULTS, $this->settings->defaults());
			$data['manageUrl'] = $this->urls->linkToRoute('settings.AppSettings.viewApps');
		}

		return new JSONResponse($data);
	}//end show()

	/**
	 * Store the signed-in person's own keys: `order`, `pinned`, `hidden` and `externalApps`. Any
	 * other key is refused; a key sent as null follows the defaults again.
	 *
	 * @param array<mixed> $settings The values, by setting.
	 *
	 * @return JSONResponse `{effective}`, 400 naming the refused setting, or 401 without a session.
	 *
	 * @no-admin-idor-exempt writes only the signed-in person's own user values, keyed by the session's uid; it takes no object id.
	 *
	 * @spec openspec/changes/apps-menu-create-pins-settings/specs/apps-menu-settings/spec.md#requirement-menu-settings-are-checked-before-they-are-stored
	 */
	#[NoAdminRequired]
	public function save(array $settings = []): JSONResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new JSONResponse(['error' => 'unauthorized'], Http::STATUS_UNAUTHORIZED);
		}

		$uid = $user->getUID();
		try {
			$settings = self::withIds(values: $settings);
			$this->settings->saveOverrides(userId: $uid, values: $settings, known: $this->known(userId: $uid, values: $settings));
		} catch (InvalidArgumentException $e) {
			return new JSONResponse(['error' => 'invalid', 'field' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}

		return new JSONResponse(['effective' => $this->settings->effective(userId: $uid)]);
	}//end save()

	/**
	 * Store the defaults for everyone.
	 *
	 * @param array<mixed> $settings The values, by setting.
	 *
	 * @return JSONResponse `{defaults}`, or 400 naming the refused setting.
	 *
	 * @spec openspec/changes/apps-menu-create-pins-settings/specs/apps-menu-settings/spec.md#requirement-menu-settings-are-checked-before-they-are-stored
	 */
	#[AuthorizedAdminSetting(settings: Admin::class)]
	public function saveDefaults(array $settings = []): JSONResponse {
		$uid = $this->userSession->getUser()?->getUID();
		try {
			$settings = self::withIds(values: $settings);
			$this->settings->saveDefaults(values: $settings, known: $this->known(userId: $uid, values: $settings));
		} catch (InvalidArgumentException $e) {
			return new JSONResponse(['error' => 'invalid', 'field' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}

		return new JSONResponse(['defaults' => array_merge(AppMenuSettings::DEFAULTS, $this->settings->defaults())]);
	}//end saveDefaults()

	/**
	 * Remove the signed-in person's own keys.
	 *
	 * @return JSONResponse `{effective}`, or 401 without a session.
	 *
	 * @spec openspec/changes/apps-menu-create-pins-settings/specs/apps-menu-settings/spec.md#requirement-reset-to-default
	 */
	#[NoAdminRequired]
	public function reset(): JSONResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new JSONResponse(['error' => 'unauthorized'], Http::STATUS_UNAUTHORIZED);
		}

		$this->settings->resetOverrides(userId: $user->getUID());

		return new JSONResponse(['effective' => $this->settings->effective(userId: $user->getUID())]);
	}//end reset()

	/**
	 * Restore the shipped defaults for everyone.
	 *
	 * @return JSONResponse `{defaults}`.
	 *
	 * @spec openspec/changes/apps-menu-create-pins-settings/specs/apps-menu-settings/spec.md#requirement-reset-to-default
	 */
	#[AuthorizedAdminSetting(settings: Admin::class)]
	public function resetDefaults(): JSONResponse {
		$this->settings->resetDefaults();

		return new JSONResponse(['defaults' => AppMenuSettings::DEFAULTS]);
	}//end resetDefaults()

	/**
	 * Keep an uploaded icon for an external app.
	 *
	 * @return JSONResponse `{id}`, 400 without a file, or 422 for a file that is not a PNG or a safe SVG of at most 100 kB.
	 *
	 * @no-admin-idor-exempt stores new bytes under the hash of their content; it reads or changes no existing object.
	 *
	 * @spec openspec/changes/apps-menu-create-pins-settings/specs/apps-menu-settings/spec.md#requirement-external-apps-are-checked-on-the-server
	 */
	#[NoAdminRequired]
	public function uploadIcon(): JSONResponse {
		$file = $this->request->getUploadedFile('icon');
		if (empty($file) === true || isset($file['tmp_name']) === false || ($file['error'] ?? 0) !== 0) {
			return new JSONResponse(['error' => 'no_file'], Http::STATUS_BAD_REQUEST);
		}

		$id = $this->icons->store(bytes: (string)file_get_contents($file['tmp_name']));
		if ($id === null) {
			return new JSONResponse(['error' => 'bad_image'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		return new JSONResponse(['id' => $id]);
	}//end uploadIcon()

	/**
	 * Serve an uploaded icon to a signed-in person, as an image that can run nothing.
	 *
	 * @param string $id The icon's id.
	 *
	 * @return Response The icon, or 404.
	 *
	 * @no-admin-idor-exempt icons are shared, inert images named by the hash of their bytes, shown in everyone's menu.
	 *
	 * @spec openspec/changes/apps-menu-create-pins-settings/specs/apps-menu-settings/spec.md#requirement-external-apps-are-checked-on-the-server
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function icon(string $id): Response {
		$image = $this->icons->read(id: $id);
		if ($image === null) {
			return new Response(Http::STATUS_NOT_FOUND);
		}

		$response = new DataDisplayResponse($image['bytes'], Http::STATUS_OK, ['Content-Type' => $image['mime']]);
		$response->addHeader('X-Content-Type-Options', 'nosniff');
		$response->addHeader('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; img-src data:");
		$response->addHeader('Cache-Control', 'private, max-age=31536000, immutable');

		return $response;
	}//end icon()

	/**
	 * The values with every external app checked once, so a new app keeps the id it gets here
	 * when `order`, `pinned` or `hidden` name it.
	 *
	 * @param array<mixed> $values The values as sent.
	 *
	 * @return array<mixed> The values.
	 *
	 * @throws InvalidArgumentException When an external app is not allowed.
	 *
	 * @spec openspec/changes/apps-menu-create-pins-settings/specs/apps-menu-settings/spec.md#requirement-external-apps-are-checked-on-the-server
	 */
	private static function withIds(array $values): array {
		if (array_key_exists('externalApps', $values) === false || $values['externalApps'] === null) {
			return $values;
		}

		$apps = null;
		if (is_array($values['externalApps']) === true) {
			$apps = AppMenuExternalApp::cleanList(apps: $values['externalApps']);
		}

		if ($apps === null) {
			throw new InvalidArgumentException('externalApps');
		}

		$values['externalApps'] = $apps;

		return $values;
	}//end withIds()

	/**
	 * The entry keys a list may name: what the person sees, and the external apps being saved.
	 *
	 * @param string|null $userId The person.
	 * @param array<mixed> $values The checked values.
	 *
	 * @return array<int, string> The keys.
	 */
	private function known(?string $userId, array $values): array {
		$known = $this->keys->known(userId: $userId);
		foreach (($values['externalApps'] ?? []) as $app) {
			$known[] = AppMenuExternalApp::key(app: $app);
		}

		return $known;
	}//end known()
}//end class
