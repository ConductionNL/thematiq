<?php

/**
 * Thematiq token source controller.
 *
 * `POST /settings/tokensets/sources/{sourceId}`: update every brand of a multi-brand
 * source from a new file or pasted content, all or none, with a per-brand report.
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
 * @spec openspec/specs/multi-brand-token-sources/spec.md#requirement-a-new-version-of-a-source-updates-every-brand-together
 */

declare(strict_types=1);

namespace OCA\Thematiq\Controller;

use OCA\Thematiq\Service\CustomTokenSetValidator;
use OCA\Thematiq\Service\MultiBrandImportService;
use OCA\Thematiq\Settings\Admin;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IL10N;
use OCP\IRequest;
use RuntimeException;

/**
 * Multi-brand sources over HTTP.
 *
 * @spec openspec/specs/multi-brand-token-sources/spec.md#requirement-a-new-version-of-a-source-updates-every-brand-together
 */
class TokenSourceController extends Controller {

	/**
	 * Constructor.
	 *
	 * @param string $appName The app name.
	 * @param IRequest $request The request.
	 * @param MultiBrandImportService $imports The sources.
	 * @param IL10N $l The error texts.
	 */
	public function __construct(
		string $appName,
		IRequest $request,
		private MultiBrandImportService $imports,
		private IL10N $l,
	) {
		parent::__construct(appName: $appName, request: $request);
	}//end __construct()

	/**
	 * Every source with its brands.
	 *
	 * @return JSONResponse The sources by id, each with its brands.
	 *
	 * @spec openspec/specs/multi-brand-token-sources/spec.md#requirement-the-administrator-chooses-which-brands-to-import
	 */
	#[AuthorizedAdminSetting(settings: Admin::class)]
	public function list(): JSONResponse {
		return new JSONResponse(['sources' => (object)$this->imports->records()]);
	}//end list()

	/**
	 * Update a source.
	 *
	 * @param string $sourceId The source id.
	 *
	 * @return JSONResponse The updated, missing and new brands, or an error naming the brand.
	 *
	 * @spec openspec/specs/multi-brand-token-sources/spec.md#requirement-a-new-version-of-a-source-updates-every-brand-together
	 */
	#[AuthorizedAdminSetting(settings: Admin::class)]
	public function update(string $sourceId): JSONResponse {
		$read = $this->readInput();
		if ($read instanceof JSONResponse) {
			return $read;
		}

		try {
			$report = $this->imports->update(sourceId: $sourceId, content: $read['content'], fileName: $read['name']);
		} catch (RuntimeException $e) {
			$code = match ($e->getCode()) {
				404 => 404,
				409 => 409,
				default => 422,
			};

			$error = $e->getMessage();
			if ($code === 404) {
				$error = $this->l->t('There is no token source with this id.');
			}

			return new JSONResponse(['error' => $error], $code);
		}

		return new JSONResponse(['status' => 'ok'] + $report);
	}//end update()

	/**
	 * The new content: an uploaded `file` or pasted `content`, at most 512 KB.
	 *
	 * @return array{content: string, name: string|null}|JSONResponse
	 */
	private function readInput(): array|JSONResponse {
		$file = $this->request->getUploadedFile(key: 'file');
		if (is_array($file) === true && (string)($file['tmp_name'] ?? '') !== '') {
			if ((int)($file['size'] ?? 0) > CustomTokenSetValidator::MAX_SIZE) {
				return new JSONResponse(['error' => $this->l->t('The file exceeds the 512 KB size limit.')], 413);
			}

			return ['content' => (string)file_get_contents((string)$file['tmp_name']), 'name' => (string)($file['name'] ?? '')];
		}

		$content = (string)$this->request->getParam('content', '');
		if (trim($content) === '') {
			return new JSONResponse(['error' => $this->l->t('Choose a file or paste the contents of a theme file.')], 400);
		}

		if (strlen($content) > CustomTokenSetValidator::MAX_SIZE) {
			return new JSONResponse(['error' => $this->l->t('Pasted content exceeds the 512 KB size limit.')], 413);
		}

		return ['content' => $content, 'name' => null];
	}//end readInput()
}//end class
