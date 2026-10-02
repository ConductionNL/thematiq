<?php

/**
 * Thematiq Brand Form Controller.
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
 * @spec openspec/specs/simple-brand-form/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Controller;

use InvalidArgumentException;
use OCA\Thematiq\Service\BrandFormService;
use OCA\Thematiq\Service\CustomTokenSetService;
use OCA\Thematiq\Service\CustomTokenSetValidator;
use OCA\Thematiq\Service\ThemingAuditService;
use OCA\Thematiq\Settings\Admin;
use OCP\App\IAppManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IL10N;
use OCP\IRequest;
use RuntimeException;

/**
 * Creates a custom token set from a name, a primary colour, a background colour and an
 * optional logo, through the same store path as an upload. A controller of its own because
 * CustomTokenSetController's constructor is already at its limit and its tests pin it.
 *
 * @spec openspec/specs/simple-brand-form/spec.md
 */
class BrandFormController extends Controller {

	/**
	 * Logo file types, as CustomTokenSetService writes them.
	 *
	 * @var array<int, string>
	 */
	private const LOGO_TYPES = ['svg', 'png', 'jpg', 'gif', 'webp'];

	/**
	 * Constructor.
	 *
	 * @param string                $appName The app name.
	 * @param IRequest              $request The request.
	 * @param BrandFormService      $brandForm The derivation.
	 * @param CustomTokenSetService $store     The custom set store.
	 * @param IAppManager           $apps      Resolves the app root (rules and defaults).
	 * @param ThemingAuditService   $audit     The theming audit log.
	 * @param IL10N                 $l         Translations.
	 */
	public function __construct(
		string $appName,
		IRequest $request,
		private BrandFormService $brandForm,
		private CustomTokenSetService $store,
		private IAppManager $apps,
		private ThemingAuditService $audit,
		private IL10N $l,
	) {
		parent::__construct(appName: $appName, request: $request);
	}//end __construct()

	/**
	 * GET /settings/tokensets/from-colours: the rules and defaults the preview derives with.
	 *
	 * @return JSONResponse `{rules, defaults}`.
	 *
	 * @spec openspec/specs/simple-brand-form/spec.md#requirement-the-derived-set-is-complete
	 */
	#[AuthorizedAdminSetting(settings: Admin::class)]
	public function inputs(): JSONResponse {
		return new JSONResponse($this->brandForm->inputs(appPath: $this->apps->getAppPath('thematiq')));
	}//end inputs()

	/**
	 * POST /settings/tokensets/from-colours: derive and store the set.
	 *
	 * @return JSONResponse `{id, textOnPrimary, textRatio, uiRatio, warnings}`, or 400, 409 or 413.
	 *
	 * @spec openspec/specs/simple-brand-form/spec.md#requirement-an-administrator-creates-a-house-style-from-a-short-form
	 * @spec openspec/specs/simple-brand-form/spec.md#requirement-the-form-endpoint-is-admin-only
	 */
	#[AuthorizedAdminSetting(settings: Admin::class)]
	public function create(): JSONResponse {
		$name = trim((string)$this->request->getParam('name', ''));
		if ($name === '') {
			return new JSONResponse(['error' => $this->l->t('A token set name is required.')], 400);
		}

		try {
			$derived = $this->brandForm->derive(
				appPath: $this->apps->getAppPath('thematiq'),
				primary: (string)$this->request->getParam('primary', ''),
				background: (string)$this->request->getParam('background', '')
			);
		} catch (InvalidArgumentException $e) {
			return new JSONResponse(['error' => $this->l->t('Enter both colors as a hex color, for example #c8102e.')], 400);
		}

		$logo = $this->readLogo();
		if ($logo instanceof JSONResponse) {
			return $logo;
		}

		$primary = $derived['declarations']['--nldesign-color-primary'];
		try {
			$result = $this->store->store(
				displayName: $name,
				description: trim((string)$this->request->getParam('description', '')),
				declarations: $derived['declarations'],
				theming: ['primary_color' => $primary, 'background_color' => $derived['declarations']['--nldesign-color-nav-background']],
				logoAsset: $logo
			);
		} catch (RuntimeException $e) {
			$code = $e->getCode();
			if ($code < 400 || $code > 599) {
				$code = 500;
			}

			return new JSONResponse(['error' => $e->getMessage()], $code);
		}

		$this->audit->log(
			action: 'custom_set_uploaded',
			context: ['id' => $result['id'], 'name' => $name, 'declarationCount' => count($derived['declarations'])]
		);

		return new JSONResponse(
			[
				'id' => $result['id'],
				'textOnPrimary' => $derived['textOnPrimary'],
				'textRatio' => $derived['textRatio'],
				'uiRatio' => $derived['uiRatio'],
				'warnings' => $result['warnings'],
			]
		);
	}//end create()

	/**
	 * The optional logo upload, checked for type, size and the obvious forms of active content.
	 *
	 * @return array{path: string, contents: string}|JSONResponse|null The asset, an error, or null when none was sent.
	 */
	private function readLogo(): array|JSONResponse|null {
		$file = $this->request->getUploadedFile(key: 'logo');
		if (is_array($file) === false || isset($file['tmp_name']) === false || (string)$file['tmp_name'] === '') {
			return null;
		}

		$extension = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
		$size = (int)($file['size'] ?? 0);
		if ($size > CustomTokenSetValidator::MAX_SIZE) {
			return new JSONResponse(['error' => $this->l->t('The logo exceeds the 512 KB size limit.')], 413);
		}

		$contents = (string)file_get_contents((string)$file['tmp_name']);
		if (in_array($extension, self::LOGO_TYPES, true) === false || $this->isImage(extension: $extension, contents: $contents) === false) {
			return new JSONResponse(['error' => $this->l->t('The logo must be an SVG, PNG, JPG, GIF or WebP image.')], 400);
		}

		return ['path' => 'logo.' . $extension, 'contents' => $contents];
	}//end readLogo()

	/**
	 * Whether the bytes are an image of the claimed type.
	 *
	 * For an SVG this is a coarse filter, not a sanitiser: it refuses the
	 * obvious script, event handler, `javascript:` and `<foreignObject>`
	 * spellings, and a namespaced `<s:script>` or an entity-encoded
	 * `&#106;avascript:` still pass. What keeps an SVG logo from running
	 * anything is the content security policy it is served with:
	 * RuntimeFileController::serve() allows no scripts or loads, and core
	 * theming's image endpoint sends its own. Logos the converter decodes out
	 * of an uploaded or gallery theme do not come through this check at all.
	 *
	 * @param string $extension The claimed type.
	 * @param string $contents  The bytes.
	 *
	 * @return boolean True when it is a plain image.
	 */
	private function isImage(string $extension, string $contents): bool {
		if ($extension !== 'svg') {
			return getimagesizefromstring($contents) !== false;
		}

		return str_contains($contents, '<svg') === true
			&& preg_match('/<script|\son[a-z]+\s*=|javascript:|<foreignObject/i', $contents) !== 1;
	}//end isImage()
}//end class
