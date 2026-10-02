<?php

/**
 * The motion speeds the token editor offers reach the theme's own transitions.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/token-editor-ui/spec.md#requirement-motion-tokens-are-typed-and-reach-every-transition
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit;

use OCA\Thematiq\Service\TokenRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Thematiq#697: the editor writes `--animation-quick` / `--animation-slow`,
 * while `css/systems/nldesign/theme.css` timed its transitions with
 * `--nldesign-animation-*`, which the editor never sets. `overrides.css` only
 * maps the nldesign names onto the Nextcloud names, so an admin's speed never
 * reached the theme's buttons and links.
 *
 * Read from the shipped stylesheets and the real TokenRegistry, so it fails if
 * either side drifts.
 */
class MotionTokensReachTransitionsTest extends TestCase {

	/**
	 * Every custom property a `transition` or `animation` in theme.css times itself with.
	 *
	 * @return array<int, string> The property names, e.g. `--animation-quick`.
	 */
	private function motionVarsInTheme(): array {
		$css = (string)file_get_contents(\dirname(__DIR__, 2) . '/css/systems/nldesign/theme.css');
		$this->assertNotSame('', $css, 'theme.css must be readable.');

		preg_match_all('/(?:transition|animation)[a-z-]*\s*:([^;]*);/i', $css, $declarations);
		$names = [];
		foreach ($declarations[1] as $value) {
			preg_match_all('/var\(\s*(--[A-Za-z0-9_-]+)/', $value, $vars);
			foreach ($vars[1] as $name) {
				$names[$name] = true;
			}
		}

		return array_keys($names);
	}//end motionVarsInTheme()

	/**
	 * Every duration theme.css reads is one the token editor can set.
	 */
	public function testEveryThemeTransitionReadsAnEditableMotionToken(): void {
		$names = $this->motionVarsInTheme();
		$this->assertNotEmpty($names, 'theme.css is expected to time its transitions with a token.');

		foreach ($names as $name) {
			$this->assertTrue(
				TokenRegistry::isEditable(tokenName: $name),
				$name . ' times a transition in theme.css, but the token editor cannot set it.'
			);
		}
	}//end testEveryThemeTransitionReadsAnEditableMotionToken()

	/**
	 * The set's own speed still reaches the Nextcloud names the theme now reads.
	 */
	public function testTheSetSpeedStillFeedsTheNextcloudNames(): void {
		$overrides = (string)file_get_contents(\dirname(__DIR__, 2) . '/css/systems/nldesign/overrides.css');

		$this->assertMatchesRegularExpression('/--animation-quick:\s*var\(--nldesign-animation-quick\)/', $overrides);
		$this->assertMatchesRegularExpression('/--animation-slow:\s*var\(--nldesign-animation-slow\)/', $overrides);
	}//end testTheSetSpeedStillFeedsTheNextcloudNames()
}//end class
