<?php

/**
 * The layout options: workplace layout, brand stripe and its placement,
 * navigation width, the selected navigation entry's style, login watermark,
 * header style.
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
 * @spec openspec/changes/zuiddrecht-workplace-theme/specs/workplace-layout/spec.md
 * @spec openspec/changes/zuiddrecht-workplace-theme/specs/brand-stripe/spec.md
 * @spec openspec/changes/layout-options-navigation-stripe-watermark/specs/workplace-layout/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Controller;

use OCA\Thematiq\Service\ActiveTokenSetService;
use OCA\Thematiq\Service\LayoutOptionsService;
use OCA\Thematiq\Service\LayoutOptionValues;
use OCA\Thematiq\Service\ThemingAuditService;
use OCA\Thematiq\Settings\Admin;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * Stores the two layout options an administrator can set next to the other
 * display toggles, and answers with what a page now resolves to.
 *
 * A separate controller rather than another `SettingsController` method, for
 * the reason `LayerController` gives: that class is constructed by hand in
 * seven test files, and this needs three services of its own.
 *
 * @spec openspec/changes/zuiddrecht-workplace-theme/specs/workplace-layout/spec.md
 * @spec openspec/changes/zuiddrecht-workplace-theme/specs/brand-stripe/spec.md
 */
class LayoutController extends Controller {

	/**
	 * The request parameter each layout option is read from, and its app config key, in the order they are stored.
	 *
	 * @var array<string, string>
	 */
	private const FIELDS = [
		'workplaceLayout' => LayoutOptionsService::WORKPLACE_LAYOUT_KEY,
		'brandStripe' => LayoutOptionsService::BRAND_STRIPE_KEY,
		'navigationWidth' => LayoutOptionValues::NAVIGATION_WIDTH_KEY,
		'navigationActiveStyle' => LayoutOptionValues::NAVIGATION_ACTIVE_STYLE_KEY,
		'brandStripePlacement' => LayoutOptionValues::BRAND_STRIPE_PLACEMENT_KEY,
		'loginWatermark' => LayoutOptionValues::LOGIN_WATERMARK_KEY,
		'headerStyle' => LayoutOptionValues::HEADER_STYLE_KEY,
	];

	/**
	 * Constructor.
	 *
	 * @param string $appName The app id.
	 * @param IRequest $request The current request.
	 * @param LayoutOptionsService $layoutOptions Stores and resolves the options.
	 * @param ActiveTokenSetService $activeTokenSet Names the instance-wide token set.
	 * @param ThemingAuditService $auditService Records each change.
	 */
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly LayoutOptionsService $layoutOptions,
		private readonly ActiveTokenSetService $activeTokenSet,
		private readonly ThemingAuditService $auditService,
	) {
		parent::__construct(appName: $appName, request: $request);
	}//end __construct()

	/**
	 * Store the layout options: the workplace layout, the brand stripe and
	 * its placement, the navigation width, the selected navigation entry's
	 * style and the login watermark.
	 *
	 * Each option is read from the request parameter named in FIELDS:
	 * `workplaceLayout` (`default`, `light`), `brandStripe` (`1`, `0`),
	 * `navigationWidth` (a whole number of pixels, 200 to 480),
	 * `navigationActiveStyle` (`default`, `soft`), `brandStripePlacement`
	 * (`header-and-login`, `header`, `login`), `loginWatermark` (`1`, `0`) and
	 * `headerStyle` (`default`, `workplace`); empty or absent follows the theme.
	 *
	 * Every value is validated before any is written, so a request with one
	 * bad value changes nothing. The answer carries the stored choices and
	 * what the instance-wide token set now resolves to, which is what the
	 * admin panel needs to add or drop the stylesheets on the open page.
	 *
	 * @return JSONResponse The stored and the resolved state, or HTTP 400 on an unknown value.
	 *
	 * @spec openspec/changes/zuiddrecht-workplace-theme/specs/workplace-layout/spec.md#requirement-the-workplace-layout-is-an-admin-option
	 * @spec openspec/changes/zuiddrecht-workplace-theme/specs/brand-stripe/spec.md#requirement-the-brand-stripe-is-an-admin-option
	 * @spec openspec/changes/layout-options-navigation-stripe-watermark/specs/workplace-layout/spec.md#requirement-the-navigation-width-is-an-admin-option
	 * @spec openspec/changes/header-style-workplace/specs/workplace-layout/spec.md#requirement-the-header-style-is-a-layout-option
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function save(): JSONResponse {
		$values = $this->requestedValues();
		if ($values === null || $this->acceptsAll(values: $values) === false) {
			return new JSONResponse(['error' => 'Unknown layout option'], 400);
		}

		foreach ($values as $key => $value) {
			$previous = $this->layoutOptions->setOption(key: $key, value: $value);
			$this->logChange(key: $key, old: $previous, new: $value);
		}

		$answer = ['status' => 'ok'];
		foreach (self::FIELDS as $param => $key) {
			$answer[$param] = $values[$key];
		}

		$answer['resolved'] = $this->layoutOptions->resolved(tokenSet: $this->activeTokenSet->getActive());

		return new JSONResponse($answer);
	}//end save()

	/**
	 * The option values the request carries, by app config key; absent ones are empty.
	 *
	 * @return array<string, string>|null The values, or null when one is not a string.
	 */
	private function requestedValues(): ?array {
		$values = [];
		foreach (self::FIELDS as $param => $key) {
			$value = $this->request->getParam($param, '');
			if (is_string($value) === false) {
				return null;
			}

			$values[$key] = $value;
		}

		return $values;
	}//end requestedValues()

	/**
	 * Whether every value is one its option accepts.
	 *
	 * @param array<string, string> $values The values, by app config key.
	 *
	 * @return bool
	 */
	private function acceptsAll(array $values): bool {
		foreach ($values as $key => $value) {
			if ($this->layoutOptions->accepts(key: $key, value: $value) === false) {
				return false;
			}
		}

		return true;
	}//end acceptsAll()

	/**
	 * Write one audit entry for an option that changed.
	 *
	 * @param string $key The app config key.
	 * @param string $old The previously stored value.
	 * @param string $new The value just stored.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/theming-audit/spec.md#requirement-complete-call-site-coverage
	 */
	private function logChange(string $key, string $old, string $new): void {
		if ($old === $new) {
			return;
		}

		$this->auditService->log(
			action: 'toggle_changed',
			context: [
				'key' => $key,
				'old' => $old,
				'new' => $new,
			]
		);
	}//end logChange()
}//end class
