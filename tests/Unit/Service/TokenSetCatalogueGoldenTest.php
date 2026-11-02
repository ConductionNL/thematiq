<?php

/**
 * Golden test for TokenSetService::getAvailableTokenSets().
 *
 * @category Test
 * @package  OCA\Thematiq\Tests\Unit\Service
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://github.com/ConductionNL/thematiq
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/token-sets/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\ShippedTokenSetAuditService;
use OCA\Thematiq\Service\TokenSetFontAuditService;
use OCA\Thematiq\Service\TokenSetService;
use OCA\Thematiq\Service\TokenSetVocabularyAuditService;
use OCP\App\IAppManager;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Lists seeded catalogues: set files shipped and uploaded (and files that are not CSS), shipped
 * manifests missing, broken or partial, custom manifests that collide with shipped ids,
 * captured branding, provenance fields, licences, and audit warnings for shipped sets.
 * Records the list and every logged warning, case by case.
 * Set THEMATIQ_GOLDEN_RECORD=1 to rewrite it.
 *
 * @covers \OCA\Thematiq\Service\TokenSetService
 * @uses   \OCA\Thematiq\Service\RestrictedSetGate
 * @uses   \OCA\Thematiq\Service\RuntimeFile\SetFileReader
 */
class TokenSetCatalogueGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/../fixtures/golden/token-set-catalogue.golden.json';

	private const IDS = ['utrecht', 'den-haag', 'custom-acme', 'custom-beta', 'rijkshuisstijl', 'custom-utrecht'];

	/**
	 * The temp app directory.
	 *
	 * @var string
	 */
	private string $appDir = '';

	/**
	 * Remove the temp app directory.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$this->clear();
		parent::tearDown();
	}//end tearDown()

	/**
	 * Every seeded catalogue lists as recorded.
	 *
	 * @return void
	 */
	public function testCatalogueMatchesTheGoldenRecording(): void {
		$this->appDir = sys_get_temp_dir() . '/thematiq-catalogue-golden-' . getmypid();
		$actual = [];
		for ($seed = 1; $seed <= 250; $seed++) {
			$actual['seed:' . $seed] = $this->runCase(random: new Randomizer(new Mt19937($seed)));
		}

		if (getenv('THEMATIQ_GOLDEN_RECORD') === '1') {
			file_put_contents(self::FIXTURE, json_encode($actual, (JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . "\n");
		}

		$expected = json_decode((string)file_get_contents(self::FIXTURE), true);
		self::assertSame(expected: array_keys($expected), actual: array_keys($actual));
		foreach ($expected as $name => $json) {
			self::assertSame(expected: $json, actual: $actual[$name], message: $name);
		}
	}//end testCatalogueMatchesTheGoldenRecording()

	/**
	 * Remove the temp app directory and its files.
	 *
	 * @return void
	 */
	private function clear(): void {
		if ($this->appDir === '' || is_dir($this->appDir) === false) {
			return;
		}

		foreach ((array)glob($this->appDir . '/css/tokens/*') as $file) {
			unlink((string)$file);
		}

		foreach (['/css/tokens', '/css'] as $dir) {
			if (is_dir($this->appDir . $dir) === true) {
				rmdir($this->appDir . $dir);
			}
		}

		if (is_file($this->appDir . '/token-sets.json') === true) {
			unlink($this->appDir . '/token-sets.json');
		}

		rmdir($this->appDir);
	}//end clear()

	/**
	 * One seeded catalogue, as JSON text.
	 *
	 * @param Randomizer $random The case's randomizer.
	 *
	 * @return string
	 */
	private function runCase(Randomizer $random): string {
		$this->clear();
		mkdir($this->appDir . '/css/tokens', 0777, true);
		foreach (self::IDS as $id) {
			if ($random->getInt(0, 2) !== 0) {
				file_put_contents($this->appDir . '/css/tokens/' . $id . '.css', ':root{--x:' . $random->getInt(1, 9) . '}');
			}
		}

		if ($random->getInt(0, 3) === 0) {
			file_put_contents($this->appDir . '/css/tokens/readme.txt', 'not a set');
		}

		$this->writeManifest(random: $random);
		$app = [
			'custom_token_sets' => $this->customManifest(random: $random),
			'captured_theming' => $this->captured(random: $random),
		];
		if ($random->getInt(0, 4) === 0) {
			$app['restricted_sets_confirmed'] = (string)json_encode(['den-haag' => ['by' => 'admin', 'at' => '2026-01-01']]);
		}

		$calls = [];
		$service = $this->service(app: $app, calls: $calls);

		return (string)json_encode(['sets' => $service->getAvailableTokenSets(), 'calls' => $calls], JSON_UNESCAPED_SLASHES);
	}//end runCase()

	/**
	 * Write a seeded shipped manifest, or none.
	 *
	 * @param Randomizer $random The case's randomizer.
	 *
	 * @return void
	 */
	private function writeManifest(Randomizer $random): void {
		$shape = $random->getInt(0, 7);
		if ($shape === 0) {
			return;
		}

		if ($shape === 1) {
			file_put_contents($this->appDir . '/token-sets.json', '[{"broken');
			return;
		}

		$entries = [];
		foreach (['utrecht', 'den-haag', 'rijkshuisstijl', 'custom-utrecht'] as $id) {
			if ($random->getInt(0, 2) === 0) {
				continue;
			}

			$entry = ['id' => $id];
			$this->maybe(random: $random, entry: $entry, key: 'name', values: ['Gemeente ' . $id]);
			$this->maybe(random: $random, entry: $entry, key: 'description', values: ['About ' . $id]);
			$this->maybe(random: $random, entry: $entry, key: 'design_system', values: ['nlds', 'utrecht']);
			$this->maybe(random: $random, entry: $entry, key: 'theming', values: [['primary_color' => '#123456'], 'flat']);
			$this->maybe(random: $random, entry: $entry, key: 'upstreamVersion', values: ['1.2.0']);
			$this->maybe(random: $random, entry: $entry, key: 'upstreamRef', values: ['v1.2.0']);
			$this->maybe(random: $random, entry: $entry, key: 'licence', values: [['spdx' => 'LicenseRef-owner', 'restricted' => true]]);
			$this->maybe(random: $random, entry: $entry, key: 'warnings', values: [['shipped-own']]);
			$entries[] = $entry;
		}

		$entries[] = ['name' => 'No id'];
		file_put_contents($this->appDir . '/token-sets.json', (string)json_encode($entries));
	}//end writeManifest()

	/**
	 * A seeded custom manifest, as stored.
	 *
	 * @param Randomizer $random The case's randomizer.
	 *
	 * @return string
	 */
	private function customManifest(Randomizer $random): string {
		if ($random->getInt(0, 6) === 0) {
			return 'not json';
		}

		$manifest = [];
		foreach (['custom-acme', 'custom-beta', 'custom-utrecht', 'utrecht'] as $id) {
			if ($random->getInt(0, 1) === 0) {
				continue;
			}

			$entry = ['name' => 'Upload ' . $id];
			$this->maybe(random: $random, entry: $entry, key: 'description', values: ['Uploaded']);
			$this->maybe(random: $random, entry: $entry, key: 'design_system', values: ['nlds']);
			$this->maybe(random: $random, entry: $entry, key: 'theming', values: [['primary_color' => '#abcdef']]);
			$this->maybe(random: $random, entry: $entry, key: 'warnings', values: [['contrast-low'], 'flat']);
			$this->maybe(random: $random, entry: $entry, key: 'upstreamVersion', values: ['0.9']);
			$manifest[$id] = $entry;
		}

		return (string)json_encode($manifest);
	}//end customManifest()

	/**
	 * Seeded captured branding, as stored.
	 *
	 * @param Randomizer $random The case's randomizer.
	 *
	 * @return string
	 */
	private function captured(Randomizer $random): string {
		$captured = [];
		foreach (self::IDS as $id) {
			$pick = $random->getInt(0, 5);
			if ($pick === 0) {
				$captured[$id] = ['primary_color' => '#0b71a1', 'name' => 'Captured'];
			}

			if ($pick === 1) {
				$captured[$id] = 'flat';
			}
		}

		return [(string)json_encode($captured), '[1', '{}'][max(0, $random->getInt(-6, 2))];
	}//end captured()

	/**
	 * Set a seeded value for a key, or leave the key out.
	 *
	 * @param Randomizer $random The case's randomizer.
	 * @param array<string, mixed> $entry The entry.
	 * @param string $key The key.
	 * @param array<int, mixed> $values The candidate values.
	 *
	 * @return void
	 */
	private function maybe(Randomizer $random, array &$entry, string $key, array $values): void {
		$pick = $random->getInt(-1, (count($values) - 1));
		if ($pick >= 0) {
			$entry[$key] = $values[$pick];
		}
	}//end maybe()

	/**
	 * The service under test with recording doubles.
	 *
	 * @param array<string, string> $app The app config values.
	 * @param array<int, mixed> $calls The call log.
	 *
	 * @return TokenSetService
	 */
	private function service(array $app, array &$calls): TokenSetService {
		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('getAppPath')->willReturn($this->appDir);
		$appManager->method('getAppVersion')->willReturn('1.0.0');

		$config = $this->createMock(originalClassName: IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			static fn (string $appId, string $key, $default = '') => ($app[$key] ?? $default)
		);

		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		$logger->method('warning')->willReturnCallback(
			static function (...$arguments) use (&$calls): void {
				$calls[] = ['warning', $arguments[0]];
			}
		);

		$cacheFactory = $this->createMock(originalClassName: ICacheFactory::class);
		$cacheFactory->method('createDistributed')->willReturn($this->createMock(originalClassName: ICache::class));
		$cacheFactory->method('createLocal')->willReturn($this->createMock(originalClassName: ICache::class));

		$audit = $this->createMock(originalClassName: ShippedTokenSetAuditService::class);
		$audit->method('warningsFor')->willReturnCallback(
			static function (string $appPath, string $id, string $designSystem, array $theming): array {
				if (strlen($id) % 3 === 0) {
					return [];
				}

				return [['contrast' => $id . '@' . $designSystem, 'theming' => $theming]];
			}
		);
		$vocabulary = $this->createMock(originalClassName: TokenSetVocabularyAuditService::class);
		$vocabulary->method('warningsFor')->willReturnCallback(
			static fn (string $appPath, string $id, array $meta): array => array_fill(0, (int)isset($meta['upstreamRef']), ['vocabulary' => $id])
		);
		$fonts = $this->createMock(originalClassName: TokenSetFontAuditService::class);
		$fonts->method('warningsFor')->willReturnCallback(
			static fn (string $appPath, string $id, array $meta): array => array_fill(0, (int)isset($meta['description']), ['font' => $id])
		);

		return new TokenSetService(
			appManager: $appManager,
			config: $config,
			logger: $logger,
			audit: $audit,
			cacheFactory: $cacheFactory,
			vocabularyAudit: $vocabulary,
			fontAudit: $fonts
		);
	}//end service()
}//end class
