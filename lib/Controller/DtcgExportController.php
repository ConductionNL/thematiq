<?php

/**
 * Thematiq DTCG export controller.
 *
 * `GET /settings/tokensets/{id}/dtcg`: any shipped or custom token set as a W3C Design
 * Tokens (DTCG v2025.10) document, as a download. Admin-only; a download changes no
 * configuration, so it writes no audit entry.
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
 * @spec openspec/changes/authoring-dtcg-export/tasks.md#task-4.2
 */

declare(strict_types=1);

namespace OCA\Thematiq\Controller;

use OCA\Thematiq\AppInfo\Application;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\DeprecationRecords;
use OCA\Thematiq\Service\DesignTokensWriter;
use OCA\Thematiq\Service\TokenSetService;
use OCA\Thematiq\Settings\Admin;
use OCP\App\IAppManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IL10N;
use OCP\IRequest;

/**
 * Token sets as DTCG documents.
 *
 * @spec openspec/changes/authoring-dtcg-export/tasks.md#task-4.2
 */
class DtcgExportController extends Controller {

	/**
	 * Constructor.
	 *
	 * @param string             $appName      The app name.
	 * @param IRequest           $request      The request.
	 * @param TokenSetService    $tokenSets    Says which ids exist, and their names.
	 * @param IAppManager        $appManager   The app path and version.
	 * @param CssParserService   $cssParser    Reads the set's declarations.
	 * @param DesignTokensWriter $writer       Writes the document.
	 * @param DeprecationRecords $deprecations Written as `$deprecated` on deprecated tokens.
	 * @param IL10N              $l            The error text.
	 */
	public function __construct(
		string $appName,
		IRequest $request,
		private TokenSetService $tokenSets,
		private IAppManager $appManager,
		private CssParserService $cssParser,
		private DesignTokensWriter $writer,
		private DeprecationRecords $deprecations,
		private IL10N $l,
	) {
		parent::__construct(appName: $appName, request: $request);
	}//end __construct()

	/**
	 * The set as a DTCG download: only what the set's own file declares.
	 *
	 * @param string $id The token set id.
	 *
	 * @return JSONResponse The document with an attachment name, or 404.
	 *
	 * @spec openspec/changes/authoring-dtcg-export/tasks.md#task-4.2
	 */
	#[AuthorizedAdminSetting(settings: Admin::class)]
	public function export(string $id): JSONResponse {
		if (preg_match('/^[a-z0-9][a-z0-9-]*$/', $id) !== 1 || $this->tokenSets->isValidTokenSet(tokenSetId: $id) === false) {
			return new JSONResponse(['error' => $this->l->t('Token set not found.')], 404);
		}

		$appPath = $this->appManager->getAppPath(Application::APP_ID);
		$css     = (string)file_get_contents($appPath . '/css/tokens/' . $id . '.css');
		$name    = $id;
		foreach ($this->tokenSets->getAvailableTokenSets() as $set) {
			if (($set['id'] ?? null) === $id) {
				$name = (string)($set['name'] ?? $id);
			}
		}

		$document = $this->writer->write(
			declarations: $this->cssParser->parseRootBlock(css: $css),
			setId: $id,
			setName: $name,
			appVersion: $this->appManager->getAppVersion(Application::APP_ID),
			deprecations: $this->deprecations->all()
		);

		$response = new JSONResponse($document);
		$response->addHeader('Content-Disposition', 'attachment; filename="' . $id . '.tokens.json"');

		return $response;
	}//end export()
}//end class
