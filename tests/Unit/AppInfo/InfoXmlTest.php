<?php

/**
 * Unit tests for appinfo/info.xml: what the app store reads to list thematiq.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/platform-properties/spec.md#REQ-TPLAT-009
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\AppInfo;

use PHPUnit\Framework\TestCase;
use SimpleXMLElement;

/**
 * thematiq installs and updates from the Nextcloud app store (REQ-TPLAT-009).
 */
class InfoXmlTest extends TestCase {

	/**
	 * The app id, licence, category and Nextcloud range the store reads.
	 *
	 * @spec openspec/specs/platform-properties/spec.md#REQ-TPLAT-009
	 *
	 * @return void
	 */
	public function testTheStoreFieldsAreDeclared(): void {
		$info = new SimpleXMLElement((string)file_get_contents(__DIR__ . '/../../../appinfo/info.xml'));

		$this->assertSame(expected: 'thematiq', actual: (string)$info->id);
		$this->assertSame(expected: 'EUPL-1.2', actual: (string)$info->licence);
		$this->assertContains(needle: 'customization', haystack: array_map('strval', iterator_to_array($info->category, false)));
		$this->assertSame(expected: '32', actual: (string)$info->dependencies->nextcloud['min-version']);
		$this->assertSame(expected: '35', actual: (string)$info->dependencies->nextcloud['max-version']);
	}//end testTheStoreFieldsAreDeclared()
}//end class
