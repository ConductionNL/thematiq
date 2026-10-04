<?php

/**
 * Thematiq Layout Options Service.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Service
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

namespace OCA\Thematiq\Service;

use InvalidArgumentException;
use OCA\Thematiq\AppInfo\Application;
use OCP\IConfig;

/**
 * Resolves the two layout options: the workplace layout and the brand stripe.
 *
 * Each option has three states, and the third is the reason this class exists.
 * An administrator can switch an option on or off, and that choice always
 * wins. Until they do, the stored value is empty and the option FOLLOWS THE
 * ACTIVE TOKEN SET: a set may carry defaults in its `layout` block in
 * token-sets.json, so a set designed for the light workplace wears it the
 * moment it is selected. A set without that block answers `default` and "no
 * stripe", which is what every set rendered before these options existed.
 *
 * The resolution takes the token set as an argument because the active set is
 * a per-request decision (per-group theming, per-app brands): the set a page
 * renders with decides the defaults, not the instance-wide one.
 *
 * @spec openspec/changes/zuiddrecht-workplace-theme/specs/workplace-layout/spec.md
 * @spec openspec/changes/zuiddrecht-workplace-theme/specs/brand-stripe/spec.md
 */
class LayoutOptionsService {

	/**
	 * The app config key holding the workplace layout choice.
	 *
	 * @var string
	 */
	public const WORKPLACE_LAYOUT_KEY = 'workplace_layout';

	/**
	 * The app config key holding the brand stripe choice.
	 *
	 * @var string
	 */
	public const BRAND_STRIPE_KEY = 'brand_stripe';

	/**
	 * The stored value that means "follow the active token set".
	 *
	 * @var string
	 */
	public const FOLLOW_SET = '';

	/**
	 * The layout every set had before the option existed.
	 *
	 * @var string
	 */
	public const LAYOUT_DEFAULT = 'default';

	/**
	 * The light workplace: a top bar on the main background with its text.
	 *
	 * @var string
	 */
	public const LAYOUT_LIGHT = 'light';

	/**
	 * The layouts an administrator or a set may name.
	 *
	 * @var array<int, string>
	 */
	public const LAYOUTS = [self::LAYOUT_DEFAULT, self::LAYOUT_LIGHT];

	/**
	 * Constructor.
	 *
	 * @param IConfig $config The config service.
	 * @param DesignSystemService $designSystemService Reads a set's manifest entry.
	 */
	public function __construct(
		private readonly IConfig $config,
		private readonly DesignSystemService $designSystemService,
	) {
	}//end __construct()

	/**
	 * The administrator's stored workplace layout choice.
	 *
	 * @return string `default`, `light`, or the empty string for "follow the set".
	 *
	 * @spec openspec/changes/zuiddrecht-workplace-theme/specs/workplace-layout/spec.md#requirement-the-workplace-layout-is-an-admin-option
	 */
	public function workplaceLayoutSetting(): string {
		$stored = $this->config->getAppValue(Application::APP_ID, self::WORKPLACE_LAYOUT_KEY, self::FOLLOW_SET);
		if (in_array($stored, self::LAYOUTS, true) === true) {
			return $stored;
		}

		return self::FOLLOW_SET;
	}//end workplaceLayoutSetting()

	/**
	 * The administrator's stored brand stripe choice.
	 *
	 * @return string `1`, `0`, or the empty string for "follow the set".
	 *
	 * @spec openspec/changes/zuiddrecht-workplace-theme/specs/brand-stripe/spec.md#requirement-the-brand-stripe-is-an-admin-option
	 */
	public function brandStripeSetting(): string {
		$stored = $this->config->getAppValue(Application::APP_ID, self::BRAND_STRIPE_KEY, self::FOLLOW_SET);
		if ($stored === '0' || $stored === '1') {
			return $stored;
		}

		return self::FOLLOW_SET;
	}//end brandStripeSetting()

	/**
	 * The layout defaults a token set carries in its manifest entry.
	 *
	 * @param string $tokenSet The token set id.
	 *
	 * @return array{workplaceLayout: string, brandStripe: bool} The set's defaults; `default` and false when it names none.
	 *
	 * @spec openspec/changes/zuiddrecht-workplace-theme/specs/workplace-layout/spec.md#requirement-a-token-set-may-carry-layout-defaults
	 */
	public function setDefaults(string $tokenSet): array {
		$meta = $this->designSystemService->getTokenSetMeta(tokenSetId: $tokenSet);
		$layout = ($meta['layout'] ?? null);
		if (is_array($layout) === false) {
			$layout = [];
		}

		$workplaceLayout = self::LAYOUT_DEFAULT;
		if (in_array(($layout['workplace_layout'] ?? null), self::LAYOUTS, true) === true) {
			$workplaceLayout = (string)$layout['workplace_layout'];
		}

		return [
			'workplaceLayout' => $workplaceLayout,
			'brandStripe' => (($layout['brand_stripe'] ?? false) === true),
		];
	}//end setDefaults()

	/**
	 * The workplace layout a page rendered with this token set wears.
	 *
	 * @param string $tokenSet The token set the page renders with.
	 *
	 * @return string `default` or `light`.
	 *
	 * @spec openspec/changes/zuiddrecht-workplace-theme/specs/workplace-layout/spec.md#requirement-a-token-set-may-carry-layout-defaults
	 */
	public function workplaceLayout(string $tokenSet): string {
		$stored = $this->workplaceLayoutSetting();
		if ($stored !== self::FOLLOW_SET) {
			return $stored;
		}

		return $this->setDefaults(tokenSet: $tokenSet)['workplaceLayout'];
	}//end workplaceLayout()

	/**
	 * Whether a page rendered with this token set draws the brand stripe.
	 *
	 * @param string $tokenSet The token set the page renders with.
	 *
	 * @return bool True when the stripe is on.
	 *
	 * @spec openspec/changes/zuiddrecht-workplace-theme/specs/brand-stripe/spec.md#requirement-the-brand-stripe-is-an-admin-option
	 */
	public function brandStripe(string $tokenSet): bool {
		$stored = $this->brandStripeSetting();
		if ($stored !== self::FOLLOW_SET) {
			return ($stored === '1');
		}

		return $this->setDefaults(tokenSet: $tokenSet)['brandStripe'];
	}//end brandStripe()

	/**
	 * Store the workplace layout choice.
	 *
	 * @param string $layout `default`, `light`, or the empty string to follow the set again.
	 *
	 * @return string The previously stored choice.
	 *
	 * @throws InvalidArgumentException When the value is not one of the three.
	 *
	 * @spec openspec/changes/zuiddrecht-workplace-theme/specs/workplace-layout/spec.md#requirement-the-workplace-layout-is-an-admin-option
	 */
	public function setWorkplaceLayout(string $layout): string {
		if ($layout !== self::FOLLOW_SET && in_array($layout, self::LAYOUTS, true) === false) {
			throw new InvalidArgumentException('Unknown workplace layout');
		}

		$previous = $this->workplaceLayoutSetting();
		$this->config->setAppValue(Application::APP_ID, self::WORKPLACE_LAYOUT_KEY, $layout);

		return $previous;
	}//end setWorkplaceLayout()

	/**
	 * Store the brand stripe choice.
	 *
	 * @param string $stripe `1`, `0`, or the empty string to follow the set again.
	 *
	 * @return string The previously stored choice.
	 *
	 * @throws InvalidArgumentException When the value is not one of the three.
	 *
	 * @spec openspec/changes/zuiddrecht-workplace-theme/specs/brand-stripe/spec.md#requirement-the-brand-stripe-is-an-admin-option
	 */
	public function setBrandStripe(string $stripe): string {
		if (in_array($stripe, [self::FOLLOW_SET, '0', '1'], true) === false) {
			throw new InvalidArgumentException('Unknown brand stripe value');
		}

		$previous = $this->brandStripeSetting();
		$this->config->setAppValue(Application::APP_ID, self::BRAND_STRIPE_KEY, $stripe);

		return $previous;
	}//end setBrandStripe()
}//end class
