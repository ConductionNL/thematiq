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
	 * The content type per format.
	 *
	 * @var array<string, string>
	 */
	private const CONTENT_TYPES = ['html' => 'text/html; charset=utf-8', 'md' => 'text/markdown; charset=utf-8'];

	/**
	 * What the reference renders per format. The Markdown is a file an administrator hands on and
	 * reads as text, where every swatch image was a long data URL drowning the table: the value
	 * stays in each row as text; the docs pages keep their swatches.
	 *
	 * @var array<string, string>
	 */
	private const RENDERS = ['html' => 'html', 'md' => 'md-plain'];

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
		$set = $this->setById(id: $id);
		if ($set === null) {
			return new DataDisplayResponse(data: 'Unknown token set', statusCode: 404, headers: ['Content-Type' => 'text/plain; charset=utf-8']);
		}

		$format = $this->requestedFormat();
		$body = $this->reference->render(appPath: $this->apps->getAppPath('thematiq'), set: $set, format: self::RENDERS[$format]);
		$response = new DataDisplayResponse(data: $body, statusCode: 200, headers: ['Content-Type' => self::CONTENT_TYPES[$format]]);
		if ((string)$this->request->getParam('download', '') === '1') {
			$response->addHeader('Content-Disposition', 'attachment; filename="' . $id . '-tokens.' . $format . '"');
		}

		return $response;
	}//end show()

	/**
	 * The catalogue entry of a token set.
	 *
	 * @param string $id The set id.
	 *
	 * @return array<string, mixed>|null The entry, or null for an unknown set.
	 */
	private function setById(string $id): array|null {
		foreach ($this->tokenSets->getAvailableTokenSets() as $candidate) {
			if (($candidate['id'] ?? null) === $id) {
				return $candidate;
			}
		}

		return null;
	}//end setById()

	/**
	 * The format asked for: `md` for Markdown, anything else HTML.
	 *
	 * @return string `md` or `html`.
	 */
	private function requestedFormat(): string {
		if ($this->request->getParam('format') === 'md') {
			return 'md';
		}

		return 'html';
	}//end requestedFormat()
}//end class
