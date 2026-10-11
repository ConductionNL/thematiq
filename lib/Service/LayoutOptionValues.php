<?php

/**
 * Thematiq Layout Option Values.
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
 * @spec openspec/changes/layout-options-navigation-stripe-watermark/specs/workplace-layout/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * What each layout option is called, what it accepts and what it means when
 * nobody says anything: pure lookups with no dependencies, so the service
 * that resolves the options, the controller that stores them and the
 * configuration bundle that carries them all validate the same way.
 *
 * @spec openspec/changes/layout-options-navigation-stripe-watermark/specs/workplace-layout/spec.md
 * @spec openspec/changes/layout-options-navigation-stripe-watermark/specs/config-portability/spec.md
 */
final class LayoutOptionValues {

	/**
	 * The app config key holding the navigation width, in pixels.
	 *
	 * @var string
	 */
	public const NAVIGATION_WIDTH_KEY = 'navigation_width';

	/**
	 * The app config key holding the selected navigation entry's style.
	 *
	 * @var string
	 */
	public const NAVIGATION_ACTIVE_STYLE_KEY = 'navigation_active_style';

	/**
	 * The app config key holding where the brand stripe is drawn.
	 *
	 * @var string
	 */
	public const BRAND_STRIPE_PLACEMENT_KEY = 'brand_stripe_placement';

	/**
	 * The app config key holding the login watermark choice.
	 *
	 * @var string
	 */
	public const LOGIN_WATERMARK_KEY = 'login_watermark';

	/**
	 * The app config key holding the top bar's style.
	 *
	 * @var string
	 */
	public const HEADER_STYLE_KEY = 'header_style';

	/**
	 * The narrowest navigation an administrator may ask for, in pixels.
	 *
	 * @var int
	 */
	public const NAVIGATION_WIDTH_MIN = 200;

	/**
	 * The widest navigation an administrator may ask for, in pixels.
	 *
	 * @var int
	 */
	public const NAVIGATION_WIDTH_MAX = 480;

	/**
	 * The selected entry as Nextcloud draws it: the primary wash and stripe.
	 *
	 * @var string
	 */
	public const ACTIVE_STYLE_DEFAULT = 'default';

	/**
	 * The selected entry as a soft tint of the accent with the accent's dark
	 * text in a bold label; the primary wash where a set names no accent.
	 *
	 * @var string
	 */
	public const ACTIVE_STYLE_SOFT = 'soft';

	/**
	 * The stripe on the top bar and along the top of the login card: how it
	 * has always been drawn.
	 *
	 * @var string
	 */
	public const PLACEMENT_HEADER_AND_LOGIN = 'header-and-login';

	/**
	 * The stripe on the top bar only.
	 *
	 * @var string
	 */
	public const PLACEMENT_HEADER = 'header';

	/**
	 * The stripe on the login card only.
	 *
	 * @var string
	 */
	public const PLACEMENT_LOGIN = 'login';

	/**
	 * The top bar as Nextcloud draws it: its logo, the current app's name,
	 * its own avatar.
	 *
	 * @var string
	 */
	public const HEADER_STYLE_DEFAULT = 'default';

	/**
	 * The workplace top bar (the DqKop board): no logo and no app name, the
	 * search beside the grid button, and the bell, a divider and the name
	 * and role of the signed-in person at the end. Only drawn with the light
	 * workplace layout.
	 *
	 * @var string
	 */
	public const HEADER_STYLE_WORKPLACE = 'workplace';

	/**
	 * The values each option may hold, by app config key, the two older
	 * options included. The navigation width is a whole number of pixels in
	 * its range instead, and is not listed.
	 *
	 * @var array<string, array<int, string>>
	 */
	public const VALUES = [
		LayoutOptionsService::WORKPLACE_LAYOUT_KEY => LayoutOptionsService::LAYOUTS,
		LayoutOptionsService::BRAND_STRIPE_KEY => ['1', '0'],
		self::NAVIGATION_ACTIVE_STYLE_KEY => [self::ACTIVE_STYLE_DEFAULT, self::ACTIVE_STYLE_SOFT],
		self::BRAND_STRIPE_PLACEMENT_KEY => [self::PLACEMENT_HEADER_AND_LOGIN, self::PLACEMENT_HEADER, self::PLACEMENT_LOGIN],
		self::LOGIN_WATERMARK_KEY => ['1', '0'],
		self::HEADER_STYLE_KEY => [self::HEADER_STYLE_DEFAULT, self::HEADER_STYLE_WORKPLACE],
	];

	/**
	 * What each of the newer options resolves to when neither the
	 * administrator nor the set says anything: the behaviour every set had
	 * before the option existed. The navigation width is Nextcloud's own.
	 *
	 * @var array<string, string>
	 */
	public const DEFAULTS = [
		self::NAVIGATION_WIDTH_KEY => '',
		self::NAVIGATION_ACTIVE_STYLE_KEY => self::ACTIVE_STYLE_DEFAULT,
		self::BRAND_STRIPE_PLACEMENT_KEY => self::PLACEMENT_HEADER_AND_LOGIN,
		self::LOGIN_WATERMARK_KEY => '1',
		self::HEADER_STYLE_KEY => self::HEADER_STYLE_DEFAULT,
	];

	/**
	 * The seven layout options as the configuration bundle names them under
	 * `config.layoutOptions`, with the app config key each is stored under.
	 *
	 * @var array<string, string>
	 */
	public const BUNDLE_KEYS = [
		'workplaceLayout' => LayoutOptionsService::WORKPLACE_LAYOUT_KEY,
		'brandStripe' => LayoutOptionsService::BRAND_STRIPE_KEY,
		'navigationWidth' => self::NAVIGATION_WIDTH_KEY,
		'navigationActiveStyle' => self::NAVIGATION_ACTIVE_STYLE_KEY,
		'brandStripePlacement' => self::BRAND_STRIPE_PLACEMENT_KEY,
		'loginWatermark' => self::LOGIN_WATERMARK_KEY,
		'headerStyle' => self::HEADER_STYLE_KEY,
	];

	/**
	 * A manifest value or a stored string as the option's string form, or
	 * null when it is not one the option accepts. A width may be declared as
	 * a number (`264`) or a string (`"264"`); a flag may be a boolean, the way
	 * a manifest says `"brand_stripe": true`; the empty string always means
	 * "follow the theme".
	 *
	 * @param string $key The app config key.
	 * @param mixed $value The declared or stored value.
	 *
	 * @return string|null The accepted string form, or null.
	 *
	 * @spec openspec/changes/layout-options-navigation-stripe-watermark/specs/workplace-layout/spec.md#requirement-the-navigation-width-is-an-admin-option
	 */
	public static function accepted(string $key, mixed $value): ?string {
		if ($value === LayoutOptionsService::FOLLOW_SET) {
			return LayoutOptionsService::FOLLOW_SET;
		}

		if ($key === self::NAVIGATION_WIDTH_KEY) {
			return self::acceptedWidth(value: $value);
		}

		if (is_bool($value) === true && in_array('1', (self::VALUES[$key] ?? []), true) === true) {
			return self::flag(value: $value);
		}

		if (is_string($value) === true && in_array($value, (self::VALUES[$key] ?? []), true) === true) {
			return $value;
		}

		return null;
	}//end accepted()

	/**
	 * A width as its string form, when it is a whole number of pixels in range.
	 *
	 * @param mixed $value The declared or stored value.
	 *
	 * @return string|null The width, or null.
	 */
	private static function acceptedWidth(mixed $value): ?string {
		if (is_int($value) === false && (is_string($value) === false || preg_match('/^[0-9]+$/', $value) !== 1)) {
			return null;
		}

		$width = (int)$value;
		if ($width < self::NAVIGATION_WIDTH_MIN || $width > self::NAVIGATION_WIDTH_MAX) {
			return null;
		}

		return (string)$width;
	}//end acceptedWidth()

	/**
	 * A boolean as the stored flag.
	 *
	 * @param bool $value The flag.
	 *
	 * @return string `1` or `0`.
	 */
	private static function flag(bool $value): string {
		if ($value === true) {
			return '1';
		}

		return '0';
	}//end flag()

	/**
	 * The `config.layoutOptions` section of a bundle as the values to store,
	 * by app config key. A missing section or key is "follow the theme"; a
	 * value an option refuses is reported and read as "follow" too, so the
	 * caller writes nothing.
	 *
	 * @param mixed $options The decoded section, or null.
	 * @param array<int, array<string, mixed>> $errors Accumulator, appended to on failure.
	 *
	 * @return array<string, string> App config key to the value to store.
	 *
	 * @spec openspec/changes/layout-options-navigation-stripe-watermark/specs/config-portability/spec.md#requirement-the-layout-options-travel-in-the-bundle
	 */
	public static function fromBundle(mixed $options, array &$errors): array {
		if (is_array($options) === false) {
			if ($options !== null) {
				$errors[] = ['section' => 'config', 'message' => '"config.layoutOptions" must be an object.'];
			}

			$options = [];
		}

		$resolved = [];
		foreach (self::BUNDLE_KEYS as $name => $key) {
			$value = ($options[$name] ?? '');
			if (is_string($value) === false || self::accepted(key: $key, value: $value) === null) {
				$errors[] = ['section' => 'config', 'message' => '"config.layoutOptions.' . $name . '" is not a value the option accepts.'];
				$value = '';
			}

			$resolved[$key] = $value;
		}

		return $resolved;
	}//end fromBundle()
}//end class
