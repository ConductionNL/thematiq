<?php

/**
 * Regression test for the custom-fonts license-responsibility notice.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/custom-fonts/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Templates;

use PHPUnit\Framework\TestCase;

/**
 * Asserts the license-responsibility notice ships in the admin template,
 * above the upload control, using an ENGLISH translatable source key.
 *
 * A full DOM/browser assertion of "visible without further interaction" is
 * a live/e2e concern (see openspec/specs/custom-fonts/spec.md — Requirement:
 * Uploader License Responsibility, `@e2e exclude`, deferred to
 * openspec/changes/custom-font-upload/tasks.md#task-6.3); this test is the
 * static regression guard that the notice ships at all and is not
 * accidentally moved below the upload control or dropped in a future edit.
 */
class AdminFontNoticeTest extends TestCase {

	/**
	 * The license notice must be present, translatable (ENGLISH key), and
	 * positioned before the upload control markup.
	 *
	 * @return void
	 */
	public function testLicenseNoticePrecedesUploadControl(): void {
		$path = __DIR__ . '/../../../templates/settings/admin.php';
		$this->assertFileExists(filename: $path);

		$template = (string)file_get_contents($path);

		$noticeKey = 'Only upload fonts your organization holds a license to self-host. Licensing responsibility rests with the uploader.';
		$noticePos = strpos($template, $noticeKey);
		$this->assertNotFalse(condition: $noticePos, message: 'The license-responsibility notice must ship in the admin template.');

		$uploadButtonPos = strpos($template, 'nldesign-font-upload-btn');
		$this->assertNotFalse(condition: $uploadButtonPos, message: 'The font upload control must ship in the admin template.');

		$this->assertLessThan(
			expected: $uploadButtonPos,
			actual: $noticePos,
			message: 'The license notice must appear above the upload control in document order.'
		);
	}//end testLicenseNoticePrecedesUploadControl()
}//end class
