<?php

/**
 * The layout options: workplace layout and brand stripe.
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
 */

declare(strict_types=1);

namespace OCA\Thematiq\Controller;

use InvalidArgumentException;
use OCA\Thematiq\Service\ActiveTokenSetService;
use OCA\Thematiq\Service\LayoutOptionsService;
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
	 * Store the workplace layout and the brand stripe.
	 *
	 * Both values are validated before either is written, so a request with
	 * one bad value changes nothing. The answer carries the stored choices and
	 * what the instance-wide token set now resolves to, which is what the
	 * admin panel needs to add or drop the two stylesheets on the open page.
	 *
	 * @param string $workplaceLayout `default`, `light`, or empty to follow the theme.
	 * @param string $brandStripe `1`, `0`, or empty to follow the theme.
	 *
	 * @return JSONResponse The stored and the resolved state, or HTTP 400 on an unknown value.
	 *
	 * @spec openspec/changes/zuiddrecht-workplace-theme/specs/workplace-layout/spec.md#requirement-the-workplace-layout-is-an-admin-option
	 * @spec openspec/changes/zuiddrecht-workplace-theme/specs/brand-stripe/spec.md#requirement-the-brand-stripe-is-an-admin-option
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function save(string $workplaceLayout = '', string $brandStripe = ''): JSONResponse {
		$layoutKnown = ($workplaceLayout === LayoutOptionsService::FOLLOW_SET
			|| in_array($workplaceLayout, LayoutOptionsService::LAYOUTS, true) === true);
		$stripeKnown = in_array($brandStripe, [LayoutOptionsService::FOLLOW_SET, '0', '1'], true);
		if ($layoutKnown === false || $stripeKnown === false) {
			return new JSONResponse(['error' => 'Unknown layout option'], 400);
		}

		try {
			$previousLayout = $this->layoutOptions->setWorkplaceLayout(layout: $workplaceLayout);
			$previousStripe = $this->layoutOptions->setBrandStripe(stripe: $brandStripe);
		} catch (InvalidArgumentException $e) {
			return new JSONResponse(['error' => 'Unknown layout option'], 400);
		}

		$this->logChange(key: LayoutOptionsService::WORKPLACE_LAYOUT_KEY, old: $previousLayout, new: $workplaceLayout);
		$this->logChange(key: LayoutOptionsService::BRAND_STRIPE_KEY, old: $previousStripe, new: $brandStripe);

		$tokenSet = $this->activeTokenSet->getActive();

		return new JSONResponse(
			[
				'status' => 'ok',
				'workplaceLayout' => $workplaceLayout,
				'brandStripe' => $brandStripe,
				'resolved' => $this->layoutOptions->resolved(tokenSet: $tokenSet),
			]
		);
	}//end save()

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
