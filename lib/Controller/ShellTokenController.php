<?php

/**
 * Thematiq shell tokens controller.
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
 * @spec openspec/changes/shell-tokens-endpoint/specs/shell-tokens/spec.md#requirement-another-product-reads-the-active-shell-tokens
 */

declare(strict_types=1);

namespace OCA\Thematiq\Controller;

use OCA\Thematiq\AppInfo\Application;
use OCA\Thematiq\Service\ShellTokenService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AnonRateLimit;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\IRequest;

/**
 * GET /api/shell-tokens: the active token set as eighteen shell roles and a
 * logo, for a product that wears the same shell as this Nextcloud (the
 * OpenProject plugin is the first).
 *
 * Public, like the font, icon and runtime file routes. The reader is another
 * server, which has no Nextcloud session and should not need a Nextcloud
 * account to learn a colour. Every value here already reaches any visitor as
 * CSS on the login page, so the route discloses nothing new: it holds no
 * user, no group and no setting, and it answers for the instance-wide set
 * only (never a group's set or a preview). The alternative, a signed-in route
 * read with an app password, would hand the other server a credential that
 * opens far more than these eighteen values.
 *
 * It takes no input, so there is nothing to guess and no brute-force counter;
 * the ceiling is the same one the health route has.
 *
 * @spec openspec/changes/shell-tokens-endpoint/specs/shell-tokens/spec.md#requirement-another-product-reads-the-active-shell-tokens
 */
class ShellTokenController extends Controller {

	/**
	 * Constructor.
	 *
	 * @param IRequest $request The request.
	 * @param ShellTokenService $shellTokens Puts the document together.
	 *
	 * @return void
	 */
	public function __construct(
		IRequest $request,
		private readonly ShellTokenService $shellTokens,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * The shell tokens document, or 304 when the reader already holds this revision.
	 *
	 * `Cache-Control: no-cache` lets a reader keep the answer and ask again
	 * with `If-None-Match`, where Nextcloud's default forbids storing it.
	 *
	 * @return Response The document as JSON with its `ETag`, or an empty 304 with the same `ETag`.
	 *
	 * @spec openspec/changes/shell-tokens-endpoint/specs/shell-tokens/spec.md#requirement-another-product-reads-the-active-shell-tokens
	 * @spec openspec/changes/shell-tokens-endpoint/specs/shell-tokens/spec.md#requirement-an-unchanged-document-is-answered-with-304
	 */
	#[PublicPage]
	#[NoCSRFRequired]
	#[AnonRateLimit(limit: 120, period: 60)]
	public function show(): Response {
		$document = $this->shellTokens->document();

		$response = new JSONResponse($document);
		if ($this->holdsRevision(header: $this->request->getHeader('If-None-Match'), revision: $document['revision']) === true) {
			$response = new Response(Http::STATUS_NOT_MODIFIED);
		}

		$response->setETag($document['revision']);
		$response->addHeader('Cache-Control', 'no-cache');

		return $response;
	}//end show()

	/**
	 * Whether an `If-None-Match` header names this revision.
	 *
	 * The header may list several validators, may mark one weak (`W/`), and
	 * may be `*`, which matches whatever exists.
	 *
	 * @param string $header The header value, empty when it was not sent.
	 * @param string $revision The document's revision.
	 *
	 * @return bool True when the reader's copy is current.
	 */
	private function holdsRevision(string $header, string $revision): bool {
		foreach (explode(',', $header) as $validator) {
			$validator = trim($validator);
			if (str_starts_with($validator, 'W/') === true) {
				$validator = substr($validator, 2);
			}

			if ($validator === '*' || trim($validator, '"') === $revision) {
				return true;
			}
		}

		return false;
	}//end holdsRevision()
}//end class
