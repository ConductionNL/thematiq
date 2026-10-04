<?php

/**
 * Contrast repair for the component pairs a converted theme declares itself.
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
 * @spec openspec/specs/token-sync-workflow/spec.md#requirement-converted-and-gated-sync
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * Mirrors `repairComponents()` in js/lib/tokenConverter.js (thematiq#1006, #1007).
 *
 * The mapping table's `contrastRepair.componentPairs` are the Den Haag and Tilburg pairs
 * DenhaagContrastPairs measures. A pair is repaired only when the theme declares its
 * foreground in its own component layer; a `transparent` background, which cannot be
 * measured, becomes the page colour it shows anyway.
 *
 * @spec openspec/specs/token-sync-workflow/spec.md#requirement-converted-and-gated-sync
 *
 * @phpstan-import-type ReportEntry from TokenSetConverterService
 * @psalm-import-type   ReportEntry from TokenSetConverterService
 */
class ConverterComponentRepair {

	/**
	 * Constructor.
	 *
	 * @param ContrastService $contrast WCAG luminance and ratio.
	 * @param ColourContrastAdjuster $adjuster Lightness repair and opaque compositing.
	 */
	public function __construct(
		private readonly ContrastService $contrast,
		private readonly ColourContrastAdjuster $adjuster,
	) {
	}//end __construct()

	/**
	 * Repair every listed pair whose foreground the component layer declares.
	 *
	 * @param array<string, string> $component The component layer.
	 * @param array<string, string> $semantic The (repaired) semantic layer.
	 * @param array<string, mixed> $spec The table's contrastRepair section.
	 * @param array<int, ReportEntry> $report The conversion report (appended to).
	 *
	 * @return array<string, string> The repaired component layer.
	 *
	 * @spec openspec/specs/token-sync-workflow/spec.md#requirement-converted-and-gated-sync
	 */
	public function repair(array $component, array $semantic, array $spec, array &$report): array {
		$pageHex = (string)($spec['page'] ?? '#ffffff');
		$page = ($this->contrast->parseColor(value: $pageHex) ?? [255, 255, 255]);
		$defaults = (array)($spec['defaults'] ?? []);

		foreach ((array)($spec['componentPairs'] ?? []) as $pair) {
			$name = (string)($pair['foreground'] ?? '');
			$backgroundName = (string)($pair['background'] ?? '');
			if (isset($component[$name]) === false) {
				continue;
			}

			if (strtolower(trim($component[$backgroundName] ?? '')) === 'transparent') {
				$component[$backgroundName] = $pageHex;
				$report[] = [
					'source' => $backgroundName,
					'target' => $backgroundName,
					'action' => 'adapted',
					'reason' => 'not-a-colour',
					'value' => $pageHex,
					'original' => 'transparent',
				];
			}

			$backgroundValue = ($component[$backgroundName] ?? $semantic[$backgroundName] ?? null);
			$backgroundValue = ($backgroundValue ?? $this->fallback(name: $backgroundName, semantic: $semantic, defaults: $defaults));
			$repaired = $this->repairPair(pair: (array)$pair, value: $component[$name], backgroundValue: $backgroundValue, page: $page);
			if ($repaired === null) {
				continue;
			}

			$report[] = [
				'source' => $name,
				'target' => $name,
				'action' => 'adapted',
				'reason' => 'contrast-repaired',
				'value' => $repaired,
				'original' => $component[$name],
			];
			$component[$name] = $repaired;
		}//end foreach

		return $component;
	}//end repair()

	/**
	 * The repaired foreground of one pair as hex, or null when it passes or cannot be measured.
	 *
	 * @param array<string, mixed> $pair The pair spec.
	 * @param string $value The foreground value.
	 * @param string|null $backgroundValue The background value.
	 * @param array{0: int, 1: int, 2: int} $page The page colour.
	 *
	 * @return string|null The new foreground.
	 */
	private function repairPair(array $pair, string $value, ?string $backgroundValue, array $page): ?string {
		$foreground = $this->adjuster->opaque(value: $value, page: $page);
		$background = null;
		if ($backgroundValue !== null) {
			$background = $this->adjuster->opaque(value: $backgroundValue, page: $page);
		}

		$min = (float)($pair['min'] ?? 4.5);
		if ($foreground === null || $background === null || $this->contrast->ratio(first: $foreground, second: $background) >= $min) {
			return null;
		}

		$rgb = $this->adjuster->repairLightness(foreground: $foreground, background: $background, min: $min);
		if (($pair['adjust'] ?? '') === 'flip') {
			$rgb = $this->adjuster->blackOrWhite(background: $background, preferWhite: true);
		}

		return sprintf('#%02x%02x%02x', $rgb[0], $rgb[1], $rgb[2]);
	}//end repairPair()

	/**
	 * A background the set does not declare: the table default, a literal or a chain of tokens.
	 *
	 * @param string $name The token.
	 * @param array<string, string> $semantic The semantic layer.
	 * @param array<string, mixed> $defaults The table's defaults.
	 *
	 * @return string|null The value.
	 */
	private function fallback(string $name, array $semantic, array $defaults): ?string {
		foreach ((array)($defaults[$name] ?? []) as $step) {
			$step = (string)$step;
			if (str_starts_with($step, '--') === false) {
				return $step;
			}

			if (isset($semantic[$step]) === true) {
				return $semantic[$step];
			}
		}

		return null;
	}//end fallback()
}//end class
