<?php

/**
 * NL Design Overrides Controller.
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
 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-7
 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-8
 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-9
 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-10
 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-11
 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-12
 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-13
 * @spec openspec/specs/theming-audit/spec.md#requirement-complete-call-site-coverage
 */

declare(strict_types=1);

namespace OCA\Thematiq\Controller;

use OCA\Thematiq\AppInfo\Application;
use OCA\Thematiq\Service\BrandingCaptureService;
use OCA\Thematiq\Service\CssInjectionService;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\CustomOverridesService;
use OCA\Thematiq\Service\ThemingAuditService;
use OCA\Thematiq\Service\ThemingService;
use OCA\Thematiq\Service\TokenRegistry;
use OCA\Thematiq\Settings\Admin;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IConfig;
use OCP\IRequest;

/**
 * Controller for managing custom CSS token overrides.
 *
 * Handles CRUD, import, and export of the overrides files, and the reset to
 * stock Nextcloud.
 *
 * Every endpoint takes an optional `tokenSet`: the set whose overrides file
 * is meant. The admin page can be wearing a set other than the instance's
 * active one (a preview, or a switch that has not reloaded), and the stock set
 * keeps its edits in a file of its own — see CustomOverridesService. Without
 * it, the instance's active set is meant.
 *
 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-7
 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-8
 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-9
 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-10
 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-11
 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-12
 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-13
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) - The reset to stock touches every part a theme
 * is made of: the overrides files, the active set and core theming. Each dependency is one of them;
 * a separate reset service would move the same coupling one class along.
 */
class OverridesController extends Controller {

	/**
	 * The answer to a failed write. The exception text names the absolute file
	 * path, which is not for the browser.
	 *
	 * @var string
	 */
	private const WRITE_FAILED = 'The token overrides could not be saved. Check that the web server can write to the app\'s css/ directory.';

	/**
	 * The custom overrides service.
	 *
	 * @var CustomOverridesService
	 */
	private CustomOverridesService $overridesService;

	/**
	 * The CSS parser service.
	 *
	 * @var CssParserService
	 */
	private CssParserService $cssParser;

	/**
	 * The theming audit trail service.
	 *
	 * @var ThemingAuditService
	 */
	private ThemingAuditService $auditService;

	/**
	 * The app config, for the active token set.
	 *
	 * @var IConfig
	 */
	private IConfig $config;

	/**
	 * The theming service, for resetting core theming with the theme.
	 *
	 * @var ThemingService
	 */
	private ThemingService $themingService;

	/**
	 * Copies Nextcloud's own branding into the theme on save. Optional so a
	 * caller that builds this controller by hand need not know about it; the
	 * container always injects it.
	 *
	 * @var BrandingCaptureService|null
	 */
	private ?BrandingCaptureService $brandingCapture;

	/**
	 * Constructor.
	 *
	 * @param string $appName The app name.
	 * @param IRequest $request The request object.
	 * @param CustomOverridesService $overridesService The custom overrides service.
	 * @param CssParserService $cssParser The CSS parser service.
	 * @param ThemingAuditService $auditService The theming audit trail service.
	 * @param IConfig $config The app config.
	 * @param ThemingService $themingService The theming service.
	 * @param BrandingCaptureService|null $brandingCapture Copies Nextcloud's branding into the theme on save.
	 */
	public function __construct(
		string $appName,
		IRequest $request,
		CustomOverridesService $overridesService,
		CssParserService $cssParser,
		ThemingAuditService $auditService,
		IConfig $config,
		ThemingService $themingService,
		?BrandingCaptureService $brandingCapture = null,
	) {
		parent::__construct(appName: $appName, request: $request);
		$this->overridesService = $overridesService;
		$this->cssParser = $cssParser;
		$this->auditService = $auditService;
		$this->config = $config;
		$this->themingService = $themingService;
		$this->brandingCapture = $brandingCapture;
	}//end __construct()

	/**
	 * The token set a request names, or null for the instance's active set.
	 *
	 * @return string|null The `tokenSet` parameter, or null when absent or empty.
	 */
	private function requestedTokenSet(): ?string {
		$tokenSet = $this->request->getParam('tokenSet', '');
		if (is_string($tokenSet) === false || $tokenSet === '') {
			return null;
		}

		return $tokenSet;
	}//end requestedTokenSet()

	/**
	 * Get the current custom token overrides.
	 *
	 * Returns only tokens explicitly set in custom-overrides.css,
	 * plus the full editable token registry for the UI.
	 *
	 * @return JSONResponse The overrides and token registry.
	 *
	 * @SuppressWarnings(PHPMD.StaticAccess) - TokenRegistry uses static methods by design
	 *
	 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-7
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function getOverrides(): JSONResponse {
		$overrides = $this->overridesService->read(tokenSet: $this->requestedTokenSet());
		$registry = TokenRegistry::getTokens();
		$tabs = TokenRegistry::getTabLabels();

		return new JSONResponse(
			[
				'overrides' => $overrides,
				'darkOverrides' => $this->overridesService->readDark(tokenSet: $this->requestedTokenSet()),
				'darkDerived' => $this->overridesService->derivedDark(tokenSet: $this->requestedTokenSet()),
				'registry' => $registry,
				// Listed per component group, below the tabs. Only what a row shows:
				// the selectors the server writes rules for would triple the payload.
				'internal' => array_map(
					static fn (array $token): array => [
						'variable' => $token['variable'],
						'group' => $token['group'],
						'type' => $token['type'],
						'stock' => $token['stock'],
					],
					TokenRegistry::getInternalTokens()
				),
				'count' => TokenRegistry::countEditable(),
				'tabs' => $tabs,
			]
		);
	}//end getOverrides()

	/**
	 * Write new custom token overrides to custom-overrides.css.
	 *
	 * Accepts a JSON body with an 'overrides' key containing token name => value pairs.
	 * A save with any token outside the TokenRegistry, or with a value the writer
	 * would drop, is refused with 400 naming those tokens, and nothing is written.
	 *
	 * `reset: true` instead resets the theme to stock Nextcloud — see
	 * {@see self::resetToStock()}. It shares this endpoint rather than taking a
	 * route of its own because Nextcloud caches the route collection per host
	 * for an hour, so a new route 404s on every warm instance until then.
	 *
	 * @return JSONResponse Status and count of written tokens.
	 *
	 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-8
	 * @spec openspec/specs/theming-audit/spec.md#requirement-complete-call-site-coverage
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function setOverrides(): JSONResponse {
		$params = $this->request->getParams();

		if (($params['reset'] ?? false) === true) {
			return $this->resetToStock();
		}

		$overrides = $params['overrides'] ?? [];

		if (is_array($overrides) === false) {
			return new JSONResponse(['error' => 'overrides must be an object'], 400);
		}

		// Refuse the whole save when any token would be dropped, so the answer,
		// the written count and the audit entry all describe what reached the file.
		$darkOverrides = ($params['darkOverrides'] ?? []);
		if (is_array($darkOverrides) === false) {
			return new JSONResponse(['error' => 'darkOverrides must be an object'], 400);
		}

		$rejected = $this->overridesService->findRejected(tokens: $overrides, darkTokens: $darkOverrides);
		if (empty($rejected) === false) {
			return $this->rejectedResponse(rejected: $rejected);
		}

		$tokenSet = $this->requestedTokenSet();
		$before = $this->overridesService->read(tokenSet: $tokenSet);

		try {
			$this->overridesService->write(tokens: $overrides, tokenSet: $tokenSet, darkTokens: $darkOverrides);
		} catch (\RuntimeException) {
			return new JSONResponse(['error' => self::WRITE_FAILED], 500);
		}

		$this->auditService->log(
			action: 'overrides_written',
			context: [
				'old' => $before,
				'new' => $overrides,
			]
		);

		$response = ['status' => 'ok', 'written' => count($overrides)];

		// Saving a theme keeps the Nextcloud branding that is on now —
		// colours, background, logos, favicon — with it, so applying the
		// theme later brings that back as well. Not for the stock set: its
		// branding IS Nextcloud's own settings, and applying stock resets them.
		$setId = ($tokenSet ?? $this->config->getAppValue(Application::APP_ID, 'token_set', CssInjectionService::STOCK_TOKEN_SET));
		if ($this->request->getParam('captureTheming', false) === true
			&& $this->brandingCapture !== null
			&& $setId !== CssInjectionService::STOCK_TOKEN_SET
		) {
			$response['theming'] = $this->brandingCapture->capture(setId: $setId);
		}

		return new JSONResponse($response);
	}//end setOverrides()

	/**
	 * The 400 for a refused save: every token with its reason, which names the type.
	 *
	 * @param array<string, string> $rejected Token name => reason.
	 *
	 * @return JSONResponse The response.
	 */
	private function rejectedResponse(array $rejected): JSONResponse {
		$named = [];
		foreach ($rejected as $name => $reason) {
			$named[] = $name . ' (' . $reason . ')';
		}

		return new JSONResponse(['error' => 'Some tokens were not saved: ' . implode(', ', $named), 'rejected' => $rejected], 400);
	}//end rejectedResponse()

	/**
	 * Reset the theme to stock Nextcloud.
	 *
	 * Empties the overrides of the set that was active and of the stock set,
	 * makes the stock set the active one, and undoes what the theming sync
	 * wrote into core theming. What is left is what the running Nextcloud
	 * paints on its own. Instance-wide features that do not belong to a theme
	 * — freeform custom CSS, custom fonts, the display toggles, group and
	 * per-app theming, and the custom token sets themselves — are kept.
	 *
	 * Both files are emptied, not only the active set's: the stock set is
	 * where this lands, and its own edits are exactly what "stock" excludes.
	 *
	 * @return JSONResponse `{status: "ok", tokenSet: "nextcloud"}`, or 500 when a file cannot be written.
	 *
	 * @spec openspec/specs/theming-audit/spec.md#requirement-complete-call-site-coverage
	 */
	private function resetToStock(): JSONResponse {
		$stock = CssInjectionService::STOCK_TOKEN_SET;
		$previous = $this->config->getAppValue(Application::APP_ID, 'token_set', $stock);

		$emptied = [];
		foreach (array_unique([$previous, $stock]) as $tokenSet) {
			$before = $this->overridesService->read(tokenSet: $tokenSet);

			try {
				$this->overridesService->write(tokens: [], tokenSet: $tokenSet);
			} catch (\RuntimeException) {
				return new JSONResponse(['error' => self::WRITE_FAILED], 500);
			}

			if ($before !== []) {
				$emptied[] = $before;
			}
		}

		$this->config->setAppValue(Application::APP_ID, 'token_set', $stock);
		$this->themingService->resetToDefaults();
		// Nothing is synced any more, so nothing is remembered as synced — the
		// same keys SettingsController::resetThemingValues() clears.
		foreach (['logo', 'background'] as $imageKey) {
			$this->config->deleteAppValue(Application::APP_ID, SettingsController::SYNCED_IMAGE_PREFIX . $imageKey);
		}

		// Recorded as what it did, in the actions the audit trail already knows.
		foreach ($emptied as $before) {
			$this->auditService->log(action: 'overrides_written', context: ['old' => $before, 'new' => []]);
		}

		if ($previous !== $stock) {
			$this->auditService->log(action: 'token_set_changed', context: ['old' => $previous, 'new' => $stock]);
		}

		return new JSONResponse(['status' => 'ok', 'tokenSet' => $stock]);
	}//end resetToStock()

	/**
	 * Download custom-overrides.css as a file.
	 *
	 * @return DataDownloadResponse The CSS file as a download.
	 *
	 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-9
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function exportOverrides(): DataDownloadResponse {
		$content = $this->overridesService->getRawContent(tokenSet: $this->requestedTokenSet());

		return new DataDownloadResponse(
			data: $content,
			filename: 'custom-overrides.css',
			contentType: 'text/css'
		);
	}//end exportOverrides()

	/**
	 * Import custom token overrides from an uploaded CSS file.
	 *
	 * Accepts a multipart/form-data upload with a 'file' field.
	 * Only recognized editable tokens are imported; unknown tokens are silently skipped.
	 * The import fully replaces the existing custom-overrides.css.
	 *
	 * @return JSONResponse Import result with 'imported' and 'skipped' counts.
	 *
	 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-10
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function importOverrides(): JSONResponse {
		$validationError = $this->validateUploadedFile();
		if ($validationError !== null) {
			return $validationError;
		}

		$content = $this->readUploadedContent();
		if ($content === null) {
			return new JSONResponse(['error' => 'Could not read uploaded file'], 400);
		}

		// The light values only; an exported file also carries the dark blocks.
		$parsed = $this->cssParser->parseOverridesFile(css: $content);
		if ($parsed === null) {
			return new JSONResponse(
				['error' => 'No CSS custom property declarations found in the uploaded file'],
				400
			);
		}

		return $this->writeImportedTokens(parsed: $parsed, rawContent: $content);
	}//end importOverrides()

	/**
	 * Validate the uploaded file for the import endpoint.
	 *
	 * @return JSONResponse|null An error response, or null if the file is valid.
	 *
	 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-11
	 */
	private function validateUploadedFile(): ?JSONResponse {
		$file = $this->request->getUploadedFile(key: 'file');

		if (empty($file) === true || isset($file['tmp_name']) === false) {
			return new JSONResponse(['error' => 'No file uploaded'], 400);
		}

		$maxSize = (256 * 1024);
		if ($file['size'] > $maxSize) {
			return new JSONResponse(['error' => 'File exceeds the 256 KB size limit'], 413);
		}

		// Validate file extension.
		$originalName = $file['name'] ?? '';
		$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
		if ($extension !== 'css') {
			return new JSONResponse(['error' => 'Only .css files are accepted'], 415);
		}

		// Validate MIME type via server-side detection (ignore client-provided type).
		$tmpName = $file['tmp_name'];
		$mimeType = mime_content_type($tmpName);
		// Accept text/css, text/plain (editors often send this for .css), and
		// application/octet-stream (generic fallback from some browsers).
		$allowedMimes = ['text/css', 'text/plain', 'application/octet-stream'];
		if (in_array($mimeType, $allowedMimes, true) === false) {
			return new JSONResponse(['error' => 'File does not appear to be a CSS file'], 415);
		}

		return null;
	}//end validateUploadedFile()

	/**
	 * Read the content of the uploaded file.
	 *
	 * @return string|null The file content, or null on failure.
	 *
	 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-12
	 */
	private function readUploadedContent(): ?string {
		$file = $this->request->getUploadedFile(key: 'file');
		$content = file_get_contents($file['tmp_name']);

		if ($content === false) {
			return null;
		}

		return $content;
	}//end readUploadedContent()

	/**
	 * Filter parsed tokens and write editable ones to the overrides file.
	 *
	 * @param array<string, string> $parsed The parsed CSS tokens.
	 * @param string $rawContent The raw uploaded CSS, hashed into the audit entry (never persisted verbatim).
	 *
	 * @return JSONResponse The import result response.
	 *
	 * @SuppressWarnings(PHPMD.StaticAccess) - TokenRegistry uses static methods by design
	 *
	 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-13
	 * @spec openspec/specs/theming-audit/spec.md#requirement-complete-call-site-coverage
	 */
	private function writeImportedTokens(array $parsed, string $rawContent): JSONResponse {
		$toImport = [];
		$skipped = 0;
		foreach ($parsed as $name => $value) {
			if (TokenRegistry::isEditable(tokenName: $name) === false) {
				$skipped++;
				continue;
			}

			$toImport[$name] = $value;
		}

		try {
			$this->overridesService->write(tokens: $toImport, tokenSet: $this->requestedTokenSet());
		} catch (\RuntimeException) {
			return new JSONResponse(['error' => self::WRITE_FAILED], 500);
		}

		$this->auditService->log(
			action: 'overrides_imported',
			context: [
				'imported' => count($toImport),
				'skipped' => $skipped,
				'new' => $rawContent,
				'newIsCss' => true,
			]
		);

		return new JSONResponse(
			[
				'status' => 'ok',
				'imported' => count($toImport),
				'skipped' => $skipped,
			]
		);
	}//end writeImportedTokens()
}//end class
