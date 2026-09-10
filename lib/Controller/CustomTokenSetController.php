<?php

/**
 * NL Design Custom Token Set Controller.
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
 * @spec openspec/changes/custom-token-set-upload/tasks.md#task-3.1
 * @spec openspec/changes/custom-token-set-upload/tasks.md#task-3.2
 * @spec openspec/changes/custom-token-set-upload/tasks.md#task-3.3
 * @spec openspec/specs/custom-token-sets/spec.md
 * @spec openspec/specs/theming-audit/spec.md#requirement-complete-call-site-coverage
 */

declare(strict_types=1);

namespace OCA\Thematiq\Controller;

use OCA\Thematiq\AppInfo\Application;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\CustomTokenSetService;
use OCA\Thematiq\Service\CustomTokenSetValidator;
use OCA\Thematiq\Service\ThemingAuditService;
use OCA\Thematiq\Service\TokenSetConverterService;
use OCA\Thematiq\Settings\Admin;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use RuntimeException;

/**
 * Admin-only controller for the custom token set upload lifecycle.
 *
 * Every method is restricted to delegated theming admins via
 * AuthorizedAdminSetting and CSRF-protected (no NoCSRFRequired). The upload
 * output is CSS served to every user, so the validation pipeline is strict and
 * the served file is always re-serialised from parsed declarations.
 *
 * @spec openspec/changes/custom-token-set-upload/tasks.md#task-3.1
 * @spec openspec/specs/theming-audit/spec.md#requirement-complete-call-site-coverage
 */
class CustomTokenSetController extends Controller {

	/**
	 * The custom token set storage/lifecycle service.
	 *
	 * @var CustomTokenSetService
	 */
	private CustomTokenSetService $service;

	/**
	 * The CSS upload validator / re-serialiser.
	 *
	 * @var CustomTokenSetValidator
	 */
	private CustomTokenSetValidator $validator;

	/**
	 * The CSS parser service.
	 *
	 * @var CssParserService
	 */
	private CssParserService $cssParser;

	/**
	 * The localization service.
	 *
	 * @var IL10N
	 */
	private IL10N $l;

	/**
	 * The theming audit trail service.
	 *
	 * @var ThemingAuditService
	 */
	private ThemingAuditService $auditService;

	/**
	 * The application configuration service (active token set lookup, for
	 * detecting whether a delete resets the active set).
	 *
	 * @var IConfig
	 */
	private IConfig $config;

	/**
	 * The theme converter — runs BEFORE the validator on every upload and
	 * paste, so an admin can hand this app the artefact a design system
	 * actually publishes instead of pre-baked `--nldesign-*` CSS.
	 *
	 * @var TokenSetConverterService
	 */
	private TokenSetConverterService $converter;

	/**
	 * Constructor.
	 *
	 * @param string $appName The app name.
	 * @param IRequest $request The request object.
	 * @param CustomTokenSetService $service The storage/lifecycle service.
	 * @param CustomTokenSetValidator $validator The CSS validator.
	 * @param CssParserService $cssParser The CSS parser service.
	 * @param IL10N $l The localization service.
	 * @param ThemingAuditService $auditService The theming audit trail service.
	 * @param IConfig $config The config service.
	 * @param TokenSetConverterService $converter The theme converter, which runs before the validator.
	 */
	public function __construct(
		string $appName,
		IRequest $request,
		CustomTokenSetService $service,
		CustomTokenSetValidator $validator,
		CssParserService $cssParser,
		IL10N $l,
		ThemingAuditService $auditService,
		IConfig $config,
		TokenSetConverterService $converter,
	) {
		parent::__construct(appName: $appName, request: $request);
		$this->service = $service;
		$this->validator = $validator;
		$this->cssParser = $cssParser;
		$this->l = $l;
		$this->auditService = $auditService;
		$this->config = $config;
		$this->converter = $converter;
	}//end __construct()

	/**
	 * Import a design-system theme (or a finished token set) as a custom set.
	 *
	 * Accepts a `name` plus EITHER a multipart `file` or a `content` paste —
	 * identical from the read onward, because a theme usually arrives as text
	 * in a clipboard, not as a file on disk. The content is CONVERTED first
	 * (`TokenSetConverterService`, which detects which of the four accepted
	 * shapes it is from the content itself, never from the file name), and the
	 * converter's emitted CSS is then put through the existing validator and
	 * stored as `css/tokens/custom-{slug}.css`.
	 *
	 * Conversion runs BEFORE validation, never instead of it: the validator is
	 * still the last gate on the bytes that get written.
	 *
	 * @return JSONResponse `{ id, imported, skipped, warnings, report, counts, inputKind }` or an error.
	 *
	 * @spec openspec/changes/nlds-theme-converter/specs/custom-token-sets/spec.md
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function upload(): JSONResponse {
		$name = trim((string)($this->request->getParam('name', '')));
		if ($name === '') {
			return new JSONResponse(['error' => $this->l->t('A token set name is required.')], 400);
		}

		$slug = $this->service->slugify(name: $name);
		if ($slug === '') {
			return new JSONResponse(['error' => $this->l->t('A token set name must contain at least one letter or digit.')], 422);
		}

		$read = $this->readInput();
		if ($read instanceof JSONResponse) {
			return $read;
		}

		try {
			$converted = $this->converter->convert(
				content: $read['content'],
				slug: $slug,
				displayName: $name,
				sourceName: $read['sourceName'],
				// An extracted logo lands under the SET's id, not the bare
				// slug, so an uploaded theme named "Amsterdam" can never
				// overwrite the shipped img/logos/amsterdam.svg.
				assetName: CustomTokenSetService::ID_PREFIX . $slug
			);
		} catch (RuntimeException $e) {
			$code = $e->getCode();
			if ($code < 400 || $code > 599) {
				$code = 422;
			}

			return new JSONResponse(['error' => $e->getMessage()], $code);
		}

		// The validator sees the EMITTED file, which is the thing being stored.
		$parsed = $this->mapFromCss(content: $converted['css'], slug: $slug);
		if ($parsed instanceof JSONResponse) {
			return $parsed;
		}

		$parsed['css'] = $converted['css'];
		$parsed['theming'] = ($converted['manifestEntry']['theming'] ?? []);
		$parsed['logoAsset'] = ($converted['logoAsset'] ?? null);
		$parsed['report'] = $converted['report'];
		$parsed['counts'] = $converted['counts'];
		$parsed['inputKind'] = $converted['inputKind'];
		$parsed['reasons'] = $this->converter->getReasons();
		if (isset($converted['manifestEntry']['upstreamVersion']) === true) {
			$parsed['version'] = $converted['manifestEntry']['upstreamVersion'];
		}

		// DTCG-only diagnostics, unchanged in shape from the pre-converter
		// upload path: `$deprecated` notices about the SOURCE document, and
		// hard mapping errors. Both stay separate from the conversion report.
		if (empty($converted['importWarnings']) === false) {
			$parsed['importWarnings'] = $converted['importWarnings'];
		}

		if (empty($converted['errors']) === false) {
			$parsed['errors'] = $converted['errors'];
		}

		return $this->persist(name: $name, parsed: $parsed);
	}//end upload()

	/**
	 * Read the import payload from either the file picker or the paste box.
	 *
	 * Both surfaces enforce the same 512 KB limit, and an empty file picker and
	 * an empty textarea are deliberately the same error: from here on the two
	 * paths are indistinguishable, so they must fail identically too.
	 *
	 * @return array{content: string, sourceName: string|null}|JSONResponse The payload, or the error response.
	 *
	 * @spec openspec/changes/nlds-theme-converter/specs/custom-token-sets/spec.md
	 */
	private function readInput() {
		$file = $this->request->getUploadedFile(key: 'file');

		if (empty($file) === false && isset($file['tmp_name']) === true) {
			$content = $this->readUpload(file: $file);
			if ($content instanceof JSONResponse) {
				return $content;
			}

			$fileName = trim((string)($file['name'] ?? ''));

			return [
				'content' => $content,
				'sourceName' => ($fileName === '' ? null : $fileName),
			];
		}

		$pasted = (string)($this->request->getParam('content', ''));
		if (trim($pasted) === '') {
			return new JSONResponse(
				['error' => $this->l->t('Choose a file or paste the contents of a theme file.')],
				400
			);
		}

		if (strlen($pasted) > CustomTokenSetValidator::MAX_SIZE) {
			return new JSONResponse(['error' => $this->l->t('Pasted content exceeds the 512 KB size limit.')], 413);
		}

		$sourceName = trim((string)($this->request->getParam('sourceName', '')));

		return [
			'content' => $pasted,
			'sourceName' => ($sourceName === '' ? null : $sourceName),
		];
	}//end readInput()

	/**
	 * Validate the uploaded file envelope and read its content.
	 *
	 * @param array<string, mixed>|null $file The uploaded file array from the request.
	 *
	 * @return string|JSONResponse The raw content, or the error response.
	 *
	 * @spec openspec/specs/custom-token-sets/spec.md
	 */
	private function readUpload(?array $file) {
		if (empty($file) === true || isset($file['tmp_name']) === false) {
			return new JSONResponse(['error' => $this->l->t('No file uploaded.')], 400);
		}

		if (($file['size'] ?? 0) > CustomTokenSetValidator::MAX_SIZE) {
			return new JSONResponse(['error' => $this->l->t('File exceeds the 512 KB size limit.')], 413);
		}

		$content = file_get_contents($file['tmp_name']);
		if ($content === false) {
			return new JSONResponse(['error' => $this->l->t('Could not read the uploaded file.')], 400);
		}

		return $content;
	}//end readUpload()

	/**
	 * Parse and map a CSS upload into the accepted/skipped split.
	 *
	 * @param string $content The raw CSS upload.
	 * @param string $slug The derived slug (for `--{slug}-*` extras).
	 *
	 * @return array{accepted: array<string, string>, skipped: string[]}|JSONResponse
	 *
	 * @spec openspec/changes/custom-token-set-upload/tasks.md#task-3.3
	 */
	private function mapFromCss(string $content, string $slug) {
		if ($this->validator->hasDisallowedSelector(css: $content) === true) {
			return new JSONResponse(
				['error' => $this->l->t('The CSS contains a selector or at-rule other than :root, which is not allowed in a token set.')],
				422
			);
		}

		$declarations = $this->cssParser->parseRootBlock(css: $content);
		$split = $this->validator->validateDeclarations(declarations: $declarations, slug: $slug);
		if ($split === null) {
			$error = $this->validator->getLastError();

			return new JSONResponse(
				['error' => ($error['message'] ?? $this->l->t('The uploaded CSS could not be validated.'))],
				($error['status'] ?? 422)
			);
		}

		return $split;
	}//end mapFromCss()

	/**
	 * Store the accepted declarations and build the upload response.
	 *
	 * The response's `warnings` key is always the WCAG contrast warnings
	 * (pre-existing behaviour, unchanged). A DTCG (JSON) upload additionally
	 * carries `errors` (structured, non-recoverable per-token diagnostics),
	 * `importWarnings` (structured `$deprecated` notices — deliberately not
	 * named `warnings` to avoid colliding with the contrast warnings above)
	 * and `version` (the declared package version, when present). A CSS
	 * upload carries none of those three — `$parsed` simply omits them.
	 *
	 * @param string $name The display name.
	 * @param array<string, mixed> $parsed The validated split (`accepted`, `skipped`), plus — for a
	 *                                     DTCG upload — `errors`, `importWarnings` and `version` (see the description above).
	 *
	 * @return JSONResponse The upload result or a collision/storage error.
	 *
	 * @spec openspec/changes/custom-token-set-upload/tasks.md#task-3.3
	 * @spec openspec/specs/custom-token-sets/spec.md
	 * @spec openspec/specs/theming-audit/spec.md#requirement-complete-call-site-coverage
	 */
	private function persist(string $name, array $parsed): JSONResponse {
		try {
			$result = $this->service->store(
				displayName: $name,
				description: trim((string)($this->request->getParam('description', ''))),
				declarations: $parsed['accepted'],
				version: ($parsed['version'] ?? null),
				importWarnings: ($parsed['importWarnings'] ?? []),
				// The converter's own file, so its four sections and provenance
				// block are what lands on disk rather than a flat re-serialise.
				css: ($parsed['css'] ?? null),
				theming: ($parsed['theming'] ?? []),
				logoAsset: ($parsed['logoAsset'] ?? null)
			);
		} catch (RuntimeException $e) {
			$code = $e->getCode();
			if ($code < 400 || $code > 599) {
				$code = 500;
			}

			return new JSONResponse(['error' => $e->getMessage()], $code);
		}

		$servedCss = $this->service->getRawContent(id: $result['id']);
		$contentHash = null;
		if ($servedCss !== null) {
			$contentHash = 'sha256:' . substr(hash(algo: 'sha256', data: $servedCss), 0, 12);
		}

		$this->auditService->log(
			action: 'custom_set_uploaded',
			context: [
				'id' => $result['id'],
				'name' => $name,
				'declarationCount' => count($parsed['accepted']),
				'contentHash' => $contentHash,
			]
		);

		$response = [
			'id' => $result['id'],
			'imported' => count($parsed['accepted']),
			'skipped' => $parsed['skipped'],
			'warnings' => $result['warnings'],
		];

		if (isset($parsed['errors']) === true) {
			$response['errors'] = $parsed['errors'];
		}

		if (isset($parsed['importWarnings']) === true) {
			$response['importWarnings'] = $parsed['importWarnings'];
		}

		if (array_key_exists('version', $parsed) === true) {
			$response['version'] = $parsed['version'];
		}

		// The conversion report: what the theme asked for that Nextcloud will
		// not do, and why. `reasons` carries the human sentence per code so the
		// panel does not have to keep its own copy of the mapping table.
		foreach (['report', 'counts', 'inputKind', 'reasons'] as $key) {
			if (isset($parsed[$key]) === true) {
				$response[$key] = $parsed[$key];
			}
		}

		$response['selectable'] = true;

		return new JSONResponse($response);
	}//end persist()

	/**
	 * List stored custom token sets with their metadata and contrast warnings.
	 *
	 * @return JSONResponse The list of custom sets.
	 *
	 * @spec openspec/changes/custom-token-set-upload/tasks.md#task-3.1
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function list(): JSONResponse {
		return new JSONResponse(['sets' => $this->service->list()]);
	}//end list()

	/**
	 * Export (download) the served CSS of a custom token set.
	 *
	 * @param string $id The custom set id.
	 *
	 * @return DataDownloadResponse|JSONResponse The CSS download or a 404.
	 *
	 * @spec openspec/changes/custom-token-set-upload/tasks.md#task-3.1
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function export(string $id) {
		$content = $this->service->getRawContent(id: $id);
		if ($content === null) {
			return new JSONResponse(['error' => $this->l->t('Token set not found.')], 404);
		}

		return new DataDownloadResponse(
			data: $content,
			filename: $id . '.css',
			contentType: 'text/css'
		);
	}//end export()

	/**
	 * Delete a custom token set (file + manifest), resetting the active set if needed.
	 *
	 * @param string $id The custom set id.
	 *
	 * @return JSONResponse The deletion result.
	 *
	 * @spec openspec/changes/custom-token-set-upload/tasks.md#task-3.1
	 * @spec openspec/specs/theming-audit/spec.md#requirement-complete-call-site-coverage
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function delete(string $id): JSONResponse {
		if ($this->service->isCustomId(id: $id) === false) {
			return new JSONResponse(['error' => $this->l->t('Only custom token sets can be deleted.')], 400);
		}

		$activeBefore = $this->config->getAppValue(Application::APP_ID, 'token_set', 'nextcloud');
		$servedCss = $this->service->getRawContent(id: $id);

		if ($this->service->delete(id: $id) === false) {
			return new JSONResponse(['error' => $this->l->t('Token set not found.')], 404);
		}

		$contentHash = null;
		if ($servedCss !== null) {
			$contentHash = 'sha256:' . substr(hash(algo: 'sha256', data: $servedCss), 0, 12);
		}

		$this->auditService->log(
			action: 'custom_set_deleted',
			context: [
				'id' => $id,
				'activeReset' => ($activeBefore === $id),
				'contentHash' => $contentHash,
			]
		);

		return new JSONResponse(['status' => 'ok']);
	}//end delete()
}//end class
