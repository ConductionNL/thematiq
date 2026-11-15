<?php

/**
 * Colour normalisation and contrast repair for the token set converter.
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
 * The PHP half of thematiq#993, mirroring `normaliseColours()` and `repairContrast()`
 * in js/lib/tokenConverter.js step for step, so the admin upload and the nightly sync
 * convert a theme to the same file.
 *
 * Colours a theme writes as hsl(), hsla() or an 8-digit hex become `#rrggbb` (or
 * `rgba()` when translucent), because ContrastService and the dark-variant generator
 * read hex and comma rgb() only. Then every pair in the mapping table's
 * `contrastRepair` list is brought to its WCAG threshold with the smallest lightness
 * change that keeps hue and saturation, or a white/black flip for text on a brand fill.
 *
 * @spec openspec/specs/token-sync-workflow/spec.md#requirement-converted-and-gated-sync
 *
 * @phpstan-import-type ReportEntry from TokenSetConverterService
 * @psalm-import-type   ReportEntry from TokenSetConverterService
 */
class ConverterColourRepair {

	/**
	 * How many times the pair list is walked before giving up on a fixed point.
	 */
	private const int MAX_PASSES = 4;

	/**
	 * The colour parser.
	 *
	 * @var ColourLiteralParser
	 */
	private readonly ColourLiteralParser $parser;

	/**
	 * Lightness repair and the white-or-black choice.
	 *
	 * @var ColourContrastAdjuster
	 */
	private readonly ColourContrastAdjuster $adjuster;

	/**
	 * Measures and repairs one pair.
	 *
	 * @var ConverterPairRepair
	 */
	private readonly ConverterPairRepair $pairs;

	/**
	 * Constructor.
	 *
	 * @param ContrastService $contrast WCAG luminance and ratio.
	 */
	public function __construct(
		private readonly ContrastService $contrast,
	) {
		$this->parser = new ColourLiteralParser();
		$this->adjuster = new ColourContrastAdjuster(contrast: $this->contrast, parser: $this->parser);
		$this->pairs = new ConverterPairRepair(contrast: $this->contrast, adjuster: $this->adjuster);
	}//end __construct()

	/**
	 * Normalise every declaration whose whole value is one colour literal written another way.
	 *
	 * @param array<string, string> $declarations Name => value.
	 * @param array<int, ReportEntry> $report The conversion report (appended to).
	 *
	 * @return array<string, string> The normalised declarations.
	 *
	 * @spec openspec/specs/token-sync-workflow/spec.md#requirement-converted-and-gated-sync
	 */
	public function normaliseColours(array $declarations, array &$report): array {
		$out = [];
		foreach ($declarations as $name => $value) {
			$out[$name] = $value;
			$normalised = $this->parser->normalise(value: trim($value));
			if ($this->plainRgb(value: $value) !== null || $normalised === null) {
				continue;
			}

			$out[$name] = $normalised;
			$report[] = (new AdaptedToken(name: $name, value: $normalised, original: $value))->colourNormalised();
		}

		return $out;
	}//end normaliseColours()

	/**
	 * Bring every pair of the table's `contrastRepair` list to its threshold.
	 *
	 * @param array<string, string> $semantic The semantic layer.
	 * @param array<string, mixed> $table The mapping table.
	 * @param array<string, string> $manifest Manifest values (updated for the primary).
	 * @param array<int, ReportEntry> $report The conversion report (appended to).
	 *
	 * @return array<string, string> The repaired semantic layer.
	 *
	 * @spec openspec/specs/token-sync-workflow/spec.md#requirement-converted-and-gated-sync
	 */
	public function repairContrast(array $semantic, array $table, array &$manifest, array &$report): array {
		$spec = ($table['contrastRepair'] ?? null);
		if (is_array($spec) === false || is_array($spec['pairs'] ?? null) === false) {
			return $semantic;
		}

		$run = new ContrastRepairRun(spec: $spec, page: ($this->plainRgb(value: (string)($spec['page'] ?? '#ffffff')) ?? [255, 255, 255]));
		$run->semantic = $semantic;
		$run->manifest = $manifest;
		$run->report = $report;
		$this->repairRun(run: $run);
		$manifest = $run->manifest;
		$report = $run->report;

		return $run->semantic;
	}//end repairContrast()

	/**
	 * Repair the component pairs the theme declares itself (thematiq#1006).
	 *
	 * @param array<string, string> $component The component layer.
	 * @param array<string, string> $semantic The repaired semantic layer.
	 * @param array<string, mixed> $table The mapping table.
	 * @param array<int, ReportEntry> $report The conversion report (appended to).
	 *
	 * @return array<string, string> The repaired component layer.
	 *
	 * @spec openspec/specs/token-sync-workflow/spec.md#requirement-converted-and-gated-sync
	 */
	public function repairComponents(array $component, array $semantic, array $table, array &$report): array {
		$spec = ($table['contrastRepair'] ?? null);
		if (is_array($spec) === false) {
			return $component;
		}

		$run = new ComponentRepairRun(semantic: $semantic, spec: $spec);
		$run->component = $component;
		$run->report = $report;
		(new ConverterComponentRepair(contrast: $this->contrast, adjuster: $this->adjuster, pairs: $this->pairs))->repair(run: $run);
		$report = $run->report;

		return $run->component;
	}//end repairComponents()

	/**
	 * The repair in order: invalid colours first, then the pair list until nothing changes, then the report.
	 *
	 * @param ContrastRepairRun $run The run.
	 *
	 * @return void
	 */
	private function repairRun(ContrastRepairRun $run): void {
		$oldPrimary = ($run->semantic['--nldesign-color-primary'] ?? null);
		$this->replaceInvalid(run: $run);

		for ($pass = 0; $pass < self::MAX_PASSES; $pass++) {
			if ($this->repairPass(run: $run) === false) {
				break;
			}
		}

		foreach ($run->originals as $name => $original) {
			$run->report[] = (new AdaptedToken(name: $name, value: $run->semantic[$name], original: $original))->contrastRepaired();
		}

		$this->followPrimary(run: $run, oldPrimary: $oldPrimary);
	}//end repairRun()

	/**
	 * Walk the pair list once.
	 *
	 * @param ContrastRepairRun $run The run; its semantic layer and first values are updated.
	 *
	 * @return bool Whether anything changed.
	 */
	private function repairPass(ContrastRepairRun $run): bool {
		$changed = false;
		foreach ($run->spec['pairs'] as $pair) {
			if ($this->repairPair(run: $run, pair: (array)$pair) === true) {
				$changed = true;
			}
		}

		return $changed;
	}//end repairPass()

	/**
	 * Repair one pair of the list.
	 *
	 * @param ContrastRepairRun $run The run; its semantic layer and first values are updated.
	 * @param array<string, mixed> $pair The table's pair.
	 *
	 * @return bool Whether the pair changed a token.
	 */
	private function repairPair(ContrastRepairRun $run, array $pair): bool {
		$result = $this->pairs->repair(pair: $pair, semantic: $run->semantic, context: $run->context());
		if ($result === null) {
			return false;
		}

		[$name, $repaired] = $result;
		if (isset($run->originals[$name]) === false) {
			$run->originals[$name] = ($run->semantic[$name] ?? $run->semantic[(string)($pair['from'] ?? '')] ?? '');
		}

		$run->semantic[$name] = $this->parser->toHex(rgb: $repaired);
		if (isset($run->semantic[$name . '-rgb']) === true) {
			$run->semantic[$name . '-rgb'] = $repaired[0] . ', ' . $repaired[1] . ', ' . $repaired[2];
		}

		return true;
	}//end repairPair()

	/**
	 * Give each listed colour token whose value is not a visible colour its default.
	 *
	 * @param ContrastRepairRun $run The run; its semantic layer and report are updated.
	 *
	 * @return void
	 */
	private function replaceInvalid(ContrastRepairRun $run): void {
		foreach ((array)($run->spec['replaceInvalid'] ?? []) as $name) {
			$this->replaceIfInvalid(run: $run, name: (string)$name);
		}
	}//end replaceInvalid()

	/**
	 * Give one colour token its default when its value is not a visible colour.
	 *
	 * @param ContrastRepairRun $run The run; its semantic layer and report are updated.
	 * @param string $name The token.
	 *
	 * @return void
	 */
	private function replaceIfInvalid(ContrastRepairRun $run, string $name): void {
		$value = ($run->semantic[$name] ?? null);
		$invisible = $this->isInvisible(value: $value);
		$replacement = $this->pairs->fallbackValue(name: $name, semantic: [], defaults: $run->defaults());
		if ($value === null || $invisible === false || $replacement === null) {
			return;
		}

		$run->semantic[$name] = $replacement;
		$run->report[] = (new AdaptedToken(name: $name, value: $replacement, original: $value))->notAColour();
	}//end replaceIfInvalid()

	/**
	 * Whether a declared value is no visible colour: not a colour at all, or a fully transparent one.
	 *
	 * @param string|null $value The value, or null when the token is not declared.
	 *
	 * @return bool True when it shows nothing.
	 */
	private function isInvisible(?string $value): bool {
		if ($value === null) {
			return false;
		}

		$parsed = $this->parser->parse(value: $value);

		return ($parsed !== null && $parsed[3] > 0) === false;
	}//end isInvisible()

	/**
	 * Keep the manifest's primary the same colour as the set's after a repair.
	 *
	 * @param ContrastRepairRun $run The run; its manifest values are updated.
	 * @param string|null $oldPrimary The primary before the repair.
	 *
	 * @return void
	 */
	private function followPrimary(ContrastRepairRun $run, ?string $oldPrimary): void {
		$primary = ($run->semantic['--nldesign-color-primary'] ?? null);
		$declared = ($run->manifest['theming.primary_color'] ?? null);
		if ($primary === null || $primary === $oldPrimary) {
			return;
		}

		if ($this->stillTheOldPrimary(declared: $declared, oldPrimary: $oldPrimary) === true) {
			$run->manifest['theming.primary_color'] = $primary;
		}
	}//end followPrimary()

	/**
	 * Whether the manifest's primary is unset or still the set's primary from before the repair.
	 *
	 * @param string|null $declared The manifest's primary.
	 * @param string|null $oldPrimary The set's primary before the repair.
	 *
	 * @return bool True when the manifest should follow the repaired primary.
	 */
	private function stillTheOldPrimary(?string $declared, ?string $oldPrimary): bool {
		return $declared === null || strtolower($declared) === strtolower((string)$oldPrimary);
	}//end stillTheOldPrimary()

	/**
	 * Parse #rgb, #rrggbb or comma rgb()/rgba() the way the JavaScript `parseColor()` does.
	 *
	 * @param string $value The value.
	 *
	 * @return array{0: int, 1: int, 2: int}|null The channels, or null.
	 */
	private function plainRgb(string $value): ?array {
		$value = trim($value);
		if (preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value) === 1
			|| preg_match('/^rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*(?:,\s*[\d.]+\s*)?\)$/', $value) === 1
		) {
			return $this->contrast->parseColor(value: $value);
		}

		return null;
	}//end plainRgb()
}//end class
