<?php

/**
 * Contrast pairs for the settable selection and highlight colours.
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
 * @spec openspec/specs/token-set-contrast-audit/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * Measures the two pairs a set can now move: selected text against the
 * selection wash, and body text against the search highlight.
 *
 * Nextcloud draws the selection wash as the primary element colour at 20%
 * over the background, so the wash is derived from the set's primary rather
 * than read from a token. A pair is reported only when the set declares its
 * settable side: otherwise Nextcloud's own values apply, and those are
 * Nextcloud's responsibility.
 *
 * @spec openspec/specs/token-set-contrast-audit/spec.md
 */
class SettableContrastPairs {

	/**
	 * WCAG AA text-contrast threshold.
	 */
	public const AA_TEXT = 4.5;

	/**
	 * Constructor.
	 *
	 * @param ContrastService $contrast The WCAG contrast service.
	 *
	 * @spec openspec/specs/token-set-contrast-audit/spec.md
	 */
	public function __construct(
		private readonly ContrastService $contrast = new ContrastService(),
	) {
	}//end __construct()

	/**
	 * The settable pairs a set's declarations move.
	 *
	 * @param array<string, string> $declarations The set's resolved `--nldesign-*` declarations.
	 *
	 * @return array<int, array{pair: string, foreground: string, background: string, ratio: float|null, threshold: float, passes: bool}> The pairs.
	 *
	 * @spec openspec/specs/token-set-contrast-audit/spec.md
	 */
	public function pairs(array $declarations): array {
		$pairs = [];
		$page = ($declarations['--nldesign-color-background'] ?? '#ffffff');
		$text = ($declarations['--nldesign-color-text'] ?? '#222222');

		if (isset($declarations['--nldesign-nc-color-text-selection']) === true) {
			$pairs[] = $this->pair(
				name: 'selection',
				foreground: '--nldesign-nc-color-text-selection',
				background: 'selection wash (20% primary over the background)',
				ratio: $this->contrast->measure(
					foreground: $declarations['--nldesign-nc-color-text-selection'],
					background: $this->wash(primary: ($declarations['--nldesign-color-primary'] ?? '#154273')),
					page: $page
				)
			);
		}

		if (isset($declarations['--nldesign-nc-color-mark']) === true) {
			$pairs[] = $this->pair(
				name: 'highlight',
				foreground: '--nldesign-color-text',
				background: '--nldesign-nc-color-mark',
				ratio: $this->contrast->measure(foreground: $text, background: $declarations['--nldesign-nc-color-mark'], page: $page)
			);
		}

		return $pairs;
	}//end pairs()

	/**
	 * Nextcloud's selection wash for a primary colour: 20% of it, as a translucent colour.
	 *
	 * @param string $primary The primary colour.
	 *
	 * @return string An `rgba()` value the contrast service blends over the page.
	 */
	private function wash(string $primary): string {
		$rgb = $this->contrast->parseColor($primary);
		if ($rgb === null) {
			return $primary;
		}

		return 'rgba(' . $rgb[0] . ', ' . $rgb[1] . ', ' . $rgb[2] . ', 0.2)';
	}//end wash()

	/**
	 * One reported pair.
	 *
	 * @param string $name The pair name.
	 * @param string $foreground What the foreground is.
	 * @param string $background What the background is.
	 * @param float|null $ratio The measured ratio, or null when a value could not be resolved.
	 *
	 * @return array{pair: string, foreground: string, background: string, ratio: float|null, threshold: float, passes: bool} The pair.
	 */
	private function pair(string $name, string $foreground, string $background, ?float $ratio): array {
		if ($ratio !== null) {
			$ratio = round($ratio, 2);
		}

		return [
			'pair' => $name,
			'foreground' => $foreground,
			'background' => $background,
			'ratio' => $ratio,
			'threshold' => self::AA_TEXT,
			'passes' => ($ratio !== null && $ratio >= self::AA_TEXT),
		];
	}//end pair()
}//end class
