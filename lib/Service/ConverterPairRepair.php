<?php

/**
 * Contrast repair of one semantic pair for the token set converter.
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
 * Measures one pair of the mapping table's `contrastRepair` list against the
 * semantic layer and, when it fails its threshold, names the token to change
 * and its new colour. {@see ConverterColourRepair} walks the list.
 *
 * @spec openspec/specs/token-sync-workflow/spec.md#requirement-converted-and-gated-sync
 */
class ConverterPairRepair {

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
	 * Repair one pair: the token it changes and that token's new colour, or null when it passes.
	 *
	 * @param array<string, mixed> $pair The pair spec.
	 * @param array<string, string> $semantic The semantic layer.
	 * @param array{page: array{0: int, 1: int, 2: int}, defaults: array<string, mixed>} $context Page colour and defaults.
	 *
	 * @return array{0: string, 1: array{0: int, 1: int, 2: int}}|null The token and its new colour.
	 *
	 * @spec openspec/specs/token-sync-workflow/spec.md#requirement-converted-and-gated-sync
	 */
	public function repair(array $pair, array $semantic, array $context): ?array {
		$values = $this->pairValues(pair: $pair, layers: ['semantic' => $semantic, 'defaults' => $context['defaults']]);
		$colours = $this->opaqueColours(values: $values, page: $context['page']);
		if ($colours === null || $this->contrast->ratio(first: $colours[0], second: $colours[1]) >= (float)($pair['min'] ?? 4.5)) {
			return null;
		}

		if ($this->repairable(pair: $pair, semantic: $semantic) === false) {
			return null;
		}

		return $this->repairedToken(pair: $pair, colours: $colours);
	}//end repair()

	/**
	 * The value a token falls back to: a literal or a chain of tokens ending in a literal.
	 *
	 * @param string $name The token.
	 * @param array<string, string> $semantic The semantic layer.
	 * @param array<string, mixed> $defaults The table's defaults.
	 *
	 * @return string|null The value, or null when there is none.
	 *
	 * @spec openspec/specs/token-sync-workflow/spec.md#requirement-converted-and-gated-sync
	 */
	public function fallbackValue(string $name, array $semantic, array $defaults): ?string {
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
	}//end fallbackValue()

	/**
	 * The foreground and background values a pair measures; the background
	 * falls back to the table's default chain when the set does not declare it.
	 *
	 * @param array<string, mixed> $pair The pair spec.
	 * @param array{semantic: array<string, string>, defaults: array<string, mixed>} $layers The semantic layer and the table's defaults.
	 *
	 * @return list<string> The foreground and background values, or nothing when either is missing.
	 */
	private function pairValues(array $pair, array $layers): array {
		$semantic = $layers['semantic'];
		$background = (string)($pair['background'] ?? '');
		$foregroundValue = $this->foregroundValue(pair: $pair, semantic: $semantic);
		$backgroundValue = ($semantic[$background] ?? $this->fallbackValue(name: $background, semantic: $semantic, defaults: $layers['defaults']));
		if ($foregroundValue === null || $backgroundValue === null) {
			return [];
		}

		return [$foregroundValue, $backgroundValue];
	}//end pairValues()

	/**
	 * Both values composited onto the page, or null when either is missing or no colour.
	 *
	 * @param list<string> $values The foreground and background values, or nothing.
	 * @param array{0: int, 1: int, 2: int} $page The page colour.
	 *
	 * @return array{0: array{0: int, 1: int, 2: int}, 1: array{0: int, 1: int, 2: int}}|null The opaque colours.
	 */
	private function opaqueColours(array $values, array $page): ?array {
		if ($values === []) {
			return null;
		}

		$foreground = $this->adjuster->opaque(value: $values[0], page: $page);
		$background = $this->adjuster->opaque(value: $values[1], page: $page);
		if ($foreground === null || $background === null) {
			return null;
		}

		return [$foreground, $background];
	}//end opaqueColours()

	/**
	 * Whether a failing pair can be repaired: the fill gives way, not the
	 * text, only when the set declares that background.
	 *
	 * @param array<string, mixed> $pair The pair spec.
	 * @param array<string, string> $semantic The semantic layer.
	 *
	 * @return bool True when the pair can be repaired.
	 */
	private function repairable(array $pair, array $semantic): bool {
		return ($pair['repair'] ?? '') !== 'background' || isset($semantic[(string)($pair['background'] ?? '')]) === true;
	}//end repairable()

	/**
	 * The value a pair measures as its foreground: the declared step, or the
	 * token it falls back to (`from`), which is written only when it needs a repair.
	 *
	 * @param array<string, mixed> $pair The pair spec.
	 * @param array<string, string> $semantic The semantic layer.
	 *
	 * @return string|null The value, or null when neither is declared.
	 */
	private function foregroundValue(array $pair, array $semantic): ?string {
		$value = ($semantic[(string)($pair['foreground'] ?? '')] ?? null);
		if ($value === null && isset($pair['from']) === true) {
			$value = ($semantic[(string)$pair['from']] ?? null);
		}

		return $value;
	}//end foregroundValue()

	/**
	 * The token a failing pair changes and its new colour: the background when
	 * the pair repairs its fill, else the foreground, flipped to white or black
	 * or moved in lightness.
	 *
	 * @param array<string, mixed> $pair The pair spec.
	 * @param array{0: array{0: int, 1: int, 2: int}, 1: array{0: int, 1: int, 2: int}} $colours The opaque foreground and background.
	 *
	 * @return array{0: string, 1: array{0: int, 1: int, 2: int}} The token and its new colour.
	 */
	private function repairedToken(array $pair, array $colours): array {
		[$foreground, $background] = $colours;
		$min = (float)($pair['min'] ?? 4.5);
		if (($pair['repair'] ?? '') === 'background') {
			return [(string)($pair['background'] ?? ''), $this->adjuster->repairLightness(foreground: $background, background: $foreground, min: $min)];
		}

		$foregroundName = (string)($pair['foreground'] ?? '');
		if (($pair['adjust'] ?? '') === 'flip') {
			return [$foregroundName, $this->adjuster->blackOrWhite(background: $background, preferWhite: true)];
		}

		return [$foregroundName, $this->adjuster->repairLightness(foreground: $foreground, background: $background, min: $min)];
	}//end repairedToken()
}//end class
