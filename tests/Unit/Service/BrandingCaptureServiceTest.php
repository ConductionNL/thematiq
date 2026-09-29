<?php
/**
 * Unit tests for BrandingCaptureService.
 *
 * @category  Test
 * @package   OCA\Thematiq\Tests\Unit\Service
 * @author    Conduction Development Team <dev@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://conduction.nl
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Theming\ImageManager;
use OCA\Thematiq\Service\BrandingCaptureService;
use OCP\App\IAppManager;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;

/**
 * A theme saved from the editor has to come back with the Nextcloud branding
 * it was saved with. These pin what is captured — the colours, which of the
 * three background states was on, and a copy of every uploaded image — and
 * that the copies land where the theming sync will accept them.
 */
class BrandingCaptureServiceTest extends TestCase {

	/**
	 * The app directory images are copied into.
	 *
	 * @var string
	 */
	private string $appDir;

	/**
	 * The app values, by "app/key".
	 *
	 * @var array<string, string>
	 */
	private array $values = [];

	/**
	 * The image manager mock.
	 *
	 * @var ImageManager&\PHPUnit\Framework\MockObject\MockObject
	 */
	private $imageManager;

	/**
	 * The service under test.
	 *
	 * @var BrandingCaptureService
	 */
	private BrandingCaptureService $service;

	/**
	 * Build the service against a temp app dir and an in-memory app config.
	 */
	protected function setUp(): void {
		parent::setUp();

		if (class_exists(ImageManager::class) === false) {
			$this->markTestSkipped('OCA\Theming is not autoloadable in this environment (no installed Nextcloud root).');
		}

		$this->appDir = sys_get_temp_dir() . '/thematiq-capture-test-' . uniqid();
		mkdir($this->appDir, 0777, true);

		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getAppPath')->willReturn($this->appDir);

		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			fn (string $app, string $key, $default = ''): string => ($this->values[$app . '/' . $key] ?? (string)$default)
		);
		$config->method('setAppValue')->willReturnCallback(
			function (string $app, string $key, $value): void {
				$this->values[$app . '/' . $key] = (string)$value;
			}
		);

		$this->imageManager = $this->createMock(ImageManager::class);

		$this->service = new BrandingCaptureService($this->imageManager, $config, $appManager);
	}//end setUp()

	/**
	 * Remove the temp app dir after each test.
	 */
	protected function tearDown(): void {
		if (isset($this->appDir) === true && is_dir($this->appDir) === true) {
			$this->rrmdir($this->appDir);
		}

		parent::tearDown();
	}//end tearDown()

	/**
	 * Recursively remove a directory tree.
	 *
	 * @param string $dir The directory to remove.
	 *
	 * @return void
	 */
	private function rrmdir(string $dir): void {
		foreach (scandir($dir) as $entry) {
			if ($entry === '.' || $entry === '..') {
				continue;
			}

			$path = $dir . '/' . $entry;
			if (is_dir($path) === true) {
				$this->rrmdir($path);
			} else {
				unlink($path);
			}
		}

		rmdir($dir);
	}//end rrmdir()

	/**
	 * Give the image manager one uploaded image per named slot.
	 *
	 * @param array<string, array{mime: string, contents: string}> $images The uploads, by slot.
	 *
	 * @return void
	 */
	private function uploads(array $images): void {
		foreach ($images as $key => $image) {
			$this->values['theming/' . $key . 'Mime'] = $image['mime'];
		}

		$this->imageManager->method('hasImage')->willReturnCallback(
			fn (string $key): bool => isset($images[$key])
		);
		$this->imageManager->method('getImage')->willReturnCallback(
			function (string $key) use ($images): ISimpleFile {
				$file = $this->createMock(ISimpleFile::class);
				$file->method('getContent')->willReturn($images[$key]['contents']);
				return $file;
			}
		);
	}//end uploads()

	/**
	 * The colours and a removed background image are captured, and stored
	 * under the set.
	 */
	public function testCapturesTheColoursAndARemovedBackground(): void {
		$this->values['theming/primary_color'] = '#23845c';
		$this->values['theming/background_color'] = '#ffffff';
		$this->values['theming/backgroundMime'] = 'backgroundColor';
		$this->uploads([]);

		$theming = $this->service->capture(setId: 'custom-openwoo');

		$this->assertSame(
			[
				'captured' => true,
				'primary_color' => '#23845c',
				'background_color' => '#ffffff',
				'background_mode' => 'color',
			],
			$theming
		);
		$this->assertSame(['custom-openwoo' => $theming], $this->service->all());
	}//end testCapturesTheColoursAndARemovedBackground()

	/**
	 * With no image of its own and nothing removed, the background is
	 * Nextcloud's default — which applying the theme must put back too.
	 */
	public function testAnUntouchedBackgroundIsCapturedAsTheDefault(): void {
		$this->uploads([]);

		$theming = $this->service->capture(setId: 'amsterdam');

		$this->assertSame('default', $theming['background_mode']);
	}//end testAnUntouchedBackgroundIsCapturedAsTheDefault()

	/**
	 * Every uploaded image is copied into the directory the sync accepts,
	 * under a name that cannot overwrite a shipped logo, and the slot is
	 * recorded as already holding it.
	 */
	public function testCopiesEveryUploadedImage(): void {
		$this->uploads(
			[
				'logo' => ['mime' => 'image/svg+xml', 'contents' => '<svg>logo</svg>'],
				'logoheader' => ['mime' => 'image/png', 'contents' => 'png-bytes'],
				'favicon' => ['mime' => 'image/x-icon', 'contents' => 'ico-bytes'],
				'background' => ['mime' => 'image/jpeg', 'contents' => 'jpeg-bytes'],
			]
		);

		$theming = $this->service->capture(setId: 'custom-openwoo');

		$this->assertSame('img/logos/custom-openwoo-captured-logo.svg', $theming['logo']);
		$this->assertSame('img/logos/custom-openwoo-captured-logoheader.png', $theming['logoheader']);
		$this->assertSame('img/logos/custom-openwoo-captured-favicon.ico', $theming['favicon']);
		$this->assertSame('img/backgrounds/custom-openwoo-captured-background.jpg', $theming['background']);
		$this->assertSame('image', $theming['background_mode']);

		$this->assertSame('<svg>logo</svg>', file_get_contents($this->appDir . '/' . $theming['logo']));
		$this->assertSame('jpeg-bytes', file_get_contents($this->appDir . '/' . $theming['background']));
		$this->assertSame($theming['logo'], $this->values['thematiq/synced_logo']);
	}//end testCopiesEveryUploadedImage()

	/**
	 * An image of a type core cannot take back, or one that cannot be read,
	 * is left out rather than failing the save it rides on.
	 */
	public function testAnImageThatCannotComeBackIsLeftOut(): void {
		$this->values['theming/logoMime'] = 'image/tiff';
		$this->values['theming/faviconMime'] = 'image/png';
		$this->imageManager->method('hasImage')->willReturnCallback(
			fn (string $key): bool => in_array($key, ['logo', 'favicon'], true)
		);
		$this->imageManager->method('getImage')->willThrowException(new \RuntimeException('unreadable'));

		$theming = $this->service->capture(setId: 'custom-openwoo');

		$this->assertArrayNotHasKey('logo', $theming);
		$this->assertArrayNotHasKey('favicon', $theming);
	}//end testAnImageThatCannotComeBackIsLeftOut()

	/**
	 * An unreadable store reads as nothing captured.
	 */
	public function testAnUnreadableStoreReadsAsEmpty(): void {
		$this->values['thematiq/' . BrandingCaptureService::CAPTURED_KEY] = 'not json';

		$this->assertSame([], $this->service->all());
	}//end testAnUnreadableStoreReadsAsEmpty()

	/**
	 * Forgetting a set removes its captured branding and its copied images.
	 */
	public function testForgetRemovesTheBrandingAndItsImages(): void {
		$this->uploads(['logo' => ['mime' => 'image/svg+xml', 'contents' => '<svg/>']]);
		$theming = $this->service->capture(setId: 'custom-openwoo');

		$this->service->forget(setId: 'custom-openwoo');

		$this->assertSame([], $this->service->all());
		$this->assertFileDoesNotExist($this->appDir . '/' . $theming['logo']);
	}//end testForgetRemovesTheBrandingAndItsImages()

	/**
	 * An image directory that cannot be created leaves the image out rather
	 * than failing the save: the colours are still captured.
	 */
	public function testAnUncreatableImageDirectoryLeavesTheImageOut(): void {
		// A file where img/ should be, so img/logos can never be made.
		file_put_contents($this->appDir . '/img', 'not a directory');
		$this->uploads(['logo' => ['mime' => 'image/svg+xml', 'contents' => '<svg/>']]);
		$this->values['theming/primary_color'] = '#23845c';

		$theming = $this->quietly(fn (): array => $this->service->capture(setId: 'custom-openwoo'));

		$this->assertArrayNotHasKey('logo', $theming);
		$this->assertSame('#23845c', $theming['primary_color']);
		$this->assertArrayNotHasKey('thematiq/synced_logo', $this->values);
	}//end testAnUncreatableImageDirectoryLeavesTheImageOut()

	/**
	 * An image that cannot be written is left out the same way.
	 */
	public function testAnUnwritableImageIsLeftOut(): void {
		// A directory where the copy should land, so writing it fails.
		mkdir($this->appDir . '/img/logos/custom-openwoo-captured-logo.svg', 0777, true);
		$this->uploads(['logo' => ['mime' => 'image/svg+xml', 'contents' => '<svg/>']]);

		$theming = $this->quietly(fn (): array => $this->service->capture(setId: 'custom-openwoo'));

		$this->assertArrayNotHasKey('logo', $theming);
		$this->assertArrayNotHasKey('thematiq/synced_logo', $this->values);
	}//end testAnUnwritableImageIsLeftOut()

	/**
	 * Run a callback with PHP warnings silenced: the filesystem failures
	 * these tests force are reported by PHP as warnings, and the service's
	 * answer to them is what is under test.
	 *
	 * @param callable $callback The code to run.
	 *
	 * @return mixed What the callback returned.
	 */
	private function quietly(callable $callback) {
		set_error_handler(static fn (): bool => true);
		try {
			return $callback();
		} finally {
			restore_error_handler();
		}
	}//end quietly()
}//end class
