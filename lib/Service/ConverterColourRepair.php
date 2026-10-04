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
	private const MAX_PASSES = 4;

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
	 * Constructor.
	 *
	 * @param ContrastService $contrast WCAG luminance and ratio.
	 */
	public function __construct(
		private readonly ContrastService $contrast,
	) {
		$this->parser = new ColourLiteralParser();
		$this->adjuster = new ColourContrastAdjuster(contrast: $this->contrast, parser: $this->parser);
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
			$report[] = [
				'source' => $name,
				'target' => '',
				'action' => 'kept',
				'reason' => 'colour-normalised',
				'value' => $normalised,
				'original' => $value,
			];
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

		$context = [
			'page' => ($this->plainRgb(value: (string)($spec['page'] ?? '#ffffff')) ?? [255, 255, 255]),
			'defaults' => (array)($spec['defaults'] ?? []),
		];
		$originals = [];
		$oldPrimary = ($semantic['--nldesign-color-primary'] ?? null);
		$semantic = $this->replaceInvalid(semantic: $semantic, spec: $spec, report: $report);

		for ($pass = 0; $pass < self::MAX_PASSES; $pass++) {
			if ($this->repairPass(semantic: $semantic, pairs: $spec['pairs'], context: $context, originals: $originals) === false) {
				break;
			}
		}

		foreach ($originals as $name => $original) {
			$report[] = [
				'source' => $name,
				'target' => $name,
				'action' => 'adapted',
				'reason' => 'contrast-repaired',
				'value' => $semantic[$name],
				'original' => $original,
			];
		}

		$this->followPrimary(semantic: $semantic, manifest: $manifest, oldPrimary: $oldPrimary);

		return $semantic;
	}//end repairContrast()

	/**
	 * Walk the pair list once.
	 *
	 * @param array<string, string> $semantic The semantic layer (updated).
	 * @param array<int, mixed> $pairs The table's pairs.
	 * @param array{page: array{0: int, 1: int, 2: int}, defaults: array<string, mixed>} $context Page colour and defaults.
	 * @param array<string, string> $originals The first value of every repaired token (updated).
	 *
	 * @return bool Whether anything changed.
	 */
	private function repairPass(array &$semantic, array $pairs, array $context, array &$originals): bool {
		$changed = false;
		foreach ($pairs as $pair) {
			$result = $this->repairPair(pair: (array)$pair, semantic: $semantic, context: $context);
			if ($result === null) {
				continue;
			}

			[$name, $repaired] = $result;

			if (isset($originals[$name]) === false) {
				$originals[$name] = $semantic[$name];
			}

			$semantic[$name] = $this->parser->toHex(rgb: $repaired);
			if (isset($semantic[$name . '-rgb']) === true) {
				$semantic[$name . '-rgb'] = $repaired[0] . ', ' . $repaired[1] . ', ' . $repaired[2];
			}

			$changed = true;
		}

		return $changed;
	}//end repairPass()

	/**
	 * Repair one pair: the token it changes and that token's new colour, or null when it passes.
	 *
	 * @param array<string, mixed> $pair The pair spec.
	 * @param array<string, string> $semantic The semantic layer.
	 * @param array{page: array{0: int, 1: int, 2: int}, defaults: array<string, mixed>} $context Page colour and defaults.
	 *
	 * @return array{0: string, 1: array{0: int, 1: int, 2: int}}|null The token and its new colour.
	 */
	private function repairPair(array $pair, array $semantic, array $context): ?array {
		$foregroundName = (string)($pair['foreground'] ?? '');
		$backgroundName = (string)($pair['background'] ?? '');
		$value = ($semantic[$foregroundName] ?? null);
		$backgroundValue = ($semantic[$backgroundName] ?? $this->fallbackValue(name: $backgroundName, semantic: $semantic, defaults: $context['defaults']));
		if ($value === null || $backgroundValue === null) {
			return null;
		}

		$foreground = $this->adjuster->opaque(value: $value, page: $context['page']);
		$background = $this->adjuster->opaque(value: $backgroundValue, page: $context['page']);
		$min = (float)($pair['min'] ?? 4.5);
		if ($foreground === null || $background === null || $this->contrast->ratio(first: $foreground, second: $background) >= $min) {
			return null;
		}

		// The fill gives way, not the text: only a background the set declares.
		if (($pair['repair'] ?? '') === 'background') {
			if (isset($semantic[$backgroundName]) === false) {
				return null;
			}

			return [$backgroundName, $this->adjuster->repairLightness(foreground: $background, background: $foreground, min: $min)];
		}

		if (($pair['adjust'] ?? '') === 'flip') {
			return [$foregroundName, $this->adjuster->blackOrWhite(background: $background, preferWhite: true)];
		}

		return [$foregroundName, $this->adjuster->repairLightness(foreground: $foreground, background: $background, min: $min)];
	}//end repairPair()

	/**
	 * The value a token falls back to: a literal or a chain of tokens ending in a literal.
	 *
	 * @param string $name The token.
	 * @param array<string, string> $semantic The semantic layer.
	 * @param array<string, mixed> $defaults The table's defaults.
	 *
	 * @return string|null The value, or null when there is none.
	 */
	private function fallbackValue(string $name, array $semantic, array $defaults): ?string {
		if (isset($defaults[$name]) === false) {
			return null;
		}

		foreach ((array)$defaults[$name] as $step) {
			$step = (string)$step;
			if (str_starts_with($step, '--') === false) {
				return $step;
			}

			if (isset($semantic[$step]) === true) {
				return $semantic[$step];
			}
		}

		return null;
	}//end fallbackValue()

	/**
	 * Give a listed colour token whose value is not a visible colour its default.
	 *
	 * @param array<string, string> $semantic The semantic layer.
	 * @param array<string, mixed> $spec The table's contrastRepair section.
	 * @param array<int, ReportEntry> $report The conversion report (appended to).
	 *
	 * @return array<string, string> The semantic layer.
	 */
	private function replaceInvalid(array $semantic, array $spec, array &$report): array {
		foreach ((array)($spec['replaceInvalid'] ?? []) as $name) {
			$name = (string)$name;
			$value = ($semantic[$name] ?? null);
			$parsed = null;
			if ($value !== null) {
				$parsed = $this->parser->parse(value: $value);
			}
			$replacement = $this->fallbackValue(name: $name, semantic: [], defaults: (array)($spec['defaults'] ?? []));
			if ($value === null || ($parsed !== null && $parsed[3] > 0) || $replacement === null) {
				continue;
			}

			$semantic[$name] = $replacement;
			$report[] = [
				'source' => $name,
				'target' => $name,
				'action' => 'adapted',
				'reason' => 'not-a-colour',
				'value' => $replacement,
				'original' => $value,
			];
		}

		return $semantic;
	}//end replaceInvalid()

	/**
	 * Keep the manifest's primary the same colour as the set's after a repair.
	 *
	 * @param array<string, string> $semantic The semantic layer.
	 * @param array<string, string> $manifest Manifest values (updated).
	 * @param string|null $oldPrimary The primary before the repair.
	 *
	 * @return void
	 */
	private function followPrimary(array $semantic, array &$manifest, ?string $oldPrimary): void {
		$primary = ($semantic['--nldesign-color-primary'] ?? null);
		$declared = ($manifest['theming.primary_color'] ?? null);
		if ($primary === null || $primary === $oldPrimary) {
			return;
		}

		if ($declared === null || strtolower($declared) === strtolower((string)$oldPrimary)) {
			$manifest['theming.primary_color'] = $primary;
		}
	}//end followPrimary()

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
