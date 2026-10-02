<?php

/**
 * NL Design Token Registry.
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
 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-45
 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-46
 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-47
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * Canonical registry of editable CSS custom properties.
 *
 * This class is the single source of truth for:
 * - Which tokens the editor exposes for editing
 * - Which tab each token belongs to
 * - Which type of input to render (color picker or text field)
 * - The human-readable label for each token
 *
 * TWO LAYERS, AND THE DIFFERENCE MATTERS
 * --------------------------------------
 * BRAND tokens are Nextcloud's own globals — `--color-primary`,
 * `--color-background-hover`, `--border-radius-element`. They are hand-listed in
 * the four methods below, and moving one is MEANT to move every component that
 * has not been given a value of its own.
 *
 * COMPONENT tokens are `--nldesign-component-*`, and they are not listed here at
 * all: they are read from `scripts/mapping/component-tokens.json`, the same file
 * `scripts/generate-component-scopes.mjs` turns into `css/component-scopes.css`.
 * One table, two runtimes, so a token cannot be editable without a stylesheet
 * that applies it — or applied by a stylesheet with no way to edit it.
 *
 * WHY THE COMPONENT LAYER EXISTS. Nextcloud has around sixty globals and every
 * component draws from them, so `--color-primary-element` paints the primary
 * button, the selected navigation entry, the sidebar's active tab, a focused
 * text input, the checked checkbox, the progress bar, the dialog's confirm
 * button and the counter bubble. Before this layer the editor could only write
 * that global, so the playground's `Primary button` chip moved all eight.
 *
 * Tokens marked "intentionally not overridden" in overrides.css MUST NOT appear here.
 * The excluded list covers dark-mode vars, auto-calculated values, and layout constants.
 *
 * Tabs: login | content | status | typography
 * Types: color | text
 * Groups: `brand`, or the id of the component the token belongs to
 *
 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-45
 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-46
 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-47
 */
class TokenRegistry implements TokenRegistryInterface {
	/**
	 * The component-token table, relative to the app root.
	 *
	 * Read rather than injected because this class is static by design and the
	 * table is a shipped file, not configuration. `__DIR__` is `lib/Service`.
	 */
	private const COMPONENT_TOKENS_PATH = __DIR__ . '/../../scripts/mapping/component-tokens.json';

	/**
	 * Decoded component-token table, or null before the first read.
	 *
	 * The registry is asked for on every admin page render and the table is
	 * parsed from disk, so the result is kept for the rest of the request.
	 *
	 * @var array<string, array{tab: string, type: string, label: string, group: string, primary: bool, global: string}>|null
	 */
	private static ?array $componentTokens = null;

	/**
	 * The variable status table, relative to the app root.
	 *
	 * Its `settable` theme entries are Nextcloud variables a theme may set
	 * through an `--nldesign-nc-*` token that has no default. The same table
	 * feeds `scripts/generate-component-scopes.mjs`, so the registry and the
	 * stylesheet that applies a value cannot disagree.
	 */
	private const STATUS_PATH = __DIR__ . '/../../scripts/mapping/variable-status.json';

	/**
	 * Decoded settable entries, or null before the first read.
	 *
	 * @var array<string, array<string, mixed>>|null
	 */
	private static ?array $settableTokens = null;

	/**
	 * The internal token map, generated from the inventory by
	 * `scripts/inventory/generate-internal-tokens.mjs`.
	 */
	private const INTERNAL_TOKENS_PATH = __DIR__ . '/../../scripts/mapping/internal-tokens.json';

	/**
	 * Decoded internal tokens, or null before the first read.
	 *
	 * @var array<string, array{
	 *     variable: string,
	 *     class: string,
	 *     owner: string,
	 *     group: string,
	 *     type: string,
	 *     mode: string,
	 *     selectors: array<int, string>,
	 *     stock: string
	 * }>|null
	 */
	private static ?array $internalTokens = null;

	/**
	 * Nextcloud's own light and dark value per settable theme variable, from the same map.
	 *
	 * @var array<string, array{light: string, dark: string}>
	 */
	private static array $themeStock = [];

	/**
	 * Returns the full registry of editable tokens.
	 *
	 * Keys are CSS custom property names (e.g. '--color-primary').
	 * Values carry 'tab', 'type', 'label', 'group' and 'primary'.
	 *
	 * @return array<string, array{tab: string, type: string, label: string, group: string, primary: bool, global?: string}> The token registry.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-45
	 */
	public static function getTokens(): array {
		return array_merge(self::getBrandTokens(), self::getComponentTokens());
	}//end getTokens()

	/**
	 * Returns the brand layer: Nextcloud's own global custom properties.
	 *
	 * These are the values every component falls back to. They carry no
	 * component of their own, so they are grouped under `brand` and are never
	 * locked by the `primary_drives_components` setting — that setting exists to
	 * make these win, not to freeze them.
	 *
	 * @return array<string, array{tab: string, type: string, label: string, group: string, primary: bool}> The brand tokens.
	 *
	 * @spec openspec/specs/component-tokens/spec.md
	 */
	public static function getBrandTokens(): array {
		// Settable entries come last, so for the two that were editable before
		// (`--color-scrollbar`, `--body-container-radius`) they add the token.
		$tokens = array_merge(
			self::getLoginTokens(),
			self::getContentTokens(),
			self::getStatusTokens(),
			self::getTypographyTokens(),
			self::getSettableTokens()
		);

		return array_map(
			static fn (array $meta): array => array_merge($meta, ['group' => 'brand', 'primary' => false]),
			$tokens
		);
	}//end getBrandTokens()

	/**
	 * The settable theme variables, read from the variable status table.
	 *
	 * A missing or malformed table yields none, so the brand tokens still edit.
	 *
	 * @return array<string, array<string, mixed>> Name => tab, type, label, settable, token, and advanced / perScheme when set.
	 *
	 * @spec openspec/specs/nextcloud-variable-mapping/spec.md
	 */
	public static function getSettableTokens(): array {
		if (self::$settableTokens !== null) {
			return self::$settableTokens;
		}

		self::$settableTokens = [];
		$raw = false;
		if (is_file(self::STATUS_PATH) === true) {
			$raw = file_get_contents(self::STATUS_PATH);
		}

		$decoded = [];
		if ($raw !== false) {
			$decoded = json_decode($raw, true);
		}

		// The status file also marks the internal variables settable. Those are
		// edited by their own token name (see getInternalTokens()), not as brand
		// rows under the Nextcloud name, so they are left out here.
		$internal = self::getInternalTokens();
		foreach (($decoded['variables'] ?? []) as $name => $entry) {
			if (($entry['status'] ?? '') !== 'settable' || is_string($entry['token'] ?? null) === false
				|| isset($internal[$entry['token']]) === true
			) {
				continue;
			}

			self::$settableTokens[$name] = [
				'tab' => (string)($entry['tab'] ?? 'content'),
				'type' => (string)($entry['type'] ?? 'text'),
				'label' => (string)($entry['label'] ?? $name),
				'settable' => true,
				'token' => $entry['token'],
				'advanced' => (($entry['advanced'] ?? false) === true),
				'perScheme' => (($entry['perScheme'] ?? false) === true),
				'note' => (string)($entry['note'] ?? ''),
				'stock' => (self::$themeStock[$name] ?? ['light' => '', 'dark' => '']),
			];
		}

		return self::$settableTokens;
	}//end getSettableTokens()

	/**
	 * The `--nldesign-nc-*` token a settable variable is stored as, or null for any other name.
	 *
	 * @param string $tokenName A registry name, such as `--color-mark`.
	 *
	 * @return string|null The token.
	 *
	 * @spec openspec/specs/nextcloud-variable-mapping/spec.md
	 */
	public static function settableToken(string $tokenName): ?string {
		return (self::getSettableTokens()[$tokenName]['token'] ?? null);
	}//end settableToken()

	/**
	 * Returns the component layer, read from the shared mapping table.
	 *
	 * A missing or malformed table degrades to an empty component layer rather
	 * than an error: the brand tokens still edit, and the instance still renders,
	 * because `css/component-scopes.css` falls back to the captured global for
	 * every component token nobody set.
	 *
	 * @return array<string, array{tab: string, type: string, label: string, group: string, primary: bool, global: string}> The component tokens.
	 *
	 * @spec openspec/specs/component-tokens/spec.md
	 */
	public static function getComponentTokens(): array {
		if (self::$componentTokens !== null) {
			return self::$componentTokens;
		}

		self::$componentTokens = [];

		$raw = false;
		if (is_file(self::COMPONENT_TOKENS_PATH) === true) {
			$raw = file_get_contents(self::COMPONENT_TOKENS_PATH);
		}

		if ($raw === false) {
			return self::$componentTokens;
		}

		$decoded = json_decode($raw, true);
		if (is_array($decoded) === false || is_array($decoded['components'] ?? null) === false) {
			return self::$componentTokens;
		}

		foreach ($decoded['components'] as $componentId => $component) {
			if (is_array($component['tokens'] ?? null) === false) {
				continue;
			}

			foreach ($component['tokens'] as $name => $meta) {
				self::$componentTokens[$name] = [
					'tab' => (string)($component['tab'] ?? 'content'),
					'type' => (string)($meta['type'] ?? 'text'),
					'label' => (string)($meta['label'] ?? $name),
					'group' => (string)$componentId,
					'primary' => (($meta['primary'] ?? false) === true),
					// The Nextcloud variable this token replaces inside the
					// component. A component token is deliberately undeclared
					// until someone sets one, so `getComputedStyle` reports it
					// as the empty string — the editor reads this global
					// instead, and shows the colour the component is ACTUALLY
					// wearing rather than a blank swatch.
					'global' => (string)($meta['global'] ?? ''),
				];
			}
		}

		return self::$componentTokens;
	}//end getComponentTokens()

	/**
	 * Returns login and branding tab tokens.
	 *
	 * @return array<string, array{tab: string, type: string, label: string}> Login tokens.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-45
	 */
	private static function getLoginTokens(): array {
		return [
			'--color-primary' => ['tab' => 'login', 'type' => 'color', 'label' => 'Primary color'],
			'--color-primary-text' => ['tab' => 'login', 'type' => 'color', 'label' => 'Primary text color'],
			'--color-primary-hover' => ['tab' => 'login', 'type' => 'color', 'label' => 'Primary hover color'],
			'--color-primary-element' => ['tab' => 'login', 'type' => 'color', 'label' => 'Primary element color'],
			'--color-primary-element-hover' => ['tab' => 'login', 'type' => 'color', 'label' => 'Primary element hover'],
			'--color-primary-element-text' => ['tab' => 'login', 'type' => 'color', 'label' => 'Primary element text'],
			'--color-primary-light' => ['tab' => 'login', 'type' => 'color', 'label' => 'Primary light'],
			'--color-primary-light-hover' => ['tab' => 'login', 'type' => 'color', 'label' => 'Primary light hover'],
			'--color-primary-light-text' => ['tab' => 'login', 'type' => 'color', 'label' => 'Primary light text'],
			'--color-primary-element-light' => ['tab' => 'login', 'type' => 'color', 'label' => 'Primary element light'],
			'--color-primary-element-light-text' => ['tab' => 'login', 'type' => 'color', 'label' => 'Primary element light text'],
			'--color-primary-element-light-hover' => ['tab' => 'login', 'type' => 'color', 'label' => 'Primary element light hover'],
		];
	}//end getLoginTokens()

	/**
	 * Returns content area tab tokens.
	 *
	 * @return array<string, array{tab: string, type: string, label: string}> Content tokens.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-45
	 */
	private static function getContentTokens(): array {
		return [
			'--color-background-hover' => ['tab' => 'content', 'type' => 'color', 'label' => 'Background hover'],
			'--color-background-dark' => ['tab' => 'content', 'type' => 'color', 'label' => 'Background dark'],
			'--color-background-darker' => ['tab' => 'content', 'type' => 'color', 'label' => 'Background darker'],
			'--color-placeholder-light' => ['tab' => 'content', 'type' => 'color', 'label' => 'Placeholder light'],
			'--color-placeholder-dark' => ['tab' => 'content', 'type' => 'color', 'label' => 'Placeholder dark'],
			'--color-border' => ['tab' => 'content', 'type' => 'color', 'label' => 'Border color'],
			'--color-border-dark' => ['tab' => 'content', 'type' => 'color', 'label' => 'Border dark'],
			'--color-border-maxcontrast' => ['tab' => 'content', 'type' => 'color', 'label' => 'Border max contrast'],
			'--color-scrollbar' => ['tab' => 'content', 'type' => 'color', 'label' => 'Scrollbar color'],
			'--border-radius' => ['tab' => 'content', 'type' => 'text',  'label' => 'Border radius'],
			'--border-radius-small' => ['tab' => 'content', 'type' => 'text',  'label' => 'Border radius small'],
			'--border-radius-element' => ['tab' => 'content', 'type' => 'text',  'label' => 'Border radius element'],
			'--border-radius-large' => ['tab' => 'content', 'type' => 'text',  'label' => 'Border radius large'],
			'--border-radius-rounded' => ['tab' => 'content', 'type' => 'text',  'label' => 'Border radius rounded'],
			'--border-radius-pill' => ['tab' => 'content', 'type' => 'text',  'label' => 'Border radius pill'],
			'--body-container-radius' => ['tab' => 'content', 'type' => 'text',  'label' => 'Body container radius'],
			'--animation-quick' => ['tab' => 'content', 'type' => 'duration', 'label' => 'Animation quick'],
			'--animation-slow' => ['tab' => 'content', 'type' => 'duration', 'label' => 'Animation slow'],
			'--nldesign-animation-easing' => ['tab' => 'content', 'type' => 'easing', 'label' => 'Animation easing'],
		];
	}//end getContentTokens()

	/**
	 * Returns status and feedback tab tokens.
	 *
	 * @return array<string, array{tab: string, type: string, label: string}> Status tokens.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-45
	 */
	private static function getStatusTokens(): array {
		return [
			'--color-error' => ['tab' => 'status', 'type' => 'color', 'label' => 'Error color'],
			'--color-error-hover' => ['tab' => 'status', 'type' => 'color', 'label' => 'Error hover'],
			'--color-error-rgb' => ['tab' => 'status', 'type' => 'text',  'label' => 'Error color (RGB)'],
			'--color-element-error' => ['tab' => 'status', 'type' => 'color', 'label' => 'Element error'],
			'--color-border-error' => ['tab' => 'status', 'type' => 'color', 'label' => 'Border error'],
			'--color-warning' => ['tab' => 'status', 'type' => 'color', 'label' => 'Warning color'],
			'--color-warning-rgb' => ['tab' => 'status', 'type' => 'text',  'label' => 'Warning color (RGB)'],
			'--color-element-warning' => ['tab' => 'status', 'type' => 'color', 'label' => 'Element warning'],
			'--color-success' => ['tab' => 'status', 'type' => 'color', 'label' => 'Success color'],
			'--color-success-rgb' => ['tab' => 'status', 'type' => 'text',  'label' => 'Success color (RGB)'],
			'--color-element-success' => ['tab' => 'status', 'type' => 'color', 'label' => 'Element success'],
			'--color-border-success' => ['tab' => 'status', 'type' => 'color', 'label' => 'Border success'],
			'--color-info' => ['tab' => 'status', 'type' => 'color', 'label' => 'Info color'],
			'--color-element-info' => ['tab' => 'status', 'type' => 'color', 'label' => 'Element info'],
			'--color-favorite' => ['tab' => 'status', 'type' => 'color', 'label' => 'Favorite (star) color'],
		];
	}//end getStatusTokens()

	/**
	 * Returns typography tab tokens.
	 *
	 * @return array<string, array{tab: string, type: string, label: string}> Typography tokens.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-45
	 */
	private static function getTypographyTokens(): array {
		return [
			'--color-main-text' => ['tab' => 'typography', 'type' => 'color', 'label' => 'Main text color'],
			'--color-text-maxcontrast' => ['tab' => 'typography', 'type' => 'color', 'label' => 'Text max contrast'],
			'--color-text-light' => ['tab' => 'typography', 'type' => 'color', 'label' => 'Text light'],
			'--color-text-lighter' => ['tab' => 'typography', 'type' => 'color', 'label' => 'Text lighter'],
			'--color-text-error' => ['tab' => 'typography', 'type' => 'color', 'label' => 'Text error'],
			'--color-text-success' => ['tab' => 'typography', 'type' => 'color', 'label' => 'Text success'],
			'--color-text-warning' => ['tab' => 'typography', 'type' => 'color', 'label' => 'Text warning'],
			'--font-face' => ['tab' => 'typography', 'type' => 'text',  'label' => 'Font family'],
			'--font-weight-default' => ['tab' => 'typography', 'type' => 'text',  'label' => 'Body font weight'],
			'--font-weight-element' => ['tab' => 'typography', 'type' => 'text',  'label' => 'Control font weight'],
			'--font-weight-heading' => ['tab' => 'typography', 'type' => 'text',  'label' => 'Heading font weight'],
		];
	}//end getTypographyTokens()

	/**
	 * Returns the display labels for each tab.
	 *
	 * @return array<string, string> Map of tab id to display label.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-46
	 */
	public static function getTabLabels(): array {
		return [
			'login' => 'Login page & Branding',
			'content' => 'Content area',
			'status' => 'Buttons & Status',
			'typography' => 'Typography',
		];
	}//end getTabLabels()

	/**
	 * Returns the set of all editable token names.
	 *
	 * @return array<string> List of token names.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-45
	 */
	public static function getTokenNames(): array {
		return array_keys(self::getTokens());
	}//end getTokenNames()

	/**
	 * How many tokens the editor offers: the registry's tabs plus the internal tokens.
	 *
	 * The editor heading and the user docs state this number, so they cannot drift apart.
	 *
	 * @return int The count.
	 *
	 * @spec openspec/specs/token-editor-ui/spec.md
	 */
	public static function countEditable(): int {
		return count(self::getTokens()) + count(self::getInternalTokens());
	}//end countEditable()

	/**
	 * Checks whether a given token name is editable.
	 *
	 * @param string $tokenName The CSS custom property name.
	 *
	 * @return bool True if the token is in the registry.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-47
	 */
	public static function isEditable(string $tokenName): bool {
		return array_key_exists($tokenName, self::getTokens()) === true
			|| array_key_exists($tokenName, self::getInternalTokens()) === true;
	}//end isEditable()

	/**
	 * The internal tokens: one per settable component, slot and Conduction variable.
	 *
	 * Keyed by token name (`--nldesign-nc-dp-hover-color`, `--nldesign-cn-kpi-accent`).
	 * They are kept out of {@see self::getTokens()}, which feeds the token editor's
	 * tabs, until the editor can list them per component. A missing or malformed
	 * map yields none.
	 *
	 * @return array<string, array{
	 *     variable: string,
	 *     class: string,
	 *     owner: string,
	 *     group: string,
	 *     type: string,
	 *     mode: string,
	 *     selectors: array<int, string>,
	 *     stock: string
	 * }> The internal tokens.
	 *
	 * @spec openspec/specs/component-tokens/spec.md
	 */
	public static function getInternalTokens(): array {
		if (self::$internalTokens !== null) {
			return self::$internalTokens;
		}

		self::$internalTokens = [];
		$raw = false;
		if (is_file(self::INTERNAL_TOKENS_PATH) === true) {
			$raw = file_get_contents(self::INTERNAL_TOKENS_PATH);
		}

		$decoded = [];
		if ($raw !== false) {
			$decoded = json_decode($raw, true);
		}

		self::$themeStock = [];
		foreach (($decoded['themeStock'] ?? []) as $name => $stock) {
			if (is_array($stock) === true) {
				self::$themeStock[(string)$name] = ['light' => (string)($stock['light'] ?? ''), 'dark' => (string)($stock['dark'] ?? '')];
			}
		}

		foreach (($decoded['tokens'] ?? []) as $token => $entry) {
			if (is_array($entry) === false || is_string($entry['variable'] ?? null) === false) {
				continue;
			}

			self::$internalTokens[(string)$token] = [
				'variable' => $entry['variable'],
				'class' => (string)($entry['class'] ?? 'component'),
				'owner' => (string)($entry['owner'] ?? ''),
				'group' => (string)($entry['group'] ?? 'other'),
				'type' => (string)($entry['type'] ?? 'text'),
				'mode' => (string)($entry['mode'] ?? 'body'),
				'selectors' => array_values(array_map('strval', (array)($entry['selectors'] ?? []))),
				'stock' => (string)($entry['stock'] ?? ''),
			];
		}

		return self::$internalTokens;
	}//end getInternalTokens()

	/**
	 * Returns tokens grouped by tab.
	 *
	 * @return array<string, array<string, array{tab: string, type: string, label: string, group: string, primary: bool}>> Tokens grouped by tab id.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-46
	 */
	public static function getTokensByTab(): array {
		$grouped = [];
		foreach (self::getTokens() as $name => $meta) {
			$grouped[$meta['tab']][$name] = $meta;
		}

		return $grouped;
	}//end getTokensByTab()
}//end class
