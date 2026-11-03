<?php

/**
 * The contrast evidence report is reachable from the admin settings page.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/compliance-evidence/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Templates;

use PHPUnit\Framework\TestCase;

/**
 * Until 29 Sep 2026 the report endpoint existed and nothing on the page
 * linked to it. This guards the section, both links and the route they use.
 */
class AdminComplianceReportTest extends TestCase {

	/**
	 * The repo root.
	 *
	 * @return string The absolute path.
	 */
	private function root(): string {
		return dirname(__DIR__, 3);
	}//end root()

	/**
	 * The section ships both download links, marked for the browser to save.
	 *
	 * @return void
	 */
	public function testTheSectionShipsBothDownloadLinks(): void {
		$template = (string)file_get_contents($this->root() . '/templates/settings/admin.php');

		$section = strpos($template, 'id="nldesign-compliance-report"');
		$this->assertNotFalse(condition: $section, message: 'The admin template must ship the contrast evidence report section.');

		foreach (['nldesign-compliance-report-json', 'nldesign-compliance-report-markdown'] as $id) {
			$this->assertMatchesRegularExpression(
				pattern: '/<a[^>]*id="' . $id . '"[^>]*\bdownload\b/s',
				string: $template,
				message: "The $id link must be an anchor with the download attribute."
			);
			$this->assertGreaterThan(expected: $section, actual: strpos($template, 'id="' . $id . '"'), message: "$id must sit inside the section.");
		}
	}//end testTheSectionShipsBothDownloadLinks()

	/**
	 * The URL the admin script builds is a registered, admin-only route.
	 *
	 * @return void
	 */
	public function testTheLinksPointAtTheRegisteredExportRoute(): void {
		$script = (string)file_get_contents($this->root() . '/js/admin.js');
		$this->assertStringContainsString(needle: "OC.generateUrl('/apps/thematiq/settings/compliance-report')", haystack: $script);

		$routes = (string)file_get_contents($this->root() . '/appinfo/routes.php');
		$this->assertStringContainsString(
			needle: "['name' => 'settings#complianceReport', 'url' => '/settings/compliance-report', 'verb' => 'GET']",
			haystack: $routes
		);
	}//end testTheLinksPointAtTheRegisteredExportRoute()
}//end class
