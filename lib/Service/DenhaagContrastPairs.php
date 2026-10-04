<?php

/**
 * Contrast pairs for the text the Den Haag components draw.
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
 * @spec openspec/changes/denhaag-component-tokens/specs/token-set-contrast-audit/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * Measures the text pairs of the Den Haag mijn-omgeving components for a set.
 *
 * A portal links `css/public-bridge.css` before the token set, so the
 * declarations this reads are the defaults, then the bridge, then the set,
 * in that order. Most values are `var()` chains through the bridge, so they
 * are resolved here before they are measured. A pair whose colours do not
 * resolve to literals is `unevaluated`, which never passes.
 *
 * @spec openspec/changes/denhaag-component-tokens/specs/token-set-contrast-audit/spec.md
 */
class DenhaagContrastPairs {

	/**
	 * WCAG AA text-contrast threshold.
	 */
	public const AA_TEXT = 4.5;

	/**
	 * The page background a component sits on, when a pair names none.
	 */
	public const PAGE = '--nldesign-color-background';

	/**
	 * The pairs: name => [foreground property, background property].
	 *
	 * Design D3 of the change, in its order.
	 */
	public const PAIRS = [
		'step-current' => ['--denhaag-step-marker-current-color', '--denhaag-step-marker-current-background-color'],
		'step-checked' => ['--denhaag-step-marker-checked-color', '--denhaag-step-marker-checked-background-color'],
		'step-not-checked' => ['--denhaag-step-marker-not-checked-color', self::PAGE],
		'case-title' => ['--denhaag-case-card-title-color', '--denhaag-case-card-background-color'],
		'case-subtitle' => ['--denhaag-case-card-subtitle-color', '--denhaag-case-card-background-color'],
		'action-date-warning' => ['--denhaag-action-date-warning-color', '--denhaag-action-background-color'],
		'badge-neutral' => ['--nl-data-badge-neutral-color', '--nl-data-badge-neutral-background-color'],
		'badge-success' => ['--nl-data-badge-success-color', '--nl-data-badge-success-background-color'],
		'badge-warning' => ['--nl-data-badge-warning-color', '--nl-data-badge-warning-background-color'],
		'badge-error' => ['--nl-data-badge-error-color', '--nl-data-badge-error-background-color'],
		'nav-link' => ['--denhaag-side-navigation-link-color', self::PAGE],
		'nav-link-active' => ['--denhaag-side-navigation-link-active-color', self::PAGE],
		'file-link' => ['--denhaag-file-link-color', self::PAGE],
		'header-title' => ['--tilburg-header-logo-text-color', '--tilburg-header-background-color'],
		'footer-text' => ['--tilburg-footer-color', '--tilburg-footer-background-color'],
	];

	/**
	 * How deep a var() chain may go before it counts as unresolvable.
	 */
	private const MAX_DEPTH = 40;

	/**
	 * Constructor.
	 *
	 * @param ContrastService $contrast The WCAG contrast service.
	 * @param ColourFunctionEvaluator $functions Turns hsl() and color-mix() into hex.
	 *
	 * @spec openspec/changes/denhaag-component-tokens/specs/token-set-contrast-audit/spec.md
	 */
	public function __construct(
		private readonly ContrastService $contrast = new ContrastService(),
		private readonly ColourFunctionEvaluator $functions = new ColourFunctionEvaluator(),
	) {
	}//end __construct()

	/**
	 * Measure every pair over the given cascade.
	 *
	 * @param array<string, string> $declarations Defaults, then the bridge, then the set, merged in that order.
	 *
	 * @return array<string, array{foreground: string, background: string, ratio: float|null, verdict: string}> The pairs by name.
	 *
	 * @spec openspec/changes/denhaag-component-tokens/specs/token-set-contrast-audit/spec.md
	 */
	public function pairs(array $declarations): array {
		$page = $this->resolve(declarations: $declarations, name: self::PAGE);
		if ($page === null) {
			$page = '#ffffff';
		}

		$results = [];
		foreach (self::PAIRS as $pair => [$foreground, $background]) {
			$fgValue = $this->resolve(declarations: $declarations, name: $foreground);
			$bgValue = $page;
			if ($background !== self::PAGE) {
				$bgValue = $this->resolve(declarations: $declarations, name: $background);
			}

			$ratio = null;
			if ($fgValue !== null && $bgValue !== null) {
				$ratio = $this->contrast->measure(foreground: $fgValue, background: $bgValue, page: $page);
			}

			$verdict = 'unevaluated';
			if ($ratio !== null) {
				$ratio = round($ratio, 2);
				$verdict = 'fail';
				if ($ratio >= self::AA_TEXT) {
					$verdict = 'pass';
				}
			}

			$results[$pair] = [
				'foreground' => $foreground,
				'background' => $background,
				'ratio' => $ratio,
				'verdict' => $verdict,
			];
		}//end foreach

		return $results;
	}//end pairs()

	/**
	 * The Den Haag component pairs section of the contrast report.
	 *
	 * @param array<int, array<string, mixed>> $rows The audit rows.
	 *
	 * @return array<int, string> The report lines.
	 *
	 * @spec openspec/changes/denhaag-component-tokens/specs/token-set-contrast-audit/spec.md
	 */
	public function reportLines(array $rows): array {
		$lines = [];
		$lines[] = '## Den Haag component pairs';
		$lines[] = '';
		$lines[] = 'The text the Den Haag mijn-omgeving components draw, and the site header title and';
		$lines[] = 'footer text, measured as a portal sees it:';
		$lines[] = '`css/systems/nldesign/defaults.css`, then `css/public-bridge.css`, then the set.';
		$lines[] = 'Threshold 4.5:1 for every pair. These pairs are reported; they are not part of';
		$lines[] = 'the verdict above.';
		$lines[] = '';
		$lines[] = 'Pairs: ' . implode(', ', array_keys(self::PAIRS)) . '.';
		$lines[] = '';
		$lines[] = '| Token set | pass | fail | unevaluated | Below 4.5:1 or unevaluated |';
		$lines[] = '|-----------|-----:|-----:|------------:|----------------------------|';

		foreach ($rows as $row) {
			$counts = ['pass' => 0, 'fail' => 0, 'unevaluated' => 0];
			$named = [];
			foreach (($row['denhaag'] ?? []) as $pair => $result) {
				$counts[$result['verdict']]++;
				if ($result['verdict'] !== 'pass') {
					$named[] = $pair . ' ' . $this->formatRatio(ratio: $result['ratio']);
				}
			}

			$lines[] = sprintf(
				'| %s | %d | %d | %d | %s |',
				$row['id'],
				$counts['pass'],
				$counts['fail'],
				$counts['unevaluated'],
				implode(', ', $named)
			);
		}

		$lines[] = '';

		return $lines;
	}//end reportLines()

	/**
	 * Resolve one property to a literal through its var() chain.
	 *
	 * @param array<string, string> $declarations The cascade.
	 * @param string $name The property name.
	 *
	 * @return string|null The literal, or null when the chain does not end in one.
	 *
	 * @spec openspec/changes/denhaag-component-tokens/specs/token-set-contrast-audit/spec.md
	 */
	public function resolve(array $declarations, string $name): ?string {
		if (isset($declarations[$name]) === false) {
			return null;
		}

		$literal = $this->resolveValue(declarations: $declarations, value: $declarations[$name], depth: 0);
		if ($literal === null) {
			return null;
		}

		return $this->functions->evaluate(value: $literal);
	}//end resolve()

	/**
	 * Replace the first var() in a value, then the rest, until none is left.
	 *
	 * A var() fallback may hold parentheses of its own (`rgba(...)`), so the
	 * call is cut out by counting parentheses rather than by a pattern.
	 *
	 * @param array<string, string> $declarations The cascade.
	 * @param string $value The raw value.
	 * @param int $depth How many substitutions deep this is.
	 *
	 * @return string|null The literal value, or null when a reference cannot be resolved.
	 */
	private function resolveValue(array $declarations, string $value, int $depth): ?string {
		$start = strpos($value, 'var(');
		if ($start === false) {
			return trim($value);
		}

		$call = $this->cutCall(value: $value, start: $start);
		if ($depth > self::MAX_DEPTH || $call === null) {
			return null;
		}

		[$end, $reference, $fallback] = $call;
		$replacement = null;
		if (isset($declarations[$reference]) === true) {
			$replacement = $this->resolveValue(declarations: $declarations, value: $declarations[$reference], depth: $depth + 1);
		}

		if ($replacement === null && $fallback !== null) {
			$replacement = $this->resolveValue(declarations: $declarations, value: $fallback, depth: $depth + 1);
		}

		if ($replacement === null) {
			return null;
		}

		$next = substr($value, 0, $start) . $replacement . substr($value, ($end + 1));

		return $this->resolveValue(declarations: $declarations, value: $next, depth: $depth + 1);
	}//end resolveValue()

	/**
	 * Cut one var() call out of a value.
	 *
	 * @param string $value The value.
	 * @param int $start Where `var(` starts.
	 *
	 * @return array{0: int, 1: string, 2: string|null}|null The closing parenthesis offset, the reference and the fallback, or null when malformed.
	 */
	private function cutCall(string $value, int $start): ?array {
		$open = ($start + 3);
		$level = 0;
		$comma = null;
		$length = strlen($value);
		for ($i = $open; $i < $length; $i++) {
			$char = $value[$i];
			if ($char === '(') {
				$level++;
			} elseif ($char === ')') {
				$level--;
				if ($level === 0) {
					$head = substr($value, ($open + 1), (($comma ?? $i) - $open - 1));
					$fallback = null;
					if ($comma !== null) {
						$fallback = trim(substr($value, ($comma + 1), ($i - $comma - 1)));
					}

					return [$i, trim($head), $fallback];
				}
			} elseif ($char === ',' && $level === 1 && $comma === null) {
				$comma = $i;
			}
		}//end for

		return null;
	}//end cutCall()
	/**
	 * Format a ratio for the report (2 decimals, or a dash when unevaluated).
	 *
	 * @param float|null $ratio The ratio.
	 *
	 * @return string The cell text.
	 */
	private function formatRatio(?float $ratio): string {
		if ($ratio === null) {
			return '—';
		}

		return number_format($ratio, 2, '.', '') . ':1';
	}//end formatRatio()
}//end class
