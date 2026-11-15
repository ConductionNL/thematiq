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
	 * @param ConverterPairRepair $pairs Resolves a background's default chain.
	 */
	public function __construct(
		private readonly ContrastService $contrast,
		private readonly ColourContrastAdjuster $adjuster,
		private readonly ConverterPairRepair $pairs,
	) {
	}//end __construct()

	/**
	 * Repair every listed pair whose foreground the component layer declares.
	 *
	 * @param ComponentRepairRun $run The run; its component layer and report are updated.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/token-sync-workflow/spec.md#requirement-converted-and-gated-sync
	 */
	public function repair(ComponentRepairRun $run): void {
		$run->page = ($this->contrast->parseColor(value: $run->pageHex()) ?? [255, 255, 255]);
		foreach ($run->pairs() as $pair) {
			$this->repairDeclaredPair(run: $run, pair: $pair);
		}
	}//end repair()

	/**
	 * Repair one pair when the component layer declares its foreground.
	 *
	 * @param ComponentRepairRun $run The run; its component layer and report are updated.
	 * @param mixed $pair The table's pair.
	 *
	 * @return void
	 */
	private function repairDeclaredPair(ComponentRepairRun $run, mixed $pair): void {
		$name = (string)($pair['foreground'] ?? '');
		$backgroundName = (string)($pair['background'] ?? '');
		if (isset($run->component[$name]) === false) {
			return;
		}

		$this->fillTransparent(run: $run, backgroundName: $backgroundName);
		$repaired = $this->repairPair(run: $run, pair: (array)$pair);
		if ($repaired === null) {
			return;
		}

		$run->report[] = (new AdaptedToken(name: $name, value: $repaired, original: $run->component[$name]))->contrastRepaired();
		$run->component[$name] = $repaired;
	}//end repairDeclaredPair()

	/**
	 * A `transparent` background cannot be measured; it becomes the page colour it shows anyway.
	 *
	 * @param ComponentRepairRun $run The run; its component layer and report are updated.
	 * @param string $backgroundName The pair's background token.
	 *
	 * @return void
	 */
	private function fillTransparent(ComponentRepairRun $run, string $backgroundName): void {
		if (strtolower(trim($run->component[$backgroundName] ?? '')) !== 'transparent') {
			return;
		}

		$run->component[$backgroundName] = $run->pageHex();
		$run->report[] = (new AdaptedToken(name: $backgroundName, value: $run->pageHex(), original: 'transparent'))->notAColour();
	}//end fillTransparent()

	/**
	 * The repaired foreground of one pair as hex, or null when it passes or cannot be measured.
	 *
	 * @param ComponentRepairRun $run The run.
	 * @param array<string, mixed> $pair The table's pair; the component layer declares its foreground.
	 *
	 * @return string|null The hex.
	 */
	private function repairPair(ComponentRepairRun $run, array $pair): ?string {
		$colours = $this->opaqueColours(run: $run, pair: $pair);
		$min = (float)($pair['min'] ?? 4.5);
		if ($colours === null || $this->contrast->ratio(first: $colours[0], second: $colours[1]) >= $min) {
			return null;
		}

		$rgb = $this->adjuster->repairLightness(foreground: $colours[0], background: $colours[1], min: $min);
		if (($pair['adjust'] ?? '') === 'flip') {
			$rgb = $this->adjuster->blackOrWhite(background: $colours[1], preferWhite: true);
		}

		return sprintf('#%02x%02x%02x', $rgb[0], $rgb[1], $rgb[2]);
	}//end repairPair()

	/**
	 * A pair's foreground and background as opaque colours on the page.
	 *
	 * @param ComponentRepairRun $run The run.
	 * @param array<string, mixed> $pair The table's pair; the component layer declares its foreground.
	 *
	 * @return array{0: array{0: int, 1: int, 2: int}, 1: array{0: int, 1: int, 2: int}}|null Foreground and background; null when unmeasurable.
	 */
	private function opaqueColours(ComponentRepairRun $run, array $pair): ?array {
		$backgroundName = (string)($pair['background'] ?? '');
		$backgroundValue = ($run->component[$backgroundName] ?? $run->semantic[$backgroundName] ?? null);
		$backgroundValue = ($backgroundValue ?? $this->pairs->fallbackValue(name: $backgroundName, semantic: $run->semantic, defaults: $run->defaults()));
		$foreground = $this->adjuster->opaque(value: $run->component[(string)($pair['foreground'] ?? '')], page: $run->page);
		$background = null;
		if ($backgroundValue !== null) {
			$background = $this->adjuster->opaque(value: $backgroundValue, page: $run->page);
		}

		if ($foreground === null || $background === null) {
			return null;
		}

		return [$foreground, $background];
	}//end opaqueColours()
}//end class
