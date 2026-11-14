<?php

/**
 * Regression test for the App view of the admin preview.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/closing-round-scenarios/tasks.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Templates;

use PHPUnit\Framework\TestCase;

/**
 * The App view of the preview is a scale model of a Nextcloud app: a top
 * navbar and a left menu whose entries carry a translated label, one of them
 * selected (closing-round-scenarios task 1.1, app-live-preview-shell).
 *
 * Whether it LOOKS right is a browser concern; this is the static guard that
 * the shell ships with its navbar, its menu and one selected entry.
 */
class AdminPreviewAppShellTest extends TestCase {

	/**
	 * The markup of the App view of the preview.
	 *
	 * @return string The `data-view="app"` stage, up to the next stage.
	 */
	private function appStage(): string {
		$template = (string)file_get_contents(__DIR__ . '/../../../templates/settings/admin.php');
		$start = strpos($template, 'class="nldesign-preview-stage" data-view="app"');
		$this->assertNotFalse(condition: $start, message: 'The preview must ship an App view.');

		$next = strpos($template, 'class="nldesign-preview-stage"', ($start + 1));
		if ($next === false) {
			$next = strlen($template);
		}

		return substr($template, $start, ($next - $start));
	}//end appStage()

	/**
	 * The App view renders a navbar and a menu of labelled entries, exactly one of them active.
	 *
	 * @return void
	 */
	public function testTheAppViewHasANavbarAndAMenuWithOneActiveEntry(): void {
		$stage = $this->appStage();

		$this->assertStringContainsString(needle: 'class="nl-mini__navbar"', haystack: $stage);
		$this->assertMatchesRegularExpression(pattern: '/<nav class="nl-mini__menu">(.*?)<\/nav>/s', string: $stage);

		preg_match('/<nav class="nl-mini__menu">(.*?)<\/nav>/s', $stage, $menu);
		preg_match_all('/<span class="(nl-mini__menuitem[^"]*)">(.*?)<\/span>/s', $menu[1], $items, PREG_SET_ORDER);

		$this->assertGreaterThanOrEqual(expected: 3, actual: count($items), message: 'The menu shows several entries.');

		$active = 0;
		foreach ($items as [, $classes, $content]) {
			$this->assertMatchesRegularExpression(
				pattern: "/<\?php p\(\\\$l->t\('[^']+'\)\); \?>/",
				string: $content,
				message: 'Every menu entry carries a translated label.'
			);
			if (str_contains($classes, 'nl-mini__menuitem--active') === true) {
				$active++;
			}
		}

		$this->assertSame(expected: 1, actual: $active, message: 'Exactly one menu entry is selected.');
	}//end testTheAppViewHasANavbarAndAMenuWithOneActiveEntry()
}//end class
