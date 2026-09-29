<?php

/**
 * The contrast evidence report is reachable from the admin settings page.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/accessibility-evidence-download-and-dark-logo/specs/compliance-evidence/spec.md
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
	 */
	public function testTheSectionShipsBothDownloadLinks(): void {
		$template = (string)file_get_contents($this->root() . '/templates/settings/admin.php');

		$section = strpos($template, 'id="nldesign-compliance-report"');
		$this->assertNotFalse($section, 'The admin template must ship the contrast evidence report section.');

		foreach (['nldesign-compliance-report-json', 'nldesign-compliance-report-markdown'] as $id) {
			$this->assertMatchesRegularExpression(
				'/<a[^>]*id="' . $id . '"[^>]*\bdownload\b/s',
				$template,
				"The $id link must be an anchor with the download attribute."
			);
			$this->assertGreaterThan($section, strpos($template, 'id="' . $id . '"'), "$id must sit inside the section.");
		}
	}//end testTheSectionShipsBothDownloadLinks()

	/**
	 * The URL the admin script builds is a registered, admin-only route.
	 */
	public function testTheLinksPointAtTheRegisteredExportRoute(): void {
		$script = (string)file_get_contents($this->root() . '/js/admin.js');
		$this->assertStringContainsString("OC.generateUrl('/apps/thematiq/settings/compliance-report')", $script);

		$routes = (string)file_get_contents($this->root() . '/appinfo/routes.php');
		$this->assertStringContainsString(
			"['name' => 'settings#complianceReport', 'url' => '/settings/compliance-report', 'verb' => 'GET']",
			$routes
		);
	}//end testTheLinksPointAtTheRegisteredExportRoute()
}//end class
