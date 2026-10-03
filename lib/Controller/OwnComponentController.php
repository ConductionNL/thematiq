<?php

/**
 * Thematiq own component controller.
 *
 * Admin-only list, save and delete of the playground's own components on
 * `/settings/playground/components`. Generic error texts; no audit entry, because a
 * saved component changes nothing any user sees.
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
 * @spec openspec/specs/own-component-preview/spec.md#requirement-an-administrator-saves-own-components
 */

declare(strict_types=1);

namespace OCA\Thematiq\Controller;

use InvalidArgumentException;
use OCA\Thematiq\Service\OwnComponentService;
use OCA\Thematiq\Settings\Admin;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IL10N;
use OCP\IRequest;
use Throwable;

/**
 * Own components over HTTP.
 *
 * @spec openspec/specs/own-component-preview/spec.md#requirement-an-administrator-saves-own-components
 */
class OwnComponentController extends Controller {

	/**
	 * Constructor.
	 *
	 * @param string $appName The app name.
	 * @param IRequest $request The request.
	 * @param OwnComponentService $components The store.
	 * @param IL10N $l The error texts.
	 */
	public function __construct(
		string $appName,
		IRequest $request,
		private OwnComponentService $components,
		private IL10N $l,
	) {
		parent::__construct(appName: $appName, request: $request);
	}//end __construct()

	/**
	 * Every saved component.
	 *
	 * @return JSONResponse The saved components and the count and size limits.
	 *
	 * @spec openspec/specs/own-component-preview/spec.md#requirement-an-administrator-saves-own-components
	 */
	#[AuthorizedAdminSetting(settings: Admin::class)]
	public function list(): JSONResponse {
		try {
			$components = $this->components->list();
		} catch (Throwable) {
			return new JSONResponse(['error' => $this->l->t('Your components could not be loaded.')], 500);
		}

		$limits = ['count' => OwnComponentService::MAX_COMPONENTS, 'bytes' => OwnComponentService::MAX_BYTES];

		return new JSONResponse(['components' => $components, 'limits' => $limits]);
	}//end list()

	/**
	 * Save a component by name.
	 *
	 * @return JSONResponse The stored component, or 400 naming the rule.
	 *
	 * @spec openspec/specs/own-component-preview/spec.md#requirement-an-administrator-saves-own-components
	 */
	#[AuthorizedAdminSetting(settings: Admin::class)]
	public function save(): JSONResponse {
		$texts = [
			'name' => $this->l->t('Give the component a name of at most 80 characters, with at least one letter or digit.'),
			'size' => $this->l->t('The HTML and CSS together may be at most 64 KB.'),
			'count' => $this->l->t('You can keep at most 20 components. Remove one first.'),
		];
		try {
			$component = $this->components->save(
				name: (string)$this->request->getParam('name', ''),
				html: (string)$this->request->getParam('html', ''),
				css: (string)$this->request->getParam('css', '')
			);
		} catch (InvalidArgumentException $e) {
			return new JSONResponse(['error' => ($texts[$e->getMessage()] ?? $this->l->t('The component was not saved.')), 'field' => $e->getMessage()], 400);
		} catch (Throwable) {
			return new JSONResponse(['error' => $this->l->t('The component was not saved.')], 500);
		}

		return new JSONResponse(['status' => 'ok', 'component' => $component]);
	}//end save()

	/**
	 * Remove a component.
	 *
	 * @param string $slug The component slug.
	 *
	 * @return JSONResponse Status ok, or 404.
	 *
	 * @spec openspec/specs/own-component-preview/spec.md#requirement-an-administrator-saves-own-components
	 */
	#[AuthorizedAdminSetting(settings: Admin::class)]
	public function delete(string $slug): JSONResponse {
		try {
			$this->components->delete(slug: $slug);
		} catch (InvalidArgumentException) {
			return new JSONResponse(['error' => $this->l->t('There is no component with this name.')], 404);
		}

		return new JSONResponse(['status' => 'ok']);
	}//end delete()
}//end class
