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
 * @spec openspec/specs/token-set-dtcg-export/spec.md#requirement-any-token-set-can-be-downloaded-as-a-dtcg-document
 */

declare(strict_types=1);

namespace OCA\Thematiq\Controller;

use OCA\Thematiq\AppInfo\Application;
use OCA\Thematiq\Service\DtcgExportTools;
use OCA\Thematiq\Service\FigmaTokenWriter;
use OCA\Thematiq\Service\RuntimeFile\SetFileReader;
use OCA\Thematiq\Service\TokenSetDownload;
use OCA\Thematiq\Service\TokenSetFileLocation;
use OCA\Thematiq\Settings\Admin;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * Token sets as DTCG documents.
 *
 * @spec openspec/specs/token-set-dtcg-export/spec.md#requirement-any-token-set-can-be-downloaded-as-a-dtcg-document
 */
class DtcgExportController extends Controller {

	/**
	 * Constructor.
	 *
	 * @param string $appName The app name.
	 * @param IRequest $request The request.
	 * @param TokenSetFileLocation $location The token set list, the app manager and the optional runtime store.
	 * @param DtcgExportTools $tools The CSS parser, the DTCG writer, the deprecation records and the translator.
	 */
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly TokenSetFileLocation $location,
		private readonly DtcgExportTools $tools,
	) {
		parent::__construct(appName: $appName, request: $request);
	}//end __construct()

	/**
	 * The set as a DTCG download: only what the set's own file declares.
	 *
	 * @param string $id The token set id.
	 *
	 * @return JSONResponse The document with an attachment name, 404 for an unknown set, or 400 for an unknown `format`.
	 *
	 * @spec openspec/specs/figma-token-files/spec.md#requirement-any-token-set-can-be-downloaded-for-figma
	 *
	 * @spec openspec/specs/token-set-dtcg-export/spec.md#requirement-any-token-set-can-be-downloaded-as-a-dtcg-document
	 */
	#[AuthorizedAdminSetting(settings: Admin::class)]
	public function export(string $id): JSONResponse {
		if ($this->location->knows(id: $id) === false) {
			return new JSONResponse(['error' => $this->tools->l->t('Token set not found.')], 404);
		}

		$format = $this->requestedFormat();
		if (in_array($format, FigmaTokenWriter::FORMATS, true) === false) {
			return new JSONResponse(['error' => $this->tools->l->t('Unknown file format.')], 400);
		}

		$download = $this->download(id: $id, format: $format);
		$figma = new FigmaTokenWriter();
		$document = $figma->inFormat(document: $this->tools->writer->document(download: $download), download: $download);

		$response = new JSONResponse($document);
		$response->addHeader('Content-Disposition', 'attachment; filename="' . $figma->fileName(setId: $id, format: $format) . '"');

		return $response;
	}//end export()

	/**
	 * The file format the request asks for; `dtcg` when it names none.
	 *
	 * @return string The format, not yet checked.
	 */
	private function requestedFormat(): string {
		$format = (string)$this->request->getParam('format', 'dtcg');
		if ($format === '') {
			$format = 'dtcg';
		}

		return $format;
	}//end requestedFormat()

	/**
	 * A set's download: its own declarations, its name, the app version and the deprecation records.
	 *
	 * @param string $id The token set id, already checked.
	 * @param string $format The file format, already checked.
	 *
	 * @return TokenSetDownload The download.
	 */
	private function download(string $id, string $format): TokenSetDownload {
		$appPath = $this->location->appManager->getAppPath(Application::APP_ID);
		$css = (string)(new SetFileReader())->read(appPath: $appPath, name: 'css/tokens/' . $id . '.css', store: $this->location->store);

		return new TokenSetDownload(
			setId: $id,
			setName: $this->location->nameOf(id: $id),
			declarations: $this->tools->cssParser->parseRootBlock(css: $css),
			format: $format,
			appVersion: $this->location->appManager->getAppVersion(Application::APP_ID),
			deprecations: $this->tools->deprecations->all()
		);
	}//end download()
}//end class
