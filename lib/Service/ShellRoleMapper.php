<?php

/**
 * Maps a resolved token set onto the eighteen shell roles.
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
 * @spec openspec/changes/shell-tokens-endpoint/specs/shell-tokens/spec.md#requirement-every-shell-role-always-has-a-value
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * The shell of another product (a header, buttons, links, panels) needs
 * eighteen values, not the few hundred a token set declares. This class reads
 * each one from where a themed Nextcloud reads it: the administrator's own
 * value of the Nextcloud variable first, then the token that variable maps to
 * in `css/systems/nldesign/overrides.css`. A role no source fills is derived
 * from another role, or takes the stock Nextcloud value in {@see self::DEFAULTS}.
 * No role is ever empty.
 *
 * Pure: no file, no config and no request is read here, so the same input
 * always gives the same eighteen values.
 *
 * @spec openspec/changes/shell-tokens-endpoint/specs/shell-tokens/spec.md#requirement-every-shell-role-always-has-a-value
 */
class ShellRoleMapper {

	/**
	 * The eighteen roles, in the order the response lists them.
	 *
	 * @var string[]
	 */
	public const ROLES = [
		'primary',
		'primary-deep',
		'primary-light',
		'action',
		'action-deep',
		'link',
		'accent',
		'ink',
		'text',
		'muted',
		'line',
		'soft',
		'ground',
		'chip',
		'field',
		'radius',
		'radius-lg',
		'font-family',
	];

	/**
	 * What a role is when nothing fills it: the stock Nextcloud look, as
	 * `css/tokens/nextcloud.css` records it.
	 *
	 * @var array<string, string>
	 */
	public const DEFAULTS = [
		'primary' => '#00679e',
		'primary-deep' => '#00486f',
		'primary-light' => '#e6f0f5',
		'action' => '#00679e',
		'action-deep' => '#00486f',
		'link' => '#00679e',
		'accent' => '#d32f2f',
		'ink' => '#222222',
		'text' => '#4c4c4c',
		'muted' => '#767676',
		'line' => '#d9d9d9',
		'soft' => '#ededed',
		'ground' => '#ffffff',
		'chip' => '#f5f5f5',
		'field' => '#767676',
		'radius' => '8px',
		'radius-lg' => '8px',
		'font-family' => "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen-Sans, Cantarell, Ubuntu, 'Helvetica Neue', Arial, sans-serif",
	];

	/**
	 * Where each role is read from, first hit wins.
	 *
	 * Per role: the Nextcloud variable an administrator can set in the token
	 * editor, the Summer Breeze name (that set keeps its own vocabulary), then
	 * the `--nldesign-*` token the Nextcloud variable maps to. `text` has no
	 * source: no token set declares a body colour between ink and muted, so it
	 * is always derived.
	 *
	 * @var array<string, string[]>
	 */
	private const SOURCES = [
		'primary' => ['--color-primary', '--summer-color-primary', '--nldesign-color-primary'],
		'primary-deep' => ['--color-primary-hover', '--summer-color-primary-hover', '--nldesign-color-primary-hover'],
		'primary-light' => ['--color-primary-light', '--summer-color-primary-light', '--nldesign-color-primary-light'],
		'action' => ['--color-primary-element', '--summer-color-primary', '--nldesign-color-primary'],
		'action-deep' => ['--color-primary-element-hover', '--summer-color-primary-hover', '--nldesign-color-primary-hover'],
		'link' => ['--summer-color-primary', '--nldesign-color-link'],
		'accent' => ['--nldesign-color-accent', '--color-error', '--summer-color-error', '--nldesign-color-error'],
		'ink' => ['--color-main-text', '--summer-color-text', '--nldesign-color-text'],
		'text' => [],
		'muted' => ['--color-text-maxcontrast', '--summer-color-text-muted', '--nldesign-color-text-muted'],
		'line' => ['--color-border', '--summer-color-border', '--nldesign-color-border'],
		'soft' => ['--color-background-dark', '--summer-color-background-dark', '--nldesign-color-background-dark'],
		'ground' => [
			'--nldesign-component-content-surface-background-color',
			'--summer-color-background-plain',
			'--nldesign-color-background',
		],
		'chip' => ['--color-background-hover', '--summer-color-background-hover', '--nldesign-color-background-hover'],
		'field' => ['--color-border-maxcontrast', '--summer-color-border-input', '--nldesign-color-border-dark'],
		'radius' => ['--border-radius', '--summer-radius', '--nldesign-border-radius'],
		'radius-lg' => ['--border-radius-large', '--summer-radius-large', '--nldesign-border-radius-large'],
		'font-family' => ['--font-face', '--summer-font-family', '--nldesign-font-family'],
	];

	/**
	 * How much black a deep colour takes when it is derived.
	 *
	 * @var float
	 */
	private const DEEPEN = 0.3;

	/**
	 * How much white a light colour takes when it is derived.
	 *
	 * @var float
	 */
	private const LIGHTEN = 0.9;

	/**
	 * Constructor.
	 *
	 * @param ContrastService $colours Parses a colour literal.
	 */
	public function __construct(
		private readonly ContrastService $colours,
	) {
	}//end __construct()

	/**
	 * The eighteen roles of one token set.
	 *
	 * @param array<string, string> $tokens The set's declarations with `var()` chains resolved.
	 * @param array<string, string> $overrides The administrator's own values, by Nextcloud variable.
	 *
	 * @return array<string, string> Role => CSS value, every role of {@see self::ROLES} present and non-empty.
	 *
	 * @spec openspec/changes/shell-tokens-endpoint/specs/shell-tokens/spec.md#requirement-every-shell-role-always-has-a-value
	 */
	public function map(array $tokens, array $overrides = []): array {
		$declared = array_merge($tokens, $overrides);

		$read = [];
		foreach (self::SOURCES as $role => $names) {
			$read[$role] = $this->firstUsable(names: $names, declared: $declared);
		}

		$primary = ($read['primary'] ?? self::DEFAULTS['primary']);
		$action = ($read['action'] ?? $primary);
		$ink = ($read['ink'] ?? self::DEFAULTS['ink']);
		$muted = ($read['muted'] ?? self::DEFAULTS['muted']);

		return [
			'primary' => $primary,
			'primary-deep' => $this->deeper(candidate: $read['primary-deep'], base: $primary),
			'primary-light' => ($read['primary-light']
				?? $this->mix(first: $primary, second: '#ffffff', weight: self::LIGHTEN)
				?? self::DEFAULTS['primary-light']),
			'action' => $action,
			'action-deep' => $this->deeper(candidate: $read['action-deep'], base: $action),
			'link' => ($read['link'] ?? $primary),
			'accent' => ($read['accent'] ?? self::DEFAULTS['accent']),
			'ink' => $ink,
			'text' => ($this->mix(first: $ink, second: $muted, weight: 0.5) ?? $ink),
			'muted' => $muted,
			'line' => ($read['line'] ?? self::DEFAULTS['line']),
			'soft' => ($read['soft'] ?? self::DEFAULTS['soft']),
			'ground' => ($read['ground'] ?? self::DEFAULTS['ground']),
			'chip' => ($read['chip'] ?? self::DEFAULTS['chip']),
			'field' => ($read['field'] ?? $read['line'] ?? self::DEFAULTS['field']),
			'radius' => $this->length(value: ($read['radius'] ?? self::DEFAULTS['radius'])),
			'radius-lg' => $this->length(value: ($read['radius-lg'] ?? $read['radius'] ?? self::DEFAULTS['radius-lg'])),
			'font-family' => ($read['font-family'] ?? self::DEFAULTS['font-family']),
		];
	}//end map()

	/**
	 * The first declared name that holds a value a consumer can use.
	 *
	 * @param string[] $names The names to try, in order.
	 * @param array<string, string> $declared Every declaration.
	 *
	 * @return string|null The value, or null when none of the names holds a usable one.
	 */
	private function firstUsable(array $names, array $declared): ?string {
		foreach ($names as $name) {
			$value = $this->usable(value: ($declared[$name] ?? null));
			if ($value !== null) {
				return $value;
			}
		}

		return null;
	}//end firstUsable()

	/**
	 * A declaration value as one line, or null when a consumer cannot use it.
	 *
	 * Refused: an empty value, a `var()` chain that did not resolve, and
	 * anything that could leave the declaration it is written into (`;`, a
	 * brace, a comment, an escape, `url()`). A token set can be uploaded, and
	 * the consumer writes these values into its own stylesheet.
	 *
	 * @param mixed $value The raw value.
	 *
	 * @return string|null The value with its white space collapsed, or null.
	 */
	private function usable(mixed $value): ?string {
		if (is_string($value) === false) {
			return null;
		}

		$value = trim((string)preg_replace('/\s+/', ' ', $value));
		if ($value === '' || preg_match('/var\s*\(|url\s*\(|[;{}<>\\\\]|\/\*/i', $value) === 1) {
			return null;
		}

		return $value;
	}//end usable()

	/**
	 * A colour that is darker than its base: the candidate when it is, else the base with black mixed in.
	 *
	 * A token set's hover colour is often lighter than its primary (stock
	 * Nextcloud and Rijkshuisstijl both are). The role is the dark step, so a
	 * lighter candidate is not taken.
	 *
	 * @param string|null $candidate The set's own hover colour, when it has one.
	 * @param string $base The colour the role must be darker than.
	 *
	 * @return string The deep colour.
	 */
	private function deeper(?string $candidate, string $base): string {
		$baseRgb = $this->colours->parseColor(value: $base);
		if ($candidate !== null) {
			$candidateRgb = $this->colours->parseColor(value: $candidate);
			// A candidate that cannot be compared (a colour function, a named
			// colour) is the set's own choice and is kept as it is.
			if ($baseRgb === null || $candidateRgb === null || $this->weight(rgb: $candidateRgb) < $this->weight(rgb: $baseRgb)) {
				return $candidate;
			}
		}

		return ($this->mix(first: $base, second: '#000000', weight: self::DEEPEN) ?? $base);
	}//end deeper()

	/**
	 * Two colours mixed, as a hex literal.
	 *
	 * @param string $first The first colour.
	 * @param string $second The second colour.
	 * @param float $weight The share of the second colour, 0 to 1.
	 *
	 * @return string|null `#rrggbb`, or null when either colour is not a hex or rgb() literal.
	 */
	private function mix(string $first, string $second, float $weight): ?string {
		$one = $this->colours->parseColor(value: $first);
		$two = $this->colours->parseColor(value: $second);
		if ($one === null || $two === null) {
			return null;
		}

		$hex = '#';
		foreach ([0, 1, 2] as $channel) {
			$hex .= sprintf('%02x', (int)round(($one[$channel] * (1 - $weight)) + ($two[$channel] * $weight)));
		}

		return $hex;
	}//end mix()

	/**
	 * How light a colour is, for ordering two colours only.
	 *
	 * @param array{0: int, 1: int, 2: int} $rgb The colour.
	 *
	 * @return float The weighted channel sum.
	 */
	private function weight(array $rgb): float {
		return ((0.2126 * $rgb[0]) + (0.7152 * $rgb[1]) + (0.0722 * $rgb[2]));
	}//end weight()

	/**
	 * A radius as a CSS length: a bare `0` gets its unit.
	 *
	 * @param string $value The radius.
	 *
	 * @return string The length.
	 */
	private function length(string $value): string {
		if ($value === '0') {
			return '0px';
		}

		return $value;
	}//end length()
}//end class
