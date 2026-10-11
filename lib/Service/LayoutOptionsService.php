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
 * @spec openspec/changes/layout-options-navigation-stripe-watermark/specs/workplace-layout/spec.md
 * @spec openspec/changes/header-style-workplace/specs/workplace-layout/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use InvalidArgumentException;
use OCA\Thematiq\AppInfo\Application;
use OCP\IConfig;

/**
 * Resolves the layout options: the workplace layout, the brand stripe and its
 * placement, the navigation width, the selected navigation entry's style and
 * the login watermark.
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
 * @spec openspec/changes/layout-options-navigation-stripe-watermark/specs/workplace-layout/spec.md
 *
 * @SuppressWarnings(PHPMD.StaticAccess) - LayoutOptionValues holds pure lookups (what each option accepts and
 *   defaults to) that the controller and the configuration bundle share with this service.
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
	 * The `id` of the inline style that carries the navigation width.
	 *
	 * @var string
	 */
	public const NAVIGATION_WIDTH_STYLE_ID = 'thematiq-navigation-width';

	/**
	 * The stylesheet of the workplace header style, under `css/`. The page
	 * loads the header's script with it ({@see HeaderUserService}).
	 *
	 * @var string
	 */
	public const HEADER_WORKPLACE_STYLESHEET = 'header-workplace';

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
	 * @return array{
	 *     workplaceLayout: string,
	 *     brandStripe: bool,
	 *     navigationWidth: string,
	 *     navigationActiveStyle: string,
	 *     brandStripePlacement: string,
	 *     loginWatermark: string,
	 *     headerStyle: string
	 * } The set's defaults; the behaviour every set had when it names none.
	 *
	 * @spec openspec/changes/zuiddrecht-workplace-theme/specs/workplace-layout/spec.md#requirement-a-token-set-may-carry-layout-defaults
	 * @spec openspec/changes/layout-options-navigation-stripe-watermark/specs/workplace-layout/spec.md#requirement-a-token-set-may-carry-the-newer-layout-defaults
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

		$defaults = [
			'workplaceLayout' => $workplaceLayout,
			'brandStripe' => (($layout['brand_stripe'] ?? false) === true),
		];
		foreach (LayoutOptionValues::DEFAULTS as $key => $builtIn) {
			$declared = LayoutOptionValues::accepted(key: $key, value: ($layout[$key] ?? null));
			if ($declared === null || $declared === self::FOLLOW_SET) {
				$declared = $builtIn;
			}

			$defaults[self::camel(key: $key)] = $declared;
		}

		return $defaults;
	}//end setDefaults()

	/**
	 * The camel-case name an option goes by in a resolved array and a
	 * response: `navigation_width` is `navigationWidth`.
	 *
	 * @param string $key The app config key.
	 *
	 * @return string The camel-case name.
	 */
	private static function camel(string $key): string {
		return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $key))));
	}//end camel()

	/**
	 * Whether a value is one the option accepts from an administrator: the
	 * empty string (follow the set) or one of the option's values. Every
	 * layout option is known here, the two older ones included.
	 *
	 * @param string $key The app config key.
	 * @param string $value The value offered.
	 *
	 * @return bool True when the option accepts it.
	 *
	 * @spec openspec/changes/layout-options-navigation-stripe-watermark/specs/workplace-layout/spec.md#requirement-the-navigation-width-is-an-admin-option
	 */
	public function accepts(string $key, string $value): bool {
		$known = ($key === LayoutOptionValues::NAVIGATION_WIDTH_KEY || array_key_exists($key, LayoutOptionValues::VALUES) === true);

		return ($known === true && LayoutOptionValues::accepted(key: $key, value: $value) !== null);
	}//end accepts()

	/**
	 * The administrator's stored choice for one of the newer options
	 * (the two older ones have readers of their own).
	 *
	 * @param string $key The app config key.
	 *
	 * @return string The stored value, or the empty string for "follow the set"
	 *                (also for a value the option does not accept).
	 *
	 * @spec openspec/changes/layout-options-navigation-stripe-watermark/specs/workplace-layout/spec.md#requirement-the-navigation-width-is-an-admin-option
	 */
	public function setting(string $key): string {
		$stored = $this->config->getAppValue(Application::APP_ID, $key, self::FOLLOW_SET);

		return (LayoutOptionValues::accepted(key: $key, value: $stored) ?? self::FOLLOW_SET);
	}//end setting()

	/**
	 * What one of the newer options resolves to for a page rendered with
	 * this token set: the stored choice, else the set's default, else the
	 * built-in one.
	 *
	 * @param string $key The app config key.
	 * @param string $tokenSet The token set the page renders with.
	 *
	 * @return string The resolved value in its string form; the empty string
	 *                for a navigation width left to Nextcloud.
	 *
	 * @spec openspec/changes/layout-options-navigation-stripe-watermark/specs/workplace-layout/spec.md#requirement-the-navigation-width-is-an-admin-option
	 */
	public function resolve(string $key, string $tokenSet): string {
		$stored = $this->setting(key: $key);
		if ($stored !== self::FOLLOW_SET) {
			return $stored;
		}

		return (string)$this->setDefaults(tokenSet: $tokenSet)[self::camel(key: $key)];
	}//end resolve()

	/**
	 * Store a layout option; the two older ones go through their own setters.
	 *
	 * @param string $key The app config key.
	 * @param string $value The value, or the empty string to follow the set again.
	 *
	 * @return string The previously stored choice.
	 *
	 * @throws InvalidArgumentException When the option does not accept the value.
	 *
	 * @spec openspec/changes/layout-options-navigation-stripe-watermark/specs/workplace-layout/spec.md#requirement-the-navigation-width-is-an-admin-option
	 */
	public function setOption(string $key, string $value): string {
		if ($this->accepts(key: $key, value: $value) === false) {
			throw new InvalidArgumentException('Unknown layout option value');
		}

		if ($key === self::WORKPLACE_LAYOUT_KEY) {
			return $this->setWorkplaceLayout(layout: $value);
		}

		if ($key === self::BRAND_STRIPE_KEY) {
			return $this->setBrandStripe(stripe: $value);
		}

		$previous = $this->setting(key: $key);
		$this->config->setAppValue(Application::APP_ID, $key, $value);

		return $previous;
	}//end setOption()

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
	 * What a page rendered with this token set resolves to, for a client.
	 *
	 * @param string $tokenSet The token set the page renders with.
	 *
	 * @return array{
	 *     workplaceLayout: string,
	 *     brandStripe: bool,
	 *     navigationWidth: int|null,
	 *     navigationActiveStyle: string,
	 *     brandStripePlacement: string,
	 *     loginWatermark: bool,
	 *     headerStyle: string
	 * } The resolved options.
	 *
	 * @spec openspec/changes/zuiddrecht-workplace-theme/specs/workplace-layout/spec.md#requirement-a-token-set-may-carry-layout-defaults
	 * @spec openspec/changes/layout-options-navigation-stripe-watermark/specs/workplace-layout/spec.md#requirement-a-token-set-may-carry-the-newer-layout-defaults
	 */
	public function resolved(string $tokenSet): array {
		$width = null;
		$resolvedWidth = $this->resolve(key: LayoutOptionValues::NAVIGATION_WIDTH_KEY, tokenSet: $tokenSet);
		if ($resolvedWidth !== self::FOLLOW_SET) {
			$width = (int)$resolvedWidth;
		}

		return [
			'workplaceLayout' => $this->workplaceLayout(tokenSet: $tokenSet),
			'brandStripe' => $this->brandStripe(tokenSet: $tokenSet),
			'navigationWidth' => $width,
			'navigationActiveStyle' => $this->resolve(key: LayoutOptionValues::NAVIGATION_ACTIVE_STYLE_KEY, tokenSet: $tokenSet),
			'brandStripePlacement' => $this->resolve(key: LayoutOptionValues::BRAND_STRIPE_PLACEMENT_KEY, tokenSet: $tokenSet),
			'loginWatermark' => ($this->resolve(key: LayoutOptionValues::LOGIN_WATERMARK_KEY, tokenSet: $tokenSet) === '1'),
			'headerStyle' => $this->resolve(key: LayoutOptionValues::HEADER_STYLE_KEY, tokenSet: $tokenSet),
		];
	}//end resolved()

	/**
	 * The conditional stylesheets a page rendered with this token set loads,
	 * in cascade order, as paths under `css/` without the extension.
	 *
	 * @param string $tokenSet The token set the page renders with.
	 *
	 * @return array<int, string> `workplace-layout` and, while the layout is light, `login-watermark-off`
	 *                            when the watermark is off and `header-workplace` for the workplace header
	 *                            style; `brand-stripe` and, for a placement other
	 *                            than both, `brand-stripe-header-only` or `brand-stripe-login-only`;
	 *                            `navigation-width` while a width is resolved; `navigation-active-soft`
	 *                            for the soft style. Empty for a set that names none of it.
	 *
	 * @spec openspec/changes/zuiddrecht-workplace-theme/specs/workplace-layout/spec.md#requirement-the-light-layout-is-one-conditional-stylesheet
	 * @spec openspec/changes/zuiddrecht-workplace-theme/specs/brand-stripe/spec.md#requirement-the-stripe-is-one-conditional-stylesheet
	 * @spec openspec/changes/layout-options-navigation-stripe-watermark/specs/workplace-layout/spec.md#requirement-each-newer-option-is-one-conditional-stylesheet
	 * @spec openspec/changes/header-style-workplace/specs/workplace-layout/spec.md#requirement-the-header-style-is-a-layout-option
	 */
	public function stylesheets(string $tokenSet): array {
		$resolved = $this->resolved(tokenSet: $tokenSet);
		$files = [];
		if ($resolved['workplaceLayout'] === self::LAYOUT_LIGHT) {
			$files[] = 'workplace-layout';
			if ($resolved['loginWatermark'] === false) {
				$files[] = 'login-watermark-off';
			}

			if ($resolved['headerStyle'] === LayoutOptionValues::HEADER_STYLE_WORKPLACE) {
				$files[] = self::HEADER_WORKPLACE_STYLESHEET;
			}
		}

		if ($resolved['brandStripe'] === true) {
			$files[] = 'brand-stripe';
			if ($resolved['brandStripePlacement'] === LayoutOptionValues::PLACEMENT_HEADER) {
				$files[] = 'brand-stripe-header-only';
			} elseif ($resolved['brandStripePlacement'] === LayoutOptionValues::PLACEMENT_LOGIN) {
				$files[] = 'brand-stripe-login-only';
			}
		}

		if ($resolved['navigationWidth'] !== null) {
			$files[] = 'navigation-width';
		}

		if ($resolved['navigationActiveStyle'] === LayoutOptionValues::ACTIVE_STYLE_SOFT) {
			$files[] = 'navigation-active-soft';
		}

		return $files;
	}//end stylesheets()

	/**
	 * The inline styles a page rendered with this token set carries, after the
	 * conditional stylesheets: the navigation width as one `:root` variable
	 * that `css/navigation-width.css` reads. Each has an `id`, so the admin
	 * page can replace it without a reload.
	 *
	 * @param string $tokenSet The token set the page renders with.
	 *
	 * @return array<int, array{id: string, css: string}> Zero or one style.
	 *
	 * @spec openspec/changes/layout-options-navigation-stripe-watermark/specs/workplace-layout/spec.md#requirement-each-newer-option-is-one-conditional-stylesheet
	 */
	public function inlineStyles(string $tokenSet): array {
		$width = $this->resolve(key: LayoutOptionValues::NAVIGATION_WIDTH_KEY, tokenSet: $tokenSet);
		if ($width === self::FOLLOW_SET) {
			return [];
		}

		return [
			[
				'id' => self::NAVIGATION_WIDTH_STYLE_ID,
				'css' => ':root { --thematiq-navigation-width: ' . $width . 'px; }',
			],
		];
	}//end inlineStyles()

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
