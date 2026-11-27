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
 * @spec openspec/specs/workplace-layout/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * The conditional stylesheets the resolved layout options switch on, in the
 * order a page loads them. Built by LayoutOptionsService.
 *
 * @spec openspec/specs/workplace-layout/spec.md
 */
final class LayoutStylesheets {

	/**
	 * The apps with content of their own that a board draws, under `css/workplace-apps/`: what every
	 * app shares (the navigation rows, list rows, panels and the rail) is in `css/workplace-layout.css`,
	 * and the top bar's brand fallback in `css/header-workplace.css`, so only these three pay a sheet more.
	 *
	 * @var string[]
	 */
	public const array APP_LAYERS = ['mail', 'calendar', 'deck'];

	/**
	 * The stylesheet the light layout adds for one app, only on that app's own pages: the app's own
	 * sheet under `css/workplace-apps/`, with what exists in that app alone.
	 *
	 * @param array<string, mixed> $resolved The resolved options.
	 * @param string|null $appId The rendered app.
	 *
	 * @return array<int, string> No stylesheet, or the app's own.
	 *
	 * @spec openspec/specs/workplace-layout/spec.md#requirement-the-light-layout-draws-mail-calendar-and-deck-as-the-boards
	 * @spec openspec/changes/same-shell-every-page/specs/workplace-layout/spec.md#requirement-what-stays-app-specific-is-listed-req-tqss-006
	 */
	public function forApp(array $resolved, ?string $appId): array {
		if ($resolved['workplaceLayout'] !== LayoutOptionsService::LAYOUT_LIGHT || in_array($appId, self::APP_LAYERS, true) === false) {
			return [];
		}

		return ['workplace-apps/' . $appId];
	}//end forApp()

	/**
	 * The stylesheets of the workplace layout, the brand stripe and the navigation, in that order.
	 *
	 * @param array<string, mixed> $resolved The resolved options.
	 *
	 * @return array<int, string> The stylesheets.
	 *
	 * @spec openspec/specs/workplace-layout/spec.md
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

		$files = [LayoutOptionsService::LIGHT_LAYOUT_STYLESHEET];
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
