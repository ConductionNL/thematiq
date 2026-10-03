<?php

/**
 * The internal scopes: the rules that carry a set's internal tokens onto the components that use them.
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
 * @spec openspec/specs/component-tokens/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCA\Thematiq\Service\RuntimeFile\RuntimeFileLocator;

/**
 * Builds, at render time, one rule per internal token the active set or the
 * admin overrides give a value, and nothing for any other token.
 *
 * WHY RULES ARE WRITTEN ONLY FOR SET TOKENS. A component that declares a
 * variable on its own element ignores anything inherited, so the value has to
 * be declared on that element: `X: var(--nldesign-nc-X)`. Written for a token
 * nobody set, that declaration would replace the component's own value with
 * nothing. So a token reaches the page only once something gives it a value.
 *
 * WHY AT RENDER TIME. The set's files, its dark variant and the overrides
 * already decide what the page wears, per group, and every place that changes
 * them takes effect on the next render. Reading their declarations here keeps
 * one source of truth, writes no file, and gives each group its own rules.
 *
 * Only names from the inventory's internal token map are emitted, and only as
 * `var()` references to the token; no value from a set or an upload reaches
 * this output.
 *
 * @spec openspec/specs/component-tokens/spec.md
 */
class InternalScopesService {

	/**
	 * One ID's worth of specificity, so a rule beats the component's own
	 * class-level declaration whatever order the stylesheets arrive in.
	 */
	public const BUMP = ':not(#thematiq-never-an-id)';

	/**
	 * The two dark scopes the dark stylesheets use: a user on the system
	 * default whose system is dark, and a user who chose the dark theme.
	 */
	private const SYSTEM_DARK = 'body:not([data-theme-light]):not([data-theme-dark])'
		. ':not([data-theme-light-highcontrast]):not([data-theme-dark-highcontrast])';
	private const CHOSEN_DARK = 'body[data-theme-dark], body[data-themes*=dark]';

	/**
	 * Constructor.
	 *
	 * @param RuntimeFileLocator $files Reads a set's files, shipped or in app data.
	 * @param CssParserService $parser Reads a stylesheet's `:root` declarations.
	 */
	public function __construct(
		private readonly RuntimeFileLocator $files,
		private readonly CssParserService $parser = new CssParserService(),
	) {
	}//end __construct()

	/**
	 * The internal scopes for a set as the page wears it.
	 *
	 * @param string $tokenSet The resolved token set.
	 * @param string $designSystemId The design system the set wears.
	 * @param bool $withDark Whether the set's dark variant is injected.
	 *
	 * @return string The CSS, or the empty string when no internal token is set.
	 *
	 * @SuppressWarnings(PHPMD.StaticAccess) - CustomOverridesService::fileFor() is a pure lookup
	 *
	 * @spec openspec/specs/component-tokens/spec.md
	 */
	public function forSet(string $tokenSet, string $designSystemId, bool $withDark): string {
		$light = (string)$this->files->read(name: 'css/tokens/' . $tokenSet . '.css');
		$overrides = (string)$this->files->read(
			name: 'css/' . CustomOverridesService::fileFor(tokenSet: $tokenSet, designSystemId: $designSystemId) . '.css'
		);
		$dark = '';
		if ($withDark === true) {
			$dark = (string)$this->files->read(name: 'css/tokens/dark/' . $tokenSet . '.css');
		}

		return $this->build(
			lightNames: array_merge(
				array_keys($this->parser->parseRootBlock(css: $light)),
				array_keys($this->parser->parseRootBlock(css: $overrides))
			),
			anyNames: $this->declaredNames(css: $light . "\n" . $overrides . "\n" . $dark)
		);
	}//end forSet()

	/**
	 * The rules for the internal tokens given a value.
	 *
	 * @param array<int, string> $lightNames Names declared on `:root`, which apply in every theme.
	 * @param array<int, string> $anyNames Names declared anywhere, including dark scopes only.
	 *
	 * @return string The CSS, or the empty string when no internal token is set.
	 *
	 * @SuppressWarnings(PHPMD.StaticAccess) - TokenRegistry uses static methods by design
	 *
	 * @spec openspec/specs/component-tokens/spec.md
	 */
	public function build(array $lightNames, array $anyNames): string {
		$internal = TokenRegistry::getInternalTokens();
		$light = array_flip($lightNames);
		$names = array_values(array_unique(array_merge($lightNames, $anyNames)));
		sort($names);

		$rules = [];
		foreach ($names as $token) {
			if (isset($internal[$token]) === false) {
				continue;
			}

			$selectors = $internal[$token]['selectors'];
			if ($internal[$token]['mode'] === 'body' || $selectors === []) {
				$selectors = ['body'];
			}

			$declaration = "\t" . $internal[$token]['variable'] . ': var(' . $token . ');';
			if (isset($light[$token]) === true) {
				$rules[] = ':is(' . implode(', ', $selectors) . ')' . self::BUMP . " {\n" . $declaration . "\n}";
				continue;
			}

			$rules[] = $this->darkOnly(selectors: $selectors, declaration: $declaration);
		}

		if ($rules === []) {
			return '';
		}

		return "/* thematiq internal scopes: the internal tokens this set and the overrides give a value. */\n"
			. implode("\n", $rules) . "\n";
	}//end build()

	/**
	 * The rule for a token declared only in dark scopes: in light the token is
	 * unset, so the rule may only apply where the dark value is visible.
	 *
	 * @param array<int, string> $selectors The component's selectors.
	 * @param string $declaration The declaration line.
	 *
	 * @return string The two scoped rules.
	 */
	private function darkOnly(array $selectors, string $declaration): string {
		$rule = function (string $scope) use ($selectors, $declaration): string {
			$list = [];
			foreach ($selectors as $selector) {
				// `:root` and `html` sit outside body, where a dark value declared on body is not visible.
				if (str_contains($selector, ':root') === true || str_starts_with($selector, 'html') === true) {
					continue;
				}

				$list[] = $this->scoped(selector: $selector, scope: $scope);
			}

			return implode(",\n", $list) . " {\n" . $declaration . "\n}";
		};

		return "@media (prefers-color-scheme: dark) {\n" . $rule(self::SYSTEM_DARK) . "\n}\n" . $rule(self::CHOSEN_DARK);
	}//end darkOnly()

	/**
	 * One selector limited to a body-level scope.
	 *
	 * @param string $selector The selector.
	 * @param string $scope The body scope.
	 *
	 * @return string The scoped selector.
	 */
	private function scoped(string $selector, string $scope): string {
		// The selector's subject is body itself: the scope is a condition on the same element.
		if (preg_match('/^body(?:\[[^\]]*\]|\.[\w-]+|#[\w-]+|:[\w-]+(?:\([^)]*\))?)*$/', $selector) === 1) {
			return ':is(' . $selector . '):where(' . $scope . ')' . self::BUMP;
		}

		return ':where(' . $scope . ') :is(' . $selector . ')' . self::BUMP;
	}//end scoped()

	/**
	 * Every custom property name a stylesheet declares.
	 *
	 * @param string $css The stylesheet.
	 *
	 * @return array<int, string> The names.
	 */
	private function declaredNames(string $css): array {
		$css = (string)preg_replace('#/\*.*?\*/#s', '', $css);
		preg_match_all('/(--nldesign-(?:nc|cn)-[A-Za-z0-9_-]+)\s*:/', $css, $matches);

		return array_values(array_unique($matches[1]));
	}//end declaredNames()
}//end class
