<?php

/**
 * Unit tests for the thematiq:config:apply command on a package with a bad token value.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/theme-as-code/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Command;

use OCA\Thematiq\Command\ConfigApply;
use OCA\Thematiq\Service\BrandingPackageReader;
use OCA\Thematiq\Service\BrandingPackageService;
use OCA\Thematiq\Service\ConfigBundleService;
use OCA\Thematiq\Service\ConfigSourceClock;
use OCA\Thematiq\Service\ConfigSourceLock;
use OCA\Thematiq\Service\ConfigSourceService;
use OCA\Thematiq\Service\ConfigSourceSteps;
use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\CustomTokenSetService;
use OCA\Thematiq\Service\DesignTokensMapper;
use OCA\Thematiq\Service\DtcgValueChecker;
use OCA\Thematiq\Service\FontService;
use OCA\Thematiq\Service\FontValidator;
use OCA\Thematiq\Service\PackageTokenSourceConverter;
use OCA\Thematiq\Service\ThemingAuditService;
use OCA\Thematiq\Service\TokenSetConverterService;
use OCA\Thematiq\Service\TokenValueValidator;
use OCP\App\IAppManager;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\Lock\ILockingProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Issue #941: `occ thematiq:config:apply` exited 0 on a package whose DTCG
 * source set `nldesign.color.primary` to "not-a-colour", and the set served
 * that value. The command runs here on the real source, package, reader,
 * converter and validator; only Nextcloud's storage and the bundle apply are
 * doubles, so the test sees what the deployment pipeline sees.
 */
class ConfigApplyTest extends TestCase {

	/**
	 * The package directory.
	 *
	 * @var string
	 */
	private string $dir;

	/**
	 * Bundles applied for real (dry runs excluded).
	 *
	 * @var int
	 */
	private int $applied = 0;

	/**
	 * Write a package with one DTCG source.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->dir = sys_get_temp_dir() . '/thematiq-941-' . uniqid();
		mkdir($this->dir . '/tokens', 0777, true);
		file_put_contents(
			$this->dir . '/bundle.json',
			(string)json_encode(
				[
					'format' => ConfigBundleService::FORMAT,
					'bundleVersion' => ConfigBundleService::BUNDLE_VERSION,
					'config' => ['tokenSet' => 'custom-gemeente'],
					'customTokenSets' => [],
				]
			)
		);
	}//end setUp()

	/**
	 * Remove the package.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		exec('rm -rf ' . escapeshellarg($this->dir));
		parent::tearDown();
	}//end tearDown()

	/**
	 * Run the command against the package with the given primary colour.
	 *
	 * @param string $primary The `$value` of nldesign.color.primary.
	 *
	 * @return CommandTester The finished run.
	 */
	private function applyPackage(string $primary): CommandTester {
		file_put_contents(
			$this->dir . '/tokens/custom-gemeente.json',
			(string)json_encode(['nldesign' => ['color' => ['$type' => 'color', 'primary' => ['$value' => $primary]]]])
		);

		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('getAppPath')->willReturn(\dirname(__DIR__, 3));
		$converter = new TokenSetConverterService(
			appManager: $appManager,
			cssParser: new CssParserService(),
			contrast: new ContrastService(),
			dtcgMapper: new DesignTokensMapper(),
			fonts: $this->createMock(originalClassName: FontService::class),
			logger: $this->createMock(originalClassName: LoggerInterface::class)
		);
		$customSets = $this->createMock(originalClassName: CustomTokenSetService::class);
		$customSets->method('isCustomId')->willReturnCallback(fn (string $id): bool => str_starts_with($id, 'custom-'));
		$fonts = $this->createMock(originalClassName: FontService::class);
		$fonts->method('getManifest')->willReturn([]);
		$bundles = $this->createMock(originalClassName: ConfigBundleService::class);
		$bundles->method('export')->willReturn([]);
		$bundles->method('import')->willReturnCallback(
			function (array $bundle, bool $dryRun = false): array {
				if ($dryRun === false) {
					$this->applied++;
				}

				return ['valid' => true, 'dryRun' => $dryRun, 'applied' => ($dryRun === false), 'sections' => []];
			}
		);
		$packages = new BrandingPackageService(
			reader: new BrandingPackageReader(),
			bundleService: $bundles,
			fontService: $fonts,
			fontValidator: new FontValidator(),
			tokenSources: new PackageTokenSourceConverter(
				converter: $converter,
				customSets: $customSets,
				values: new DtcgValueChecker(values: new TokenValueValidator())
			)
		);

		$app = [];
		$config = $this->createMock(originalClassName: IConfig::class);
		$config->method('getSystemValueString')->willReturnCallback(
			fn (string $key, string $default = ''): string => $key === 'thematiq.config_source' ? $this->dir : $default
		);
		$config->method('getAppValue')->willReturnCallback(fn (string $appId, string $key, $default = '') => ($app[$key] ?? $default));
		$config->method('setAppValue')->willReturnCallback(
			function (string $appId, string $key, $value) use (&$app): void {
				$app[$key] = (string)$value;
			}
		);
		$time = $this->createMock(originalClassName: ITimeFactory::class);
		$time->method('getTime')->willReturn(1790000000);

		$source = new ConfigSourceService(
			config: $config,
			steps: new ConfigSourceSteps(
				reader: new BrandingPackageReader(),
				packageService: $packages,
				bundleService: $bundles,
				audit: $this->createMock(originalClassName: ThemingAuditService::class)
			),
			lock: new ConfigSourceLock(
				locking: $this->createMock(originalClassName: ILockingProvider::class),
				appConfig: $this->createMock(originalClassName: IAppConfig::class)
			),
			clock: new ConfigSourceClock(
				time: $time,
				logger: $this->createMock(originalClassName: LoggerInterface::class)
			)
		);

		$tester = new CommandTester(new ConfigApply(source: $source));
		$tester->execute([]);

		return $tester;
	}//end applyPackage()

	/**
	 * The exact value from the issue: refused, non-zero, the token named, nothing applied.
	 *
	 * @return void
	 */
	public function testAnInvalidColourExitsNonZeroNamingTheToken(): void {
		$tester = $this->applyPackage(primary: 'not-a-colour');

		$this->assertSame(expected: 1, actual: $tester->getStatusCode());
		$this->assertStringContainsString(needle: 'nldesign.color.primary', haystack: $tester->getDisplay());
		$this->assertStringContainsString(needle: 'not-a-colour', haystack: $tester->getDisplay());
		$this->assertSame(expected: 0, actual: $this->applied);
	}//end testAnInvalidColourExitsNonZeroNamingTheToken()

	/**
	 * Control: a real colour applies and exits 0.
	 *
	 * @return void
	 */
	public function testAValidColourApplies(): void {
		$tester = $this->applyPackage(primary: '#8a2be2');

		$this->assertSame(expected: 0, actual: $tester->getStatusCode(), message: $tester->getDisplay());
		$this->assertSame(expected: 1, actual: $this->applied);
	}//end testAValidColourApplies()
}//end class
