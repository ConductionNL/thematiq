<?php

/**
 * NL Design Font Controller.
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
 * @spec openspec/specs/custom-fonts/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Controller;

use OCA\Thematiq\Service\FontService;
use OCA\Thematiq\Service\FontValidator;
use OCA\Thematiq\Settings\Admin;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AnonRateLimit;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\IL10N;
use OCP\IRequest;
use RuntimeException;

/**
 * Font upload lifecycle (admin-only) plus the deliberately public serving
 * routes CSS `url()` font loads depend on.
 *
 * `upload()`, `list()` and `delete()` mirror {@see CustomTokenSetController}:
 * admin-only via AuthorizedAdminSetting, CSRF-protected (no
 * NoCSRFRequired). `serve()` and `css()` are the exception: a CSS `url()`
 * font load carries no CSRF token and no session guarantee, and MUST work
 * on the pre-login page before any session exists, so both are annotated
 * `#[PublicPage]` + `#[NoCSRFRequired]` — a deliberate public surface
 * serving admin-curated binaries addressed by opaque manifest id, not a
 * data leak (route-auth + semantic-auth gate rationale, documented again at
 * each method below).
 *
 * @spec openspec/specs/custom-fonts/spec.md
 */
class FontController extends Controller {
	use ErrorStatusTrait;

	/**
	 * The font storage/lifecycle service.
	 *
	 * @var FontService
	 */
	private FontService $service;

	/**
	 * The localization service.
	 *
	 * @var IL10N
	 */
	private IL10N $l;

	/**
	 * Constructor.
	 *
	 * @param string $appName The app name.
	 * @param IRequest $request The request object.
	 * @param FontService $service The font storage/lifecycle service.
	 * @param IL10N $l The localization service.
	 */
	public function __construct(
		string $appName,
		IRequest $request,
		FontService $service,
		IL10N $l,
	) {
		parent::__construct(appName: $appName, request: $request);
		$this->service = $service;
		$this->l = $l;
	}//end __construct()

	/**
	 * Upload a custom font.
	 *
	 * Accepts a multipart upload with a `font` file field, a `name` field
	 * (display name), and a `role` field (`body`|`heading`). The file is
	 * validated (WOFF2 magic bytes, 2 MB cap, per-instance cap of 20) and
	 * stored; validator failures are mapped to their HTTP status.
	 *
	 * @return JSONResponse `{ id }` on success, or `{ error }` with the
	 *                      mapped status (400 no file/unreadable, 413 oversize, 422 bad
	 *                      name/role/content, 409 collision or cap reached).
	 *
	 * @spec openspec/specs/custom-fonts/spec.md
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function upload(): JSONResponse {
		$name = trim((string)$this->request->getParam('name', ''));
		$role = trim((string)$this->request->getParam('role', 'body'));

		$file = $this->request->getUploadedFile(key: 'font');
		if (empty($file) === true || isset($file['tmp_name']) === false) {
			return new JSONResponse(['error' => $this->l->t('No font file uploaded.')], 400);
		}

		if (($file['size'] ?? 0) > FontValidator::MAX_SIZE) {
			return new JSONResponse(['error' => $this->l->t('Font file exceeds the 2 MB size limit.')], 413);
		}

		$bytes = file_get_contents($file['tmp_name']);
		if ($bytes === false) {
			return new JSONResponse(['error' => $this->l->t('Could not read the uploaded file.')], 400);
		}

		try {
			$result = $this->service->store(
				displayName: $name,
				role: $role,
				bytes: $bytes,
				reportedSize: (int)($file['size'] ?? strlen($bytes))
			);
		} catch (RuntimeException $e) {
			return new JSONResponse(['error' => $e->getMessage()], $this->errorStatus(exception: $e, fallback: 500));
		}

		return new JSONResponse(['id' => $result['id']]);
	}//end upload()

	/**
	 * List stored fonts with their metadata.
	 *
	 * @return JSONResponse `{ fonts: [...] }`.
	 *
	 * @spec openspec/specs/custom-fonts/spec.md
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function list(): JSONResponse {
		return new JSONResponse(['fonts' => $this->service->list()]);
	}//end list()

	/**
	 * Delete a font (file + manifest entry).
	 *
	 * @param string $id The font id.
	 *
	 * @return JSONResponse The deletion result, or 404 when unknown.
	 *
	 * @spec openspec/specs/custom-fonts/spec.md
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function delete(string $id): JSONResponse {
		if ($this->service->delete(id: $id) === false) {
			return new JSONResponse(['error' => $this->l->t('Font not found.')], 404);
		}

		return new JSONResponse(['status' => 'ok']);
	}//end delete()

	/**
	 * Serve a stored font's binary bytes.
	 *
	 * Deliberately public: a CSS `url()` font load carries no CSRF token
	 * and no session guarantee, and MUST succeed from the pre-login page
	 * (no session exists yet). The route serves only admin-curated static
	 * binaries addressed by an opaque manifest id — the manifest lookup
	 * (never a filesystem path built from the request) is the sole
	 * authorization gate, so this is a considered public surface, not a
	 * data leak (route-auth + semantic-auth gate rationale).
	 *
	 * Self-hosted webfonts. Every themed page pulls several of these, and the
	 * whole point of self-hosting them is that they load without a third-party
	 * request — so the ceiling is the loosest in the app. The id names a
	 * published font, not a credential, so no brute-force counter.
	 *
	 * @param string $id The font id (without the `.woff2` suffix — the
	 *                   route pattern strips it).
	 *
	 * @return Response The font bytes with immutable long-lived caching, or
	 *                  a bare 404 for an unknown id (no body detail).
	 *
	 * @spec openspec/specs/custom-fonts/spec.md
	 */
	#[PublicPage]
	#[NoCSRFRequired]
	#[AnonRateLimit(limit: 480, period: 60)]
	public function serve(string $id): Response {
		$content = $this->service->readFontBytes(id: $id);
		if ($content === null) {
			return new Response(Http::STATUS_NOT_FOUND);
		}

		$entry = $this->service->getEntry(id: $id);
		$response = new DataDisplayResponse($content, Http::STATUS_OK, ['Content-Type' => 'font/woff2']);
		$this->applyImmutableCache(response: $response);
		$response->setETag((string)($entry['rev'] ?? $this->service->getRevision()));

		return $response;
	}//end serve()

	/**
	 * Serve the generated `@font-face` + font-token stylesheet.
	 *
	 * Same deliberate public posture as {@see serve()} and for the same
	 * reason: the stylesheet is itself loaded via a `<link>` that must
	 * resolve before login. An empty manifest yields a 200 with an empty
	 * body (no `@font-face` rules), so a themed instance with zero uploaded
	 * fonts still resolves the URL cleanly if ever requested directly.
	 *
	 * The `@font-face` stylesheet — one fetch per page load, ahead of the fonts
	 * it declares.
	 *
	 * @return Response The generated stylesheet with immutable long-lived
	 *                  caching (the injected `<link>` carries `?v=<revision>` so a new
	 *                  upload/delete is picked up via a new URL, not a stale cache hit).
	 *
	 * @spec openspec/specs/custom-fonts/spec.md
	 */
	#[PublicPage]
	#[NoCSRFRequired]
	#[AnonRateLimit(limit: 480, period: 60)]
	public function css(): Response {
		$response = new DataDisplayResponse($this->service->buildCss(), Http::STATUS_OK, ['Content-Type' => 'text/css']);
		$this->applyImmutableCache(response: $response);
		$response->setETag((string)$this->service->getRevision());

		return $response;
	}//end css()

	/**
	 * Apply the immutable long-lived cache header shared by {@see serve()}
	 * and {@see css()}.
	 *
	 * Sets `Cache-Control` directly (rather than via `Response::cacheFor()`,
	 * which additionally resolves `\OCP\Server::get(ITimeFactory::class)`
	 * to stamp an `Expires` header) so the exact header value required by
	 * the spec is under our control and independent of a live service
	 * container — `Cache-Control` alone is what {@see spec} mandates.
	 *
	 * @param Response $response The response to annotate.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/custom-fonts/spec.md
	 */
	private function applyImmutableCache(Response $response): void {
		$response->addHeader('Cache-Control', 'public, max-age=31536000, immutable');
	}//end applyImmutableCache()
}//end class
