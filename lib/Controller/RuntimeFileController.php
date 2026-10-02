<?php

/**
 * Serve the files thematiq writes at runtime.
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
 * @spec openspec/changes/runtime-files-in-appdata/specs/runtime-file-storage/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Controller;

use OCA\Thematiq\Service\RuntimeFile\RuntimeFileLocator;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AnonRateLimit;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\EmptyContentSecurityPolicy;
use OCP\AppFramework\Http\Response;
use OCP\IRequest;

/**
 * The runtime file route: overrides, custom CSS, uploaded sets and their
 * images, served from app data.
 *
 * These used to be static files inside the app directory, which the web
 * server served directly. They moved to app data so the signed app directory
 * stays as shipped, and this route is what serves them now.
 *
 * @spec openspec/changes/runtime-files-in-appdata/specs/runtime-file-storage/spec.md
 */
class RuntimeFileController extends Controller {

	/**
	 * Constructor.
	 *
	 * @param string             $appName The app name.
	 * @param IRequest           $request The request.
	 * @param RuntimeFileLocator $files   Finds runtime files.
	 *
	 * @spec openspec/changes/runtime-files-in-appdata/specs/runtime-file-storage/spec.md
	 */
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly RuntimeFileLocator $files,
	) {
		parent::__construct(appName: $appName, request: $request);
	}//end __construct()

	/**
	 * Serve one runtime file.
	 *
	 * Public on purpose, like the font route: the login page and public share
	 * pages are themed too, and a stylesheet `<link>` carries no session or
	 * CSRF token. What keeps it safe is that the name must pass
	 * {@see \OCA\Thematiq\Service\RuntimeFile\RuntimeFileNames::isAllowed()}, a closed set of patterns with no
	 * `..`, no absolute path and no other directory, and that only the runtime
	 * store is read: a shipped file is never served from here.
	 *
	 * Every response carries a policy that lets nothing load or run, so an
	 * uploaded SVG opened on its own renders as an image and cannot execute.
	 *
	 * @param string $name The runtime file name, such as `css/custom-overrides.css`.
	 *
	 * @return Response The file with an immutable cache header, or a bare 404.
	 *
	 * @spec openspec/changes/runtime-files-in-appdata/specs/runtime-file-storage/spec.md
	 */
	#[PublicPage]
	#[NoCSRFRequired]
	#[AnonRateLimit(limit: 480, period: 60)]
	public function serve(string $name): Response {
		if ($this->files->inStore(name: $name) === false) {
			return new Response(Http::STATUS_NOT_FOUND);
		}

		$content = $this->files->store()->read(name: $name);
		if ($content === null) {
			return new Response(Http::STATUS_NOT_FOUND);
		}

		$response = new DataDisplayResponse($content, Http::STATUS_OK, ['Content-Type' => $this->files->contentType(name: $name)]);
		$response->addHeader('Cache-Control', 'public, max-age=31536000, immutable');
		$response->addHeader('X-Content-Type-Options', 'nosniff');
		$response->setETag($this->files->store()->revision(name: $name));

		$policy = new EmptyContentSecurityPolicy();
		$policy->allowInlineStyle(true);
		$response->setContentSecurityPolicy($policy);

		return $response;
	}//end serve()
}//end class
