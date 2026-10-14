<?php

/**
 * Any token set as the OpenProject plugin's set file.
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
 * @spec openspec/changes/openproject-set-file/specs/openproject-set-file/spec.md#requirement-any-token-set-can-be-downloaded-as-the-plugins-set-file
 */

declare(strict_types=1);

namespace OCA\Thematiq\Controller;

use OCA\Thematiq\AppInfo\Application;
use OCA\Thematiq\Service\OpenProjectSetFileWriter;
use OCA\Thematiq\Service\RuntimeFile\RuntimeFileStore;
use OCA\Thematiq\Service\TokenSetService;
use OCA\Thematiq\Settings\Admin;
use OCP\App\IAppManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IL10N;
use OCP\IRequest;

/**
 * The set file of one set.
 *
 * @spec openspec/changes/openproject-set-file/specs/openproject-set-file/spec.md#requirement-any-token-set-can-be-downloaded-as-the-plugins-set-file
 */
class OpenProjectSetFileController extends Controller {

	/**
	 * Constructor.
	 *
	 * @param string $appName The app name.
	 * @param IRequest $request The request.
	 * @param TokenSetService $tokenSets Says which ids exist, and their names.
	 * @param IAppManager $appManager The app path.
	 * @param OpenProjectSetFileWriter $writer Writes the set file.
	 * @param IL10N $l The error text.
	 * @param RuntimeFileStore|null $store Where uploaded sets live; shipped sets come from the release.
	 */
	public function __construct(
		string $appName,
		IRequest $request,
		private TokenSetService $tokenSets,
		private IAppManager $appManager,
		private OpenProjectSetFileWriter $writer,
		private IL10N $l,
		private ?RuntimeFileStore $store = null,
	) {
		parent::__construct(appName: $appName, request: $request);
	}//end __construct()

	/**
	 * The set as the plugin's set file, `thematiq-{id}.json`.
	 *
	 * @param string $id The token set id.
	 *
	 * @return JSONResponse The set file with an attachment name, or 404.
	 *
	 * @spec openspec/changes/openproject-set-file/specs/openproject-set-file/spec.md#requirement-any-token-set-can-be-downloaded-as-the-plugins-set-file
	 */
	#[AuthorizedAdminSetting(settings: Admin::class)]
	public function export(string $id): JSONResponse {
		if (preg_match('/^[a-z0-9][a-z0-9-]*$/', $id) !== 1 || $this->tokenSets->isValidTokenSet(tokenSetId: $id) === false) {
			return new JSONResponse(['error' => $this->l->t('Token set not found.')], 404);
		}

		$name = $id;
		foreach ($this->tokenSets->getAvailableTokenSets() as $set) {
			if (($set['id'] ?? null) === $id) {
				$name = (string)($set['name'] ?? $id);
			}
		}

		$file = $this->writer->setFile(appPath: $this->appManager->getAppPath(Application::APP_ID), id: $id, name: $name, store: $this->store);
		$response = new JSONResponse($file);
		$response->addHeader('Content-Disposition', 'attachment; filename="thematiq-' . $id . '.json"');

		return $response;
	}//end export()
}//end class
