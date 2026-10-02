<?php

/**
 * Unit tests for ConfigSourceService: apply the package named in config.php when it changed.
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
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\BrandingPackageReader;
use OCA\Thematiq\Service\BrandingPackageService;
use OCA\Thematiq\Service\ConfigBundleService;
use OCA\Thematiq\Service\ConfigSourceService;
use OCA\Thematiq\Service\ThemingAuditService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;
use OCP\Lock\ILockingProvider;
use OCP\Lock\LockedException;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Tests for the declarative configuration source.
 */
class ConfigSourceServiceTest extends TestCase {

	/**
	 * In-memory app config.
	 *
	 * @var array<string, string>
	 */
	private array $app = [];

	/**
	 * In-memory system config.
	 *
	 * @var array<string, mixed>
	 */
	private array $system = [];

	/**
	 * Audit entries written.
	 *
	 * @var array<int, array{0: string, 1: array<string, mixed>}>
	 */
	private array $audited = [];

	/**
	 * Error log lines.
	 *
	 * @var array<int, string>
	 */
	private array $errors = [];

	/**
	 * Packages imported.
	 *
	 * @var int
	 */
	private int $imports = 0;

	/**
	 * What the package import answers.
	 *
	 * @var array<string, mixed>
	 */
	private array $importResult = ['valid' => true, 'revision' => '3f2a9c1', 'hash' => 'sha256:x'];

	/**
	 * The running configuration the bundle export returns.
	 *
	 * @var array<string, mixed>
	 */
	private array $running = ['config' => ['tokenSet' => 'rijkshuisstijl']];

	/**
	 * Temporary package directory.
	 *
	 * @var string
	 */
	private string $dir;

	/**
	 * Whether the lock is held by someone else.
	 *
	 * @var bool
	 */
	private bool $lockBusy = false;

	/**
	 * Set up a package on disk.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->dir = sys_get_temp_dir() . '/thematiq-source-' . uniqid();
		mkdir($this->dir, 0777, true);
		file_put_contents($this->dir . '/bundle.json', '{"format":"nldesign-config-bundle"}');
		$this->system['thematiq.config_source'] = $this->dir;
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
	 * The service under test.
	 *
	 * @return ConfigSourceService The service.
	 */
	private function service(): ConfigSourceService {
		$config = $this->createMock(IConfig::class);
		$config->method('getSystemValueString')->willReturnCallback(fn (string $key, string $default = '') => (string)($this->system[$key] ?? $default));
		$config->method('getSystemValueBool')->willReturnCallback(fn (string $key, bool $default = false) => (bool)($this->system[$key] ?? $default));
		$config->method('getAppValue')->willReturnCallback(fn (string $app, string $key, $default = '') => ($this->app[$key] ?? $default));
		$config->method('setAppValue')->willReturnCallback(
			function (string $app, string $key, $value): void {
				$this->app[$key] = (string)$value;
			}
		);
		$config->method('deleteAppValue')->willReturnCallback(
			function (string $app, string $key): void {
				unset($this->app[$key]);
			}
		);

		$packages = $this->createMock(BrandingPackageService::class);
		$packages->method('import')->willReturnCallback(
			function (): array {
				$this->imports++;

				return $this->importResult;
			}
		);

		$bundles = $this->createMock(ConfigBundleService::class);
		$bundles->method('export')->willReturnCallback(fn (): array => $this->running + ['exportedAt' => uniqid()]);

		$audit = $this->createMock(ThemingAuditService::class);
		$audit->method('log')->willReturnCallback(
			function (string $action, array $context = []): void {
				$this->audited[] = [$action, $context];
			}
		);

		$locking = $this->createMock(ILockingProvider::class);
		$locking->method('acquireLock')->willReturnCallback(
			function (): void {
				if ($this->lockBusy === true) {
					throw new LockedException('thematiq-config-source');
				}
			}
		);

		$time = $this->createMock(ITimeFactory::class);
		$time->method('getTime')->willReturn(1790000000);

		$logger = $this->createMock(LoggerInterface::class);
		$logger->method('error')->willReturnCallback(
			function (string $message): void {
				$this->errors[] = $message;
			}
		);

		return new ConfigSourceService($config, new BrandingPackageReader(), $packages, $bundles, $audit, $locking, $time, $logger);
	}//end service()

	/**
	 * No source set: nothing happens.
	 *
	 * @return void
	 */
	public function testUnconfiguredDoesNothing(): void {
		unset($this->system['thematiq.config_source']);

		$this->assertSame('unconfigured', $this->service()->applyIfChanged()['status']);
		$this->assertSame(0, $this->imports);
		$this->assertSame(['managed' => false], $this->service()->getStatus());
	}//end testUnconfiguredDoesNothing()

	/**
	 * A changed, valid package is applied and audited as the system with the revision.
	 *
	 * @return void
	 */
	public function testChangedValidPackageIsAppliedAndAudited(): void {
		$result = $this->service()->applyIfChanged();

		$this->assertSame('applied', $result['status']);
		$this->assertSame(1, $this->imports);
		$this->assertSame('3f2a9c1', $this->app['config_source_applied_revision']);
		$this->assertCount(1, $this->audited);
		$this->assertSame('config_imported', $this->audited[0][0]);
		$this->assertSame('system', $this->audited[0][1]['actor']);
		$this->assertSame('deployment', $this->audited[0][1]['source']);
		$this->assertSame('3f2a9c1', $this->audited[0][1]['revision']);
	}//end testChangedValidPackageIsAppliedAndAudited()

	/**
	 * An unchanged package writes no configuration and no audit entry.
	 *
	 * @return void
	 */
	public function testUnchangedPackageIsNotAppliedAgain(): void {
		$this->service()->applyIfChanged();
		$before = $this->app;
		$this->audited = [];

		$result = $this->service()->applyIfChanged();

		$this->assertSame('unchanged', $result['status']);
		$this->assertSame(1, $this->imports);
		$this->assertSame($before, $this->app);
		$this->assertSame([], $this->audited);
	}//end testUnchangedPackageIsNotAppliedAgain()

	/**
	 * A changed, invalid package changes nothing, records the error, and logs once per hash.
	 *
	 * @return void
	 */
	public function testChangedInvalidPackageChangesNothingAndLogsOnce(): void {
		$this->importResult = ['valid' => false, 'errors' => [['section' => 'customTokenSets', 'id' => 'custom-gemeente', 'message' => 'Token not allowed.']]];

		$first = $this->service()->applyIfChanged();
		$this->service()->applyIfChanged();

		$this->assertSame('failed', $first['status']);
		$this->assertArrayNotHasKey('config_source_applied_hash', $this->app);
		$this->assertSame([], $this->audited);
		$this->assertCount(1, $this->errors, 'One log line per distinct package hash.');
		$status = $this->service()->getStatus();
		$this->assertSame('custom-gemeente', $status['lastError']['errors'][0]['id']);

		file_put_contents($this->dir . '/REVISION', 'next');
		$this->service()->applyIfChanged();
		$this->assertCount(2, $this->errors, 'A new package hash logs again.');
	}//end testChangedInvalidPackageChangesNothingAndLogsOnce()

	/**
	 * A path that does not exist fails without an import.
	 *
	 * @return void
	 */
	public function testMissingPathFails(): void {
		$this->system['thematiq.config_source'] = $this->dir . '/nope';

		$result = $this->service()->applyIfChanged();

		$this->assertSame('failed', $result['status']);
		$this->assertSame('package', $result['errors'][0]['section']);
		$this->assertSame(0, $this->imports);
	}//end testMissingPathFails()

	/**
	 * Another replica holding the lock: nothing is applied.
	 *
	 * @return void
	 */
	public function testBusyLockSkips(): void {
		$this->lockBusy = true;

		$this->assertSame('busy', $this->service()->applyIfChanged()['status']);
		$this->assertSame(0, $this->imports);
	}//end testBusyLockSkips()

	/**
	 * Status names the path and revision, and shows drift after a web change.
	 *
	 * @return void
	 */
	public function testStatusShowsRevisionAndDrift(): void {
		$this->system['thematiq.config_source_lock'] = true;
		$this->service()->applyIfChanged();

		$status = $this->service()->getStatus();
		$this->assertTrue($status['managed']);
		$this->assertSame($this->dir, $status['path']);
		$this->assertSame('3f2a9c1', $status['revision']);
		$this->assertTrue($status['locked']);
		$this->assertFalse($status['drift']);

		$this->running['config']['tokenSet'] = 'amsterdam';
		$this->assertTrue($this->service()->getStatus()['drift']);
	}//end testStatusShowsRevisionAndDrift()

	/**
	 * The lock only counts while a source is set.
	 *
	 * @return void
	 */
	public function testLockNeedsASource(): void {
		$this->system['thematiq.config_source_lock'] = true;
		$this->assertTrue($this->service()->isLocked());

		unset($this->system['thematiq.config_source']);
		$this->assertFalse($this->service()->isLocked());
	}//end testLockNeedsASource()
}//end class
