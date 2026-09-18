<?php

/**
 * Unit tests for CustomTokenSetService's logo asset: the one file an import
 * writes outside css/tokens/.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/custom-token-sets/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\CustomTokenSetService;
use OCA\Thematiq\Service\CustomTokenSetValidator;
use OCA\Thematiq\Service\DarkPaletteService;
use OCP\App\IAppManager;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * An imported theme can carry a wordmark, and storing it is the only place
 * this app writes a file whose bytes came out of an uploaded document into a
 * directory that is served as static content.
 *
 * Two things have to hold, and both are about the ORDER of two side effects
 * rather than about either one on its own:
 *
 *  - the file is written BEFORE the manifest entry names it, because
 *    `theming.logo` is validated by `ThemingService` against the file
 *    existing. A manifest naming a file that was never written does not fail
 *    at import; it fails later, at sync time, as "Image file not found";
 *  - a logo that could NOT be written leaves no `theming.logo` at all, rather
 *    than a key pointing at nothing. The import still succeeds — a theme
 *    without a usable wordmark is still a usable theme.
 *
 * The extension allowlist is the same list Nextcloud's own ImageManager takes,
 * and it is applied to the path the converter proposed rather than to the
 * bytes: the converter has already decided the type from the data URI's mime.
 *
 * @spec openspec/specs/custom-token-sets/spec.md
 */
class CustomTokenSetLogoAssetTest extends TestCase {

	/**
	 * The temp app directory the service writes into.
	 *
	 * @var string
	 */
	private string $appDir;

	/**
	 * In-memory appconfig store.
	 *
	 * @var array<string, string>
	 */
	private array $appConfig = [];

	/**
	 * The service under test.
	 *
	 * @var CustomTokenSetService
	 */
	private CustomTokenSetService $service;

	/**
	 * Build the service over a temp app directory.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->appDir = sys_get_temp_dir() . '/thematiq-logo-asset-test-' . uniqid();
		mkdir($this->appDir . '/css/tokens', 0777, true);

		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getAppPath')->willReturn($this->appDir);

		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			fn (string $app, string $key, $default = '') => ($this->appConfig[$key] ?? $default)
		);
		$config->method('setAppValue')->willReturnCallback(
			function (string $app, string $key, $value): void {
				$this->appConfig[$key] = $value;
			}
		);

		$this->service = new CustomTokenSetService(
			$appManager,
			$config,
			new CustomTokenSetValidator(),
			new ContrastService(),
			new DarkPaletteService(
				new ContrastService(),
				new CssParserService(),
				$appManager,
				$this->createMock(LoggerInterface::class)
			)
		);
	}//end setUp()

	/**
	 * Remove the temp app directory.
	 */
	protected function tearDown(): void {
		$this->rrmdir($this->appDir);
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
		if (is_dir($dir) === false) {
			return;
		}

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
	 * Store a set carrying the given logo asset.
	 *
	 * @param array<string, mixed>|null $logoAsset The decoded logo, or null.
	 * @param string                    $name      The display name.
	 *
	 * @return array<string, mixed> The store result.
	 */
	private function store(?array $logoAsset, string $name = 'Gemeente Voorbeeld'): array {
		return $this->service->store(
			displayName: $name,
			description: '',
			declarations: ['--nldesign-color-primary' => '#154273'],
			logoAsset: $logoAsset
		);
	}//end store()

	/**
	 * The manifest entry the service persisted for an id.
	 *
	 * @param string $id The set id.
	 *
	 * @return array<string, mixed> The manifest entry.
	 */
	private function manifestEntry(string $id): array {
		$manifest = json_decode(($this->appConfig['custom_token_sets'] ?? '{}'), true);

		return ($manifest[$id] ?? []);
	}//end manifestEntry()

	/**
	 * The wordmark lands under the SET's id — never the bare slug — so an
	 * import called "Amsterdam" cannot overwrite the shipped
	 * `img/logos/amsterdam.svg`, and the manifest names the file that is now
	 * actually on disk.
	 *
	 * @spec openspec/specs/custom-token-sets/spec.md
	 */
	public function testTheLogoIsWrittenUnderTheSetIdAndNamedInTheManifest(): void {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg"/>';

		$result = $this->store(['path' => 'img/logos/custom-gemeente-voorbeeld.svg', 'contents' => $svg]);

		$expected = 'img/logos/custom-gemeente-voorbeeld.svg';
		$this->assertSame('custom-gemeente-voorbeeld', $result['id']);
		$this->assertFileExists($this->appDir . '/' . $expected);
		$this->assertSame($svg, file_get_contents($this->appDir . '/' . $expected));
		$this->assertSame($expected, $this->manifestEntry($result['id'])['theming']['logo']);
	}//end testTheLogoIsWrittenUnderTheSetIdAndNamedInTheManifest()

	/**
	 * `img/logos/` is created when it is not there — a fresh checkout has no
	 * reason to carry it, and a missing directory must not turn an import
	 * into a failure.
	 *
	 * @spec openspec/specs/custom-token-sets/spec.md
	 */
	public function testTheLogoDirectoryIsCreatedOnDemand(): void {
		$this->assertDirectoryDoesNotExist($this->appDir . '/img/logos');

		$this->store(['path' => 'img/logos/custom-gemeente-voorbeeld.svg', 'contents' => '<svg/>']);

		$this->assertDirectoryExists($this->appDir . '/img/logos');
	}//end testTheLogoDirectoryIsCreatedOnDemand()

	/**
	 * The extension is taken from the path the converter proposed, and the
	 * written file carries it — the file is served by name, so a mismatch
	 * would hand the browser the wrong content type.
	 *
	 * @param string $path      The proposed asset path.
	 * @param string $extension The extension the file must end up with.
	 *
	 * @dataProvider acceptedExtensionProvider
	 *
	 * @spec openspec/specs/custom-token-sets/spec.md
	 */
	public function testAcceptedExtensionsAreWrittenUnderTheirOwnName(string $path, string $extension): void {
		$result = $this->store(['path' => $path, 'contents' => 'bytes']);

		$this->assertSame(
			'img/logos/custom-gemeente-voorbeeld.' . $extension,
			$this->manifestEntry($result['id'])['theming']['logo']
		);
		$this->assertFileExists($this->appDir . '/img/logos/custom-gemeente-voorbeeld.' . $extension);
	}//end testAcceptedExtensionsAreWrittenUnderTheirOwnName()

	/**
	 * The image types Nextcloud's own ImageManager takes.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function acceptedExtensionProvider(): array {
		return [
			'svg' => ['img/logos/x.svg', 'svg'],
			'png' => ['img/logos/x.png', 'png'],
			'jpg' => ['img/logos/x.jpg', 'jpg'],
			'gif' => ['img/logos/x.gif', 'gif'],
			'webp' => ['img/logos/x.webp', 'webp'],
			'uppercase is normalised' => ['img/logos/x.PNG', 'png'],
		];
	}//end acceptedExtensionProvider()

	/**
	 * An extension outside the allowlist writes nothing AND leaves no
	 * `theming.logo`. A key pointing at a file that was never written is worse
	 * than no key: it fails later, during the core theming sync, as "Image
	 * file not found" — a message that names the wrong step.
	 *
	 * @param string $path The proposed asset path.
	 *
	 * @dataProvider rejectedExtensionProvider
	 *
	 * @spec openspec/specs/custom-token-sets/spec.md
	 */
	public function testARejectedExtensionLeavesNoLogoAtAll(string $path): void {
		$result = $this->store(['path' => $path, 'contents' => 'bytes']);

		$this->assertArrayNotHasKey('logo', $this->manifestEntry($result['id'])['theming']);
		$this->assertDirectoryDoesNotExist($this->appDir . '/img/logos');
	}//end testARejectedExtensionLeavesNoLogoAtAll()

	/**
	 * Proposed paths whose extension is not a storable image.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function rejectedExtensionProvider(): array {
		return [
			'not an image type core takes' => ['img/logos/x.tiff'],
			'executable' => ['img/logos/x.php'],
			'no extension at all' => ['img/logos/x'],
			'empty path' => [''],
		];
	}//end rejectedExtensionProvider()

	/**
	 * A set imported without a logo is stored exactly as before: the asset
	 * step is skipped entirely rather than writing an empty file.
	 *
	 * @spec openspec/specs/custom-token-sets/spec.md
	 */
	public function testASetWithoutALogoWritesNothingExtra(): void {
		$result = $this->store(null);

		$this->assertArrayNotHasKey('logo', $this->manifestEntry($result['id'])['theming']);
		$this->assertDirectoryDoesNotExist($this->appDir . '/img/logos');
	}//end testASetWithoutALogoWritesNothingExtra()
}//end class
