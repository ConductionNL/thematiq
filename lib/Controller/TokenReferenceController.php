<?php

/**
 * Thematiq Token Reference Controller.
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
 * @spec openspec/specs/token-reference/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Controller;

use OCA\Thematiq\Service\TokenReferenceService;
use OCA\Thematiq\Service\TokenSetService;
use OCP\App\IAppManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\IRequest;

/**
 * The token reference of any available token set, custom sets included, for any signed-in
 * user. `#[NoAdminRequired]`, never `#[PublicPage]`: a custom set is the organisation's own
 * and may not be published yet. A controller of its own next to CatalogController, whose
 * constructor other code and tests pin.
 *
 * @spec openspec/specs/token-reference/spec.md
 */
class TokenReferenceController extends Controller {

	/**
	 * Constructor.
	 *
	 * @param string $appName The app name.
	 * @param IRequest $request The request.
	 * @param TokenSetService $tokenSets Available token sets, custom sets included.
	 * @param TokenReferenceService $reference The renderer.
	 * @param IAppManager $apps For the app root.
	 */
	public function __construct(
		string $appName,
		IRequest $request,
		private TokenSetService $tokenSets,
		private TokenReferenceService $reference,
		private IAppManager $apps,
	) {
		parent::__construct(appName: $appName, request: $request);
	}//end __construct()

	/**
	 * GET /api/token-sets/{id}/reference?format=md|html[&download=1]
	 *
	 * @param string $id The token set id.
	 *
	 * @return DataDisplayResponse The reference, or 404 for an unknown id.
	 *
	 * @spec openspec/specs/token-reference/spec.md#requirement-signed-in-users-read-the-reference-of-any-available-set
	 *
	 * @no-admin-idor-exempt token sets are instance-wide, not owned per user: every signed-in user already
	 *   receives every available set's tokens as the page stylesheet, and the spec grants the reference of
	 *   any available set to any signed-in user.
	 */
	#[NoAdminRequired]
	public function show(string $id): DataDisplayResponse {
		$set = null;
		foreach ($this->tokenSets->getAvailableTokenSets() as $candidate) {
			if (($candidate['id'] ?? null) === $id) {
				$set = $candidate;
				break;
			}
		}

		if ($set === null) {
			return new DataDisplayResponse(data: 'Unknown token set', statusCode: 404, headers: ['Content-Type' => 'text/plain; charset=utf-8']);
		}

		$format = 'html';
		$type = 'text/html; charset=utf-8';
		if ($this->request->getParam('format') === 'md') {
			$format = 'md';
			$type = 'text/markdown; charset=utf-8';
		}

		// The Markdown here is a file an administrator hands on and reads as text,
		// where every swatch image was a long data URL drowning the table. The
		// value stays in each row as text; the docs pages keep their swatches.
		$render = $format;
		if ($format === 'md') {
			$render = 'md-plain';
		}

		$body = $this->reference->render(appPath: $this->apps->getAppPath('thematiq'), set: $set, format: $render);
		$response = new DataDisplayResponse(data: $body, statusCode: 200, headers: ['Content-Type' => $type]);
		if ((string)$this->request->getParam('download', '') === '1') {
			$response->addHeader('Content-Disposition', 'attachment; filename="' . $id . '-tokens.' . $format . '"');
		}

		return $response;
	}//end show()
}//end class
