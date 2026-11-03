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
use OCA\Thematiq\Service\ConfigSourceClock;
use OCA\Thematiq\Service\ConfigSourceLock;
use OCA\Thematiq\Service\ConfigSourceService;
use OCA\Thematiq\Service\ConfigSourceSteps;
use OCA\Thematiq\Service\ThemingAuditService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IAppConfig;
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
	 * Run while the lock is taken, as another replica finishing its apply.
	 *
	 * @var callable|null
	 */
	private $whileWaitingForTheLock = null;

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
		$config = $this->createMock(originalClassName: IConfig::class);
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

		$packages = $this->createMock(originalClassName: BrandingPackageService::class);
		$packages->method('import')->willReturnCallback(
			function (): array {
				$this->imports++;

				return $this->importResult;
			}
		);

		$bundles = $this->createMock(originalClassName: ConfigBundleService::class);
		$bundles->method('export')->willReturnCallback(fn (): array => $this->running + ['exportedAt' => uniqid()]);

		$audit = $this->createMock(originalClassName: ThemingAuditService::class);
		$audit->method('log')->willReturnCallback(
			function (string $action, array $context = []): void {
				$this->audited[] = [$action, $context];
			}
		);

		$locking = $this->createMock(originalClassName: ILockingProvider::class);
		$locking->method('acquireLock')->willReturnCallback(
			function (): void {
				if ($this->lockBusy === true) {
					throw new LockedException('thematiq-config-source');
				}

				if ($this->whileWaitingForTheLock !== null) {
					($this->whileWaitingForTheLock)();
				}
			}
		);

		$time = $this->createMock(originalClassName: ITimeFactory::class);
		$time->method('getTime')->willReturn(1790000000);

		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		$logger->method('error')->willReturnCallback(
			function (string $message): void {
				$this->errors[] = $message;
			}
		);

		return new ConfigSourceService(
			config: $config,
			steps: new ConfigSourceSteps(
				reader: new BrandingPackageReader(),
				packageService: $packages,
				bundleService: $bundles,
				audit: $audit
			),
			lock: new ConfigSourceLock(
				locking: $locking,
				appConfig: $this->createMock(originalClassName: IAppConfig::class)
			),
			clock: new ConfigSourceClock(
				time: $time,
				logger: $logger
			)
		);
	}//end service()

	/**
	 * No source set: nothing happens.
	 *
	 * @return void
	 */
	public function testUnconfiguredDoesNothing(): void {
		unset($this->system['thematiq.config_source']);

		$this->assertSame(expected: 'unconfigured', actual: $this->service()->applyIfChanged()['status']);
		$this->assertSame(expected: 0, actual: $this->imports);
		$this->assertSame(expected: ['managed' => false], actual: $this->service()->getStatus());
	}//end testUnconfiguredDoesNothing()

	/**
	 * A changed, valid package is applied and audited as the system with the revision.
	 *
	 * @return void
	 */
	public function testChangedValidPackageIsAppliedAndAudited(): void {
		$result = $this->service()->applyIfChanged();

		$this->assertSame(expected: 'applied', actual: $result['status']);
		$this->assertSame(expected: 1, actual: $this->imports);
		$this->assertSame(expected: '3f2a9c1', actual: $this->app['config_source_applied_revision']);
		$this->assertSame(expected: '1790000000', actual: $this->app['config_source_applied_at']);
		$this->assertCount(expectedCount: 1, haystack: $this->audited);
		$this->assertSame(expected: 'config_imported', actual: $this->audited[0][0]);
		$this->assertSame(expected: 'system', actual: $this->audited[0][1]['actor']);
		$this->assertSame(expected: 'deployment', actual: $this->audited[0][1]['source']);
		$this->assertSame(expected: '3f2a9c1', actual: $this->audited[0][1]['revision']);
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

		$this->assertSame(expected: 'unchanged', actual: $result['status']);
		$this->assertSame(expected: 1, actual: $this->imports);
		$this->assertSame(expected: $before, actual: $this->app);
		$this->assertSame(expected: [], actual: $this->audited);
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

		$this->assertSame(expected: 'failed', actual: $first['status']);
		$this->assertArrayNotHasKey(key: 'config_source_applied_hash', array: $this->app);
		$this->assertSame(expected: [], actual: $this->audited);
		$this->assertCount(expectedCount: 1, haystack: $this->errors, message: 'One log line per distinct package hash.');
		$status = $this->service()->getStatus();
		$this->assertSame(expected: 'custom-gemeente', actual: $status['lastError']['errors'][0]['id']);
		$this->assertSame(expected: gmdate(format: 'c', timestamp: 1790000000), actual: $status['lastError']['at']);

		file_put_contents($this->dir . '/REVISION', 'next');
		$this->service()->applyIfChanged();
		$this->assertCount(expectedCount: 2, haystack: $this->errors, message: 'A new package hash logs again.');
	}//end testChangedInvalidPackageChangesNothingAndLogsOnce()

	/**
	 * Reverting a broken package to the one already applied clears its error.
	 *
	 * @return void
	 */
	public function testRevertingToTheAppliedPackageClearsTheError(): void {
		$this->service()->applyIfChanged();

		file_put_contents($this->dir . '/REVISION', 'broken');
		$this->importResult = ['valid' => false, 'errors' => [['section' => 'package', 'message' => 'Broken.']]];
		$this->service()->applyIfChanged();
		$this->assertNotNull(actual: $this->service()->getStatus()['lastError']);

		unlink($this->dir . '/REVISION');
		$result = $this->service()->applyIfChanged();

		$this->assertSame(expected: 'unchanged', actual: $result['status']);
		$this->assertNull(actual: $this->service()->getStatus()['lastError']);
	}//end testRevertingToTheAppliedPackageClearsTheError()

	/**
	 * A package another replica applied while this one waited for the lock is
	 * not applied a second time.
	 *
	 * @return void
	 */
	public function testAPackageAppliedWhileWaitingForTheLockIsNotAppliedAgain(): void {
		$hash = (new BrandingPackageReader())->hash(path: $this->dir);
		$this->whileWaitingForTheLock = function () use ($hash): void {
			$this->app['config_source_applied_hash'] = $hash;
		};

		$result = $this->service()->applyIfChanged();

		$this->assertSame(expected: 'unchanged', actual: $result['status']);
		$this->assertSame(expected: 0, actual: $this->imports);
		$this->assertSame(expected: [], actual: $this->audited);
	}//end testAPackageAppliedWhileWaitingForTheLockIsNotAppliedAgain()

	/**
	 * A path that does not exist fails without an import.
	 *
	 * @return void
	 */
	public function testMissingPathFails(): void {
		$this->system['thematiq.config_source'] = $this->dir . '/nope';

		$result = $this->service()->applyIfChanged();

		$this->assertSame(expected: 'failed', actual: $result['status']);
		$this->assertSame(expected: 'package', actual: $result['errors'][0]['section']);
		$this->assertSame(expected: 0, actual: $this->imports);
	}//end testMissingPathFails()

	/**
	 * Another replica holding the lock: nothing is applied.
	 *
	 * @return void
	 */
	public function testBusyLockSkips(): void {
		$this->lockBusy = true;

		$this->assertSame(expected: 'busy', actual: $this->service()->applyIfChanged()['status']);
		$this->assertSame(expected: 0, actual: $this->imports);
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
		$this->assertTrue(condition: $status['managed']);
		$this->assertSame(expected: $this->dir, actual: $status['path']);
		$this->assertSame(expected: '3f2a9c1', actual: $status['revision']);
		$this->assertTrue(condition: $status['locked']);
		$this->assertFalse(condition: $status['drift']);

		$this->running['config']['tokenSet'] = 'amsterdam';
		$this->assertTrue(condition: $this->service()->getStatus()['drift']);
	}//end testStatusShowsRevisionAndDrift()

	/**
	 * The lock only counts while a source is set.
	 *
	 * @return void
	 */
	public function testLockNeedsASource(): void {
		$this->system['thematiq.config_source_lock'] = true;
		$this->assertTrue(condition: $this->service()->isLocked());

		unset($this->system['thematiq.config_source']);
		$this->assertFalse(condition: $this->service()->isLocked());
	}//end testLockNeedsASource()
}//end class
