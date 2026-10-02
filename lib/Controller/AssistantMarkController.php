<?php

/**
 * Thematiq controller: the approved mark for the AI assistant.
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
 * @spec openspec/specs/assistant-approved-mark/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Controller;

use OCA\Thematiq\Service\AssistantMarkService;
use OCA\Thematiq\Service\Exception\AssistantMarkException;
use OCA\Thematiq\Settings\Admin;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IL10N;
use OCP\IRequest;

/**
 * `GET /api/assistant-mark` for signed-in users (the contract with the
 * assistant panel in nextcloud-vue), `GET` and `POST /settings/assistant-mark`
 * for administrators.
 *
 * @spec openspec/specs/assistant-approved-mark/spec.md
 */
class AssistantMarkController extends Controller {

	/**
	 * Constructor.
	 *
	 * @param string               $appName The app name.
	 * @param IRequest             $request The request.
	 * @param AssistantMarkService $mark    The mark service.
	 * @param IL10N                $l10n    Translations in the requesting user's language.
	 */
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly AssistantMarkService $mark,
		private readonly IL10N $l10n,
	) {
		parent::__construct(appName: $appName, request: $request);
	}//end __construct()

	/**
	 * The mark for the signed-in user's assistant panel. No session, no answer:
	 * the route has no public-page attribute, so Nextcloud refuses anonymous calls.
	 *
	 * @return JSONResponse `{enabled: false}` or `{enabled, label, organisation, logo}`.
	 *
	 * @no-admin-idor-exempt reads one instance-wide branding statement; the request names no object
	 *
	 * @spec openspec/specs/assistant-approved-mark/spec.md
	 */
	#[NoAdminRequired]
	public function show(): JSONResponse {
		return new JSONResponse($this->mark->forUser(l10n: $this->l10n));
	}//end show()

	/**
	 * The settings for the AI assistant block.
	 *
	 * @return JSONResponse The settings.
	 *
	 * @spec openspec/specs/assistant-approved-mark/spec.md
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function settings(): JSONResponse {
		return new JSONResponse($this->mark->getSettings());
	}//end settings()

	/**
	 * Save the settings.
	 *
	 * @param bool   $enabled      Whether the mark is on.
	 * @param string $organisation The organisation name, empty for the email footer name.
	 * @param string $logo         The logo URL, empty for the house style logo.
	 *
	 * @return JSONResponse The saved settings, or 422 with the reason.
	 *
	 * @spec openspec/specs/assistant-approved-mark/spec.md
	 *
	 * @SuppressWarnings(PHPMD.BooleanArgumentFlag) - the toggle value from the form.
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function save(bool $enabled = false, string $organisation = '', string $logo = ''): JSONResponse {
		try {
			$settings = $this->mark->save(enabled: $enabled, organisation: $organisation, logo: $logo);
		} catch (AssistantMarkException $e) {
			$message = $this->l10n->t('Enter the logo as an https address or a path on this server.');
			if ($e->getCode() === AssistantMarkService::ERROR_NAME) {
				$message = $this->l10n->t('Enter the organisation name before you turn on the approved mark.');
			}

			return new JSONResponse(['error' => $message], Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		return new JSONResponse($settings + ['preview' => $this->mark->forUser(l10n: $this->l10n)]);
	}//end save()
}//end class
