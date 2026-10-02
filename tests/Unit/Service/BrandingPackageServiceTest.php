<?php

/**
 * Unit tests for BrandingPackageService and BrandingPackageReader.
 *
 * The reader, the font validator and the DTCG converter are the real classes; the
 * package is written to and read from a temporary directory and a real ZIP.
 *
 * @category Test
 * @package  OCA\Thematiq\Tests\Unit\Service
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/specs/theme-as-code/spec.md
 * @spec openspec/specs/config-portability/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\BrandingPackageReader;
use OCA\Thematiq\Service\BrandingPackageService;
use OCA\Thematiq\Service\ConfigBundleService;
use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\CustomTokenSetService;
use OCA\Thematiq\Service\DesignTokensMapper;
use OCA\Thematiq\Service\FontService;
use OCA\Thematiq\Service\FontValidator;
use OCA\Thematiq\Service\TokenSetConverterService;
use OCP\App\IAppManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ZipArchive;

/**
 * Tests for branding packages.
 */
class BrandingPackageServiceTest extends TestCase {

	/**
	 * Temporary working directory.
	 *
	 * @var string
	 */
	private string $dir;

	/**
	 * The bundle service double.
	 *
	 * @var ConfigBundleService&MockObject
	 */
	private $bundleService;

	/**
	 * The font service double.
	 *
	 * @var FontService&MockObject
	 */
	private $fontService;

	/**
	 * Bundles passed to the bundle import, with their dry-run flag.
	 *
	 * @var array<int, array{0: array<string, mixed>, 1: bool}>
	 */
	private array $imported = [];

	/**
	 * Fonts stored, as [name, role, bytes].
	 *
	 * @var array<int, array{0: string, 1: string, 2: string}>
	 */
	private array $stored = [];

	/**
	 * The font manifest of the running server.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private array $manifest = [];

	/**
	 * Set up the doubles on a temporary directory.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->dir = sys_get_temp_dir() . '/thematiq-package-' . uniqid();
		mkdir($this->dir, 0777, true);

		$this->manifest = [
			'custom-corporate' => ['name' => 'Corporate', 'role' => 'heading', 'size' => 8],
			'custom-body-text' => ['name' => 'Body Text', 'role' => 'body', 'size' => 8],
		];

		$this->bundleService = $this->createMock(ConfigBundleService::class);
		$this->bundleService->method('export')->willReturnCallback(fn (): array => $this->bundle());
		$this->bundleService->method('import')->willReturnCallback(
			function (array $bundle, bool $dryRun = false): array {
				$this->imported[] = [$bundle, $dryRun];

				return ['valid' => true, 'dryRun' => $dryRun, 'applied' => ($dryRun === false), 'sections' => ['config' => []]];
			}
		);

		$this->fontService = $this->createMock(FontService::class);
		$this->fontService->method('getManifest')->willReturnCallback(fn (): array => $this->manifest);
		$this->fontService->method('readFontBytes')->willReturnCallback(fn (string $id): string => $this->fontBytes($id));
		$this->fontService->method('slugify')->willReturnCallback(
			fn (string $name): string => trim((string)preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-')
		);
		$this->fontService->method('store')->willReturnCallback(
			function (string $displayName, string $role, string $bytes, int $reportedSize): array {
				$this->stored[] = [$displayName, $role, $bytes];

				return ['id' => 'custom-' . $displayName];
			}
		);
	}//end setUp()

	/**
	 * Remove the temporary directory.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		exec('rm -rf ' . escapeshellarg($this->dir));
		parent::tearDown();
	}//end tearDown()

	/**
	 * A woff2-looking payload.
	 *
	 * @param string $id The font id.
	 *
	 * @return string The bytes.
	 */
	private function fontBytes(string $id): string {
		return 'wOF2' . str_pad($id, 32, '.');
	}//end fontBytes()

	/**
	 * The exported bundle.
	 *
	 * @return array<string, mixed> The bundle.
	 */
	private function bundle(): array {
		return [
			'format' => ConfigBundleService::FORMAT,
			'bundleVersion' => ConfigBundleService::BUNDLE_VERSION,
			'config' => ['tokenSet' => 'custom-gemeente'],
			'customTokenSets' => [
				['id' => 'custom-gemeente', 'name' => 'Gemeente', 'css' => ":root {\n  --nldesign-color-primary: #123456;\n}\n"],
			],
			'customFonts' => ['binariesIncluded' => false, 'manifest' => $this->manifest],
		];
	}//end bundle()

	/**
	 * The service under test, with the real reader, validator and converter.
	 *
	 * @return BrandingPackageService The service.
	 */
	private function service(): BrandingPackageService {
		$repoApp = $this->createMock(IAppManager::class);
		$repoApp->method('getAppPath')->willReturn(\dirname(__DIR__, 3));
		$converter = new TokenSetConverterService(
			$repoApp,
			new CssParserService(),
			new ContrastService(),
			new DesignTokensMapper(),
			$this->createMock(FontService::class),
			$this->createMock(LoggerInterface::class)
		);
		$customSets = $this->createMock(CustomTokenSetService::class);
		$customSets->method('isCustomId')->willReturnCallback(
			fn (string $id): bool => preg_match('/^custom-[a-z0-9][a-z0-9-]*$/', $id) === 1
		);

		return new BrandingPackageService(
			new BrandingPackageReader(),
			$this->bundleService,
			$this->fontService,
			new FontValidator(),
			$converter,
			$customSets
		);
	}//end service()

	/**
	 * Export writes bundle.json and both fonts; importing the directory and its ZIP
	 * converts the DTCG source and stores both fonts.
	 *
	 * @return void
	 */
	public function testPackageRoundTripsWithTwoFontsAndOneDtcgSource(): void {
		$package = $this->dir . '/branding';
		$written = $this->service()->export(dir: $package);

		$this->assertContains('bundle.json', $written);
		$this->assertFileExists($package . '/fonts/custom-corporate.woff2');
		$this->assertFileExists($package . '/fonts/custom-body-text.woff2');
		$exported = json_decode((string)file_get_contents($package . '/bundle.json'), true);
		$this->assertTrue($exported['customFonts']['binariesIncluded']);

		mkdir($package . '/tokens');
		copy(\dirname(__DIR__) . '/fixtures/dtcg/14-utrecht-real-excerpt.tokens.json', $package . '/tokens/custom-gemeente.json');
		file_put_contents($package . '/REVISION', "3f2a9c1\n");

		$zipPath = $this->dir . '/branding.zip';
		$zip = new ZipArchive();
		$zip->open($zipPath, ZipArchive::CREATE);
		foreach (['bundle.json', 'REVISION', 'fonts/custom-corporate.woff2', 'fonts/custom-body-text.woff2', 'tokens/custom-gemeente.json'] as $file) {
			$zip->addFile($package . '/' . $file, 'branding-main/' . $file);
		}

		$zip->close();

		foreach ([$package, $zipPath] as $source) {
			$this->imported = [];
			$this->stored = [];
			$this->assertTrue($this->service()->isPackage(path: $source));

			$result = $this->service()->import(path: $source);

			$this->assertTrue($result['valid'], json_encode($result['errors'] ?? []));
			$this->assertSame('3f2a9c1', $result['revision']);
			$this->assertSame(2, $result['sections']['customFonts']['count']);
			$this->assertCount(2, $this->stored);
			$this->assertSame(['Corporate', 'heading', $this->fontBytes('custom-corporate')], $this->stored[0]);

			$applied = end($this->imported);
			$this->assertFalse($applied[1], 'The last bundle import applies.');
			$set = $applied[0]['customTokenSets'][0];
			$this->assertSame('custom-gemeente', $set['id']);
			$this->assertSame('Gemeente', $set['name']);
			$this->assertStringNotContainsString('#123456', $set['css'], 'The DTCG source replaced the bundle CSS.');
			$this->assertStringContainsString('--nldesign-', $set['css']);
		}
	}//end testPackageRoundTripsWithTwoFontsAndOneDtcgSource()

	/**
	 * A manifest entry without its file is refused whole: no bundle apply, no font stored.
	 *
	 * @return void
	 */
	public function testMissingFontFileRefusesThePackageWhole(): void {
		$package = $this->dir . '/branding';
		$this->service()->export(dir: $package);
		unlink($package . '/fonts/custom-corporate.woff2');

		$result = $this->service()->import(path: $package);

		$this->assertFalse($result['valid']);
		$this->assertStringContainsString('fonts/custom-corporate.woff2', json_encode($result['errors'], JSON_UNESCAPED_SLASHES));
		$this->assertSame([], $this->stored);
		foreach ($this->imported as $call) {
			$this->assertTrue($call[1], 'Only dry runs may reach the bundle service.');
		}
	}//end testMissingFontFileRefusesThePackageWhole()

	/**
	 * A font file that is not woff2 is refused with the validator's message.
	 *
	 * @return void
	 */
	public function testFontFileThatIsNotWoff2IsRefused(): void {
		$package = $this->dir . '/branding';
		$this->service()->export(dir: $package);
		file_put_contents($package . '/fonts/custom-corporate.woff2', 'not a font');

		$result = $this->service()->import(path: $package);

		$this->assertFalse($result['valid']);
		$this->assertSame('customFonts', $result['errors'][0]['section']);
		$this->assertSame([], $this->stored);
	}//end testFontFileThatIsNotWoff2IsRefused()

	/**
	 * A DTCG source that cannot be converted fails the package.
	 *
	 * @return void
	 */
	public function testUnconvertibleTokenSourceRefusesThePackage(): void {
		$package = $this->dir . '/branding';
		$this->service()->export(dir: $package);
		mkdir($package . '/tokens');
		file_put_contents($package . '/tokens/custom-gemeente.json', '{"not": "tokens"}');

		$result = $this->service()->import(path: $package);

		$this->assertFalse($result['valid']);
		$this->assertSame('tokens', $result['errors'][0]['section']);
		$this->assertSame([], $this->stored);
	}//end testUnconvertibleTokenSourceRefusesThePackage()

	/**
	 * A dry run validates fonts and writes nothing.
	 *
	 * @return void
	 */
	public function testDryRunStoresNothing(): void {
		$package = $this->dir . '/branding';
		$this->service()->export(dir: $package);

		$result = $this->service()->import(path: $package, dryRun: true);

		$this->assertTrue($result['valid']);
		$this->assertFalse($result['applied']);
		$this->assertSame([], $this->stored);
		$this->assertCount(1, $this->imported);
		$this->assertTrue($this->imported[0][1]);
	}//end testDryRunStoresNothing()

	/**
	 * A bare bundle file is not a package, so the import keeps its old font behaviour.
	 *
	 * @return void
	 */
	public function testBareBundleFileIsNotAPackage(): void {
		$file = $this->dir . '/bundle.json';
		file_put_contents($file, json_encode($this->bundle()));

		$this->assertFalse($this->service()->isPackage(path: $file));
		$this->assertFalse($this->service()->isPackage(path: $this->dir . '/missing'));
	}//end testBareBundleFileIsNotAPackage()

	/**
	 * The hash ignores file order and changes with any content.
	 *
	 * @return void
	 */
	public function testHashChangesWithContentOnly(): void {
		$package = $this->dir . '/branding';
		$this->service()->export(dir: $package);
		$reader = new BrandingPackageReader();

		$first = $reader->hash(path: $package);
		$this->assertSame($first, $reader->hash(path: $package));

		file_put_contents($package . '/REVISION', 'abc');
		$this->assertNotSame($first, $reader->hash(path: $package));
	}//end testHashChangesWithContentOnly()
}//end class
