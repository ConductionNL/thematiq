<?php

/**
 * Thematiq Layout Stylesheets.
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
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * The conditional stylesheets the resolved layout options switch on, in the
 * order a page loads them. Built by LayoutOptionsService.
 *
 * @spec openspec/changes/zuiddrecht-workplace-theme/specs/workplace-layout/spec.md
 */
final class LayoutStylesheets {

	/**
	 * The apps the light layout draws with a stylesheet of their own, under `css/workplace-apps/`.
	 *
	 * @var string[]
	 */
	public const array APP_LAYERS = ['mail', 'calendar', 'deck'];

	/**
	 * The stylesheets the light layout adds for one app, only on that app's own pages: with the
	 * workplace header first `workplace-apps/brand`, the organisation and the app name at the start
	 * of the bar, then `workplace-apps/common`, what the three apps share, then the app's own sheet.
	 *
	 * @param array<string, mixed> $resolved The resolved options.
	 * @param string|null $appId The rendered app.
	 *
	 * @return array<int, string> No stylesheet, or two or three.
	 *
	 * @spec openspec/specs/workplace-layout/spec.md#requirement-the-light-layout-draws-mail-calendar-and-deck-as-the-boards
	 */
	public function forApp(array $resolved, ?string $appId): array {
		if ($resolved['workplaceLayout'] !== LayoutOptionsService::LAYOUT_LIGHT || in_array($appId, self::APP_LAYERS, true) === false) {
			return [];
		}

		$files = [];
		if ($resolved['headerStyle'] === LayoutOptionValues::HEADER_STYLE_WORKPLACE) {
			$files[] = 'workplace-apps/brand';
		}

		$files[] = 'workplace-apps/common';
		$files[] = 'workplace-apps/' . $appId;
		return $files;
	}//end forApp()

	/**
	 * The stylesheets of the workplace layout, the brand stripe and the navigation, in that order.
	 *
	 * @param array<string, mixed> $resolved The resolved options.
	 *
	 * @return array<int, string> The stylesheets.
	 *
	 * @spec openspec/changes/zuiddrecht-workplace-theme/specs/workplace-layout/spec.md
	 */
	public function forOptions(array $resolved): array {
		return array_merge(
			$this->workplaceStylesheets(resolved: $resolved),
			$this->brandStripeStylesheets(resolved: $resolved),
			$this->navigationStylesheets(resolved: $resolved)
		);
	}//end forOptions()

	/**
	 * The stylesheets of the light workplace layout: the layout, the watermark switched off, the workplace header.
	 *
	 * @param array<string, mixed> $resolved The resolved options.
	 *
	 * @return array<int, string> The stylesheets.
	 */
	private function workplaceStylesheets(array $resolved): array {
		if ($resolved['workplaceLayout'] !== LayoutOptionsService::LAYOUT_LIGHT) {
			return [];
		}

		$files = ['workplace-layout'];
		if ($resolved['loginWatermark'] === false) {
			$files[] = 'login-watermark-off';
		}

		if ($resolved['headerStyle'] === LayoutOptionValues::HEADER_STYLE_WORKPLACE) {
			$files[] = LayoutOptionsService::HEADER_WORKPLACE_STYLESHEET;
		}

		return $files;
	}//end workplaceStylesheets()

	/**
	 * The stylesheets of the brand stripe, and of where it is placed when not everywhere.
	 *
	 * @param array<string, mixed> $resolved The resolved options.
	 *
	 * @return array<int, string> The stylesheets.
	 */
	private function brandStripeStylesheets(array $resolved): array {
		if ($resolved['brandStripe'] !== true) {
			return [];
		}

		$files = ['brand-stripe'];
		if ($resolved['brandStripePlacement'] === LayoutOptionValues::PLACEMENT_HEADER) {
			$files[] = 'brand-stripe-header-only';
		} elseif ($resolved['brandStripePlacement'] === LayoutOptionValues::PLACEMENT_LOGIN) {
			$files[] = 'brand-stripe-login-only';
		}

		return $files;
	}//end brandStripeStylesheets()

	/**
	 * The stylesheets of the navigation width and the soft active style.
	 *
	 * @param array<string, mixed> $resolved The resolved options.
	 *
	 * @return array<int, string> The stylesheets.
	 */
	private function navigationStylesheets(array $resolved): array {
		$files = [];
		if ($resolved['navigationWidth'] !== null) {
			$files[] = 'navigation-width';
		}

		if ($resolved['navigationActiveStyle'] === LayoutOptionValues::ACTIVE_STYLE_SOFT) {
			$files[] = 'navigation-active-soft';
		}

		return $files;
	}//end navigationStylesheets()
}//end class
