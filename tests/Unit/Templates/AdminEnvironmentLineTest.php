<?php

/**
 * The settings page shows the declared environment and never writes it.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/environment-marker/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Templates;

use PHPUnit\Framework\TestCase;

/**
 * Static guard on templates/settings/admin.php: the environment line sits
 * under the theming heading, shows the value or the occ command, and offers
 * no control that writes the value.
 */
class AdminEnvironmentLineTest extends TestCase {

	/**
	 * The environment line ships, read-only, under the section heading.
	 *
	 * @return void
	 */
	public function testTheEnvironmentLineIsReadOnly(): void {
		$template = (string)file_get_contents(dirname(__DIR__, 3) . '/templates/settings/admin.php');

		$start = strpos($template, 'id="nldesign-environment"');
		$this->assertNotFalse(condition: $start, message: 'The admin template must ship the environment line.');
		$this->assertGreaterThan(
			expected: strpos($template, '<h2>Thematiq</h2>'),
			actual: $start,
			message: 'The line sits under the theming heading.'
		);

		$end = strpos($template, '</div>', $start);
		$block = substr($template, $start, ($end - $start));
		$this->assertStringContainsString(needle: "\$_['environment']", haystack: $block);
		$this->assertStringContainsString(needle: "\$_['environmentCommand']", haystack: $block);
		$this->assertStringContainsString(needle: "'Environment: {environment}'", haystack: $block);
		$this->assertDoesNotMatchRegularExpression(
			pattern: '/<(input|select|textarea|button)\b/',
			string: $block,
			message: 'The page must not offer a control that writes the value.'
		);
	}//end testTheEnvironmentLineIsReadOnly()

	/**
	 * The heading row holds the title, the environment chip and the board's line.
	 *
	 * @spec openspec/changes/admin-pages-as-the-tq-boards/specs/admin-settings/spec.md#requirement-settings-hint-text
	 *
	 * @return void
	 */
	public function testTheHeadingRowIsTheBoards(): void {
		$template = (string)file_get_contents(dirname(__DIR__, 3) . '/templates/settings/admin.php');

		$start = strpos($template, '<div class="nldesign-settings-header">');
		$this->assertNotFalse(condition: $start);
		$row = substr($template, $start, (strpos($template, '</span>', strpos($template, 'nldesign-settings-header__line')) - $start));
		$this->assertStringContainsString(needle: '<h2>Thematiq</h2>', haystack: $row);
		$this->assertStringContainsString(needle: 'class="nldesign-environment__chip"', haystack: $row);
		$this->assertStringContainsString(
			needle: "'Choose a token set from a Dutch government body as a base, or adjust single tokens.'",
			haystack: $row
		);
	}//end testTheHeadingRowIsTheBoards()
}//end class
