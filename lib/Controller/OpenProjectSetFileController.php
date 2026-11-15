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
 * @spec openspec/specs/openproject-set-file/spec.md#requirement-any-token-set-can-be-downloaded-as-the-plugins-set-file
 */

declare(strict_types=1);

namespace OCA\Thematiq\Controller;

use OCA\Thematiq\AppInfo\Application;
use OCA\Thematiq\Service\OpenProjectSetFileOutput;
use OCA\Thematiq\Service\TokenSetFileLocation;
use OCA\Thematiq\Settings\Admin;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * The set file of one set.
 *
 * @spec openspec/specs/openproject-set-file/spec.md#requirement-any-token-set-can-be-downloaded-as-the-plugins-set-file
 */
class OpenProjectSetFileController extends Controller {

	/**
	 * Constructor.
	 *
	 * @param string $appName The app name.
	 * @param IRequest $request The request.
	 * @param TokenSetFileLocation $location The token set list, the app manager and the optional runtime store.
	 * @param OpenProjectSetFileOutput $output The set file writer and the translator.
	 */
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly TokenSetFileLocation $location,
		private readonly OpenProjectSetFileOutput $output,
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
	 * @spec openspec/specs/openproject-set-file/spec.md#requirement-any-token-set-can-be-downloaded-as-the-plugins-set-file
	 */
	#[AuthorizedAdminSetting(settings: Admin::class)]
	public function export(string $id): JSONResponse {
		if ($this->location->knows(id: $id) === false) {
			return new JSONResponse(['error' => $this->output->l->t('Token set not found.')], 404);
		}

		$name = $this->location->nameOf(id: $id);
		$appPath = $this->location->appManager->getAppPath(Application::APP_ID);
		$file = $this->output->writer->setFile(appPath: $appPath, id: $id, name: $name, store: $this->location->store);
		$response = new JSONResponse($file);
		$response->addHeader('Content-Disposition', 'attachment; filename="thematiq-' . $id . '.json"');

		return $response;
	}//end export()
}//end class
