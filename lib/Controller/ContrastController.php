<?php

/**
 * NL Design Contrast Controller.
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
 * @spec openspec/specs/app-token-set-selection/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Controller;

use OCA\Thematiq\Service\ContrastService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * Shared WCAG contrast-evaluation endpoint for a leaf app's own picker or
 * free-hand color authoring feature.
 *
 * `#[NoAdminRequired]` — authenticated non-admin user, NOT `#[PublicPage]`.
 * No `#[NoCSRFRequired]`: this is a state-free POST from an authenticated,
 * same-origin browser session, which carries the Nextcloud request token
 * automatically, so the framework's default CSRF requirement applies
 * unchanged (matching every other session-authenticated POST in this app).
 *
 * The response is diagnostic data only — ratio/threshold/level/pass per
 * candidate — and NEVER a `blocked`/`allowed`/`verdict` field. Evaluating a
 * SELECTION of an existing catalogue entry (`GET /api/token-sets`) MUST
 * always be treated as a warning by the caller, never a hard block
 * (design.md decision 4); a caller's own free-hand custom-color authoring
 * policy is that caller's decision, not this endpoint's.
 *
 * @spec openspec/specs/app-token-set-selection/spec.md
 */
class ContrastController extends Controller {

	/**
	 * The WCAG contrast math service.
	 *
	 * @var ContrastService
	 */
	private ContrastService $contrastService;

	/**
	 * Constructor.
	 *
	 * @param string $appName The app name.
	 * @param IRequest $request The request object.
	 * @param ContrastService $contrastService The WCAG contrast math service.
	 */
	public function __construct(
		string $appName,
		IRequest $request,
		ContrastService $contrastService,
	) {
		parent::__construct(appName: $appName, request: $request);
		$this->contrastService = $contrastService;
	}//end __construct()

	/**
	 * POST /api/contrast/evaluate — evaluate arbitrary candidate colors
	 * against a caller-supplied background.
	 *
	 * Accepts `{ candidates: [{ name, value, role: "text"|"ui" }],
	 * background: string }` and returns `{ results: [{ name, ratio,
	 * threshold, level, pass, unevaluated? }] }`. A malformed request
	 * (missing/invalid `candidates` or `background`) returns 400, never a
	 * silent 200 with fabricated results.
	 *
	 * @return JSONResponse `{ results: [...] }`, or 400 on a malformed request.
	 *
	 * @spec openspec/specs/app-token-set-selection/spec.md
	 *
	 * @no-admin-idor-exempt Pure function over caller-supplied VALUES — there is
	 * no direct object reference to substitute, so IDOR is not structurally
	 * possible. The method takes zero parameters; the only request input is a
	 * `background` colour string and a `candidates` list of {name, value, role}
	 * triples, all of which the caller already possesses. It reads no id, no
	 * path and no session identity, and `ContrastService` has no constructor
	 * and no dependencies at all — no mapper, no ObjectService, no config, no
	 * filesystem — so the response is WCAG contrast arithmetic over the request
	 * body and nothing else. There is no stored object this endpoint can be
	 * pointed at, hence nothing an authorization guard could scope: adding one
	 * would assert a boundary that does not exist. `#[NoAdminRequired]` (not
	 * `#[PublicPage]`) is deliberate and remains the access control — the
	 * endpoint still requires an authenticated session.
	 */
	#[NoAdminRequired]
	public function evaluate(): JSONResponse {
		$params = $this->request->getParams();
		$background = ($params['background'] ?? null);
		$candidates = ($params['candidates'] ?? null);
		$error = $this->inputError(background: $background, candidates: $candidates);
		if ($error !== null) {
			return new JSONResponse(['error' => $error], 400);
		}

		$normalised = $this->normalisedCandidates(candidates: $candidates);
		if ($normalised === null) {
			return new JSONResponse(['error' => 'each candidate must have name, value, and role ("text"|"ui")'], 400);
		}

		$results = $this->contrastService->evaluate(candidates: $normalised, background: $background);

		return new JSONResponse(['results' => $results]);
	}//end evaluate()

	/**
	 * What is wrong with the request's background or candidates list, if anything.
	 *
	 * @param mixed $background The submitted background.
	 * @param mixed $candidates The submitted candidates.
	 *
	 * @return string|null The message, or null when both have the right shape.
	 */
	private function inputError(mixed $background, mixed $candidates): string|null {
		if (is_string($background) === false || $background === '') {
			return 'background must be a non-empty string';
		}

		if (is_array($candidates) === false) {
			return 'candidates must be an array';
		}

		return null;
	}//end inputError()

	/**
	 * The candidates with only their name, value and role, in submitted order.
	 *
	 * @param array<array-key, mixed> $candidates The submitted candidates.
	 *
	 * @return list<array{name: string, value: string, role: string}>|null The candidates, or null when one is malformed.
	 */
	private function normalisedCandidates(array $candidates): array|null {
		$normalised = [];
		foreach ($candidates as $candidate) {
			if ($this->isCandidate(candidate: $candidate) === false) {
				return null;
			}

			$normalised[] = [
				'name' => $candidate['name'],
				'value' => $candidate['value'],
				'role' => $candidate['role'],
			];
		}

		return $normalised;
	}//end normalisedCandidates()

	/**
	 * Whether a candidate has a string name and value and a role of `text` or `ui`.
	 *
	 * @param mixed $candidate The submitted candidate.
	 *
	 * @return bool True when it is well-formed.
	 */
	private function isCandidate(mixed $candidate): bool {
		return is_array($candidate) === true
			&& is_string($candidate['name'] ?? null) === true
			&& is_string($candidate['value'] ?? null) === true
			&& in_array($candidate['role'] ?? null, ['text', 'ui'], true) === true;
	}//end isCandidate()
}//end class
