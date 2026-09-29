<?php

/**
 * Stylesheet layer manifest for applying a token set without a reload.
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
 * @spec openspec/changes/apply-without-reload/specs/css-architecture/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Controller;

use OCA\Thematiq\Service\CssInjectionService;
use OCA\Thematiq\Service\TokenSetService;
use OCA\Thematiq\Settings\Admin;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * Answers "which stylesheet elements make up this token set on a page".
 *
 * The admin panel applies a set to the page the admin is looking at by
 * removing the elements the current set produced and inserting the ones the
 * new set produces. It cannot know which `<link>`s are Thematiq's or in which
 * order they go — only `CssInjectionService` knows that, because it emits
 * them. So the client asks, and the answer comes from the same layer list the
 * page render uses: one owner for the cascade, no second copy in JavaScript.
 *
 * A separate controller rather than another `SettingsController` method: that
 * class already carries eight services and seven test files construct it by
 * hand; this needs two.
 *
 * @spec openspec/changes/apply-without-reload/specs/css-architecture/spec.md
 */
class LayerController extends Controller {

	/**
	 * Owns the cascade and builds the manifest from it.
	 *
	 * @var CssInjectionService
	 */
	private CssInjectionService $injection;

	/**
	 * Validates the requested set id before anything is computed for it.
	 *
	 * @var TokenSetService
	 */
	private TokenSetService $tokenSets;

	/**
	 * Constructor.
	 *
	 * @param string              $appName   The app id.
	 * @param IRequest            $request   The current request.
	 * @param CssInjectionService $injection The cascade owner.
	 * @param TokenSetService     $tokenSets The token set catalogue.
	 */
	public function __construct(
		string $appName,
		IRequest $request,
		CssInjectionService $injection,
		TokenSetService $tokenSets,
	) {
		parent::__construct(appName: $appName, request: $request);
		$this->injection = $injection;
		$this->tokenSets = $tokenSets;
	}//end __construct()

	/**
	 * The ordered stylesheet layers a token set puts on a page.
	 *
	 * Read-only and free of side effects: nothing is applied, the active set
	 * is not consulted, and the answer for a given id depends only on the
	 * files on disk and the toggles (dark variants, Marianne, fonts) that gate
	 * a layer — the same inputs the page render reads.
	 *
	 * @param string $tokenSetId The token set id.
	 *
	 * @return JSONResponse `{tokenSet, designSystem, layers: [{layer, kind, href|css, id?}]}`, or 404.
	 *
	 * @spec openspec/changes/apply-without-reload/specs/css-architecture/spec.md
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function getStylesheets(string $tokenSetId): JSONResponse {
		if ($this->tokenSets->isValidTokenSet(tokenSetId: $tokenSetId) === false) {
			return new JSONResponse(['error' => 'Token set not found'], 404);
		}

		return new JSONResponse($this->injection->getStylesheetManifest(tokenSet: $tokenSetId));
	}//end getStylesheets()
}//end class
