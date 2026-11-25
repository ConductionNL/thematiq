<?php

/**
 * Tests for AppMenuIcons.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Tests
 * @package   OCA\Thematiq
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 *
 * @spec openspec/specs/apps-menu-settings/spec.md#requirement-external-apps-are-checked-on-the-server
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\AppMenuIcons;
use OCA\Thematiq\Service\ImageSniffer;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Files\SimpleFS\ISimpleFolder;
use PHPUnit\Framework\TestCase;

/**
 * Over an in-memory app data folder.
 *
 * @spec openspec/specs/apps-menu-settings/spec.md#requirement-external-apps-are-checked-on-the-server
 */
final class AppMenuIconsTest extends TestCase {

	/**
	 * The stored files, by name.
	 *
	 * @var array<string, string>
	 */
	private array $files = [];

	/**
	 * The service over the in-memory folder.
	 *
	 * @return AppMenuIcons
	 */
	private function icons(): AppMenuIcons {
		$folder = $this->createMock(originalClassName: ISimpleFolder::class);
		$folder->method('fileExists')->willReturnCallback(fn (string $name): bool => isset($this->files[$name]));
		$folder->method('newFile')->willReturnCallback(
			function (string $name, $content = null): ISimpleFile {
				$this->files[$name] = (string)$content;

				return $this->createMock(originalClassName: ISimpleFile::class);
			}
		);
		$folder->method('getFile')->willReturnCallback(
			function (string $name): ISimpleFile {
				if (isset($this->files[$name]) === false) {
					throw new NotFoundException($name);
				}

				$file = $this->createMock(originalClassName: ISimpleFile::class);
				$file->method('getContent')->willReturn($this->files[$name]);

				return $file;
			}
		);
		$appData = $this->createMock(originalClassName: IAppData::class);
		$appData->method('getFolder')->willReturn($folder);

		return new AppMenuIcons(appData: $appData, sniffer: new ImageSniffer());
	}//end icons()

	/**
	 * A PNG and a safe SVG are kept under the hash of their bytes and read back.
	 *
	 * @return void
	 */
	public function testAPngAndASafeSvgAreKept(): void {
		$icons = $this->icons();
		$png = "\x89PNG\r\n\x1a\n" . str_repeat('x', 64);
		$id = $icons->store(bytes: $png);
		$this->assertMatchesRegularExpression(pattern: AppMenuIcons::ID_PATTERN, string: (string)$id);
		$this->assertSame(expected: ['bytes' => $png, 'mime' => 'image/png'], actual: $icons->read(id: (string)$id));
		$this->assertSame(expected: $id, actual: $icons->store(bytes: $png), message: 'the same icon is kept once');
		$this->assertCount(expectedCount: 1, haystack: $this->files);

		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M0 0h24v24H0z"/></svg>';
		$this->assertSame(expected: 'image/svg+xml', actual: $icons->read(id: (string)$icons->store(bytes: $svg))['mime']);
	}//end testAPngAndASafeSvgAreKept()

	/**
	 * An SVG with script, another image type, or more than 100 kB is refused; an id of another
	 * shape reads nothing.
	 *
	 * @return void
	 */
	public function testWhatMayNotRunOrIsTooLargeIsRefused(): void {
		$icons = $this->icons();
		$this->assertNull(actual: $icons->store(bytes: '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'));
		$this->assertNull(actual: $icons->store(bytes: '<svg onload="alert(1)"></svg>'));
		$this->assertNull(actual: $icons->store(bytes: "\xFF\xD8\xFF" . 'jpeg'));
		$this->assertNull(actual: $icons->store(bytes: "\x89PNG\r\n\x1a\n" . str_repeat('x', AppMenuIcons::MAX_BYTES)));
		$this->assertSame(expected: [], actual: $this->files);
		$this->assertNull(actual: $icons->read(id: '../../config'));
		$this->assertNull(actual: $icons->read(id: str_repeat('a', 32)));
	}//end testWhatMayNotRunOrIsTooLargeIsRefused()
}//end class
