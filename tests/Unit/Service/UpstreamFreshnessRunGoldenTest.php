<?php

/**
 * Golden test for UpstreamFreshnessService::runCheck().
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
 * @spec openspec/specs/upstream-freshness/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\TokenSetService;
use OCA\Thematiq\Service\UpstreamFreshnessService;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Random\Engine\Mt19937;
use Random\Randomizer;
use RuntimeException;

/**
 * Runs seeded freshness checks: off or on, with and without a stored ETag, answers 304, 200 and
 * other statuses, head revisions well formed or not, an ETag header or none, installed sets
 * behind, level or without a reference, and a compare call that answers, fails, throws or names
 * changed paths per organisation. Records every config write and request, case by case, with
 * the clock readings masked. Set THEMATIQ_GOLDEN_RECORD=1 to rewrite it.
 *
 * @covers \OCA\Thematiq\Service\UpstreamFreshnessService
 */
class UpstreamFreshnessRunGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/../fixtures/golden/upstream-freshness-run.golden.json';

	private const HEAD = 'abcdef1234567';

	/**
	 * Every seeded run writes and requests as recorded.
	 *
	 * @return void
	 */
	public function testRunCheckMatchesTheGoldenRecording(): void {
		$actual = [];
		for ($seed = 1; $seed <= 300; $seed++) {
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
	}//end testRunCheckMatchesTheGoldenRecording()

	/**
	 * One seeded run, as JSON text.
	 *
	 * @param Randomizer $random The case's randomizer.
	 *
	 * @return string
	 */
	private function runCase(Randomizer $random): string {
		$calls = [];
		$app = [
			'upstream_freshness_enabled' => ['yes', 'yes', 'yes', 'no'][$random->getInt(0, 3)],
			'upstream_etag' => ['', 'W/"old"'][$random->getInt(0, 1)],
			'upstream_updates' => ['{}', '{"rijkshuisstijl":{"headSha":"x","upstreamVersion":"1"}}', 'broken'][$random->getInt(0, 2)],
		];
		if ($random->getInt(0, 4) === 0) {
			$app['upstream_manifest_url'] = ['https://mirror.example/no-marker', 'https://api.example/repos/o/t/commits/main'][$random->getInt(0, 1)];
		}

		$config = $this->createMock(originalClassName: IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			static fn (string $appId, string $key, $default = '') => ($app[$key] ?? $default)
		);
		$config->method('setAppValue')->willReturnCallback(
			static function (string $appId, string $key, $value) use (&$calls): void {
				$calls[] = ['setAppValue', $key, self::masked(key: $key, value: (string)$value)];
			}
		);

		$head = [self::HEAD, '  ' . self::HEAD . "\n", '', 'not-a-sha', 'ABCDEF1234567890ABCDEF1234567890ABCDEF12', 'abc12'][$random->getInt(0, 5)];
		$manifest = $this->response(status: [200, 200, 200, 304, 500][$random->getInt(0, 4)], body: $head, etag: ['', 'W/"new"'][$random->getInt(0, 1)]);
		$compare = $this->compareOutcome(random: $random);

		$client = $this->createMock(originalClassName: IClient::class);
		$client->method('get')->willReturnCallback(
			static function (string $uri, array $options = []) use (&$calls, $manifest, $compare): IResponse {
				$calls[] = ['get', $uri, $options];
				if (str_contains($uri, '/compare/') === false) {
					return $manifest;
				}

				if ($compare === null) {
					throw new RuntimeException('compare offline');
				}

				return $compare;
			}
		);
		$clientService = $this->createMock(originalClassName: IClientService::class);
		$clientService->method('newClient')->willReturn($client);

		$sets = [];
		foreach (['rijkshuisstijl', 'denhaag', 'utrecht', 'custom-acme'] as $id) {
			$ref = [null, '', self::HEAD, 'base111', 'base000'][$random->getInt(0, 4)];
			$sets[] = ['id' => $id, 'name' => 'Set ' . $id, 'upstreamRef' => $ref, 'upstreamVersion' => ['1.0', null][$random->getInt(0, 1)]];
		}

		$tokenSets = $this->createMock(originalClassName: TokenSetService::class);
		$tokenSets->method('getAvailableTokenSets')->willReturn($sets);

		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		$logger->method('info')->willReturnCallback(
			static function (...$arguments) use (&$calls): void {
				$calls[] = ['info', $arguments[0]];
			}
		);

		$service = new UpstreamFreshnessService(config: $config, clientService: $clientService, tokenSetService: $tokenSets, logger: $logger);
		$service->runCheck();

		return (string)json_encode($calls, JSON_UNESCAPED_SLASHES);
	}//end runCase()

	/**
	 * A config value with its clock readings replaced.
	 *
	 * @param string $key The config key.
	 * @param string $value The value written.
	 *
	 * @return string
	 */
	private static function masked(string $key, string $value): string {
		if ($key === 'upstream_checked_at') {
			return '(time)';
		}

		return (string)preg_replace('/"detectedAt":\d+/', '"detectedAt":"(time)"', $value);
	}//end masked()

	/**
	 * A seeded compare response, or null for a compare call that throws.
	 *
	 * @param Randomizer $random The case's randomizer.
	 *
	 * @return IResponse|null
	 */
	private function compareOutcome(Randomizer $random): ?IResponse {
		$kind = $random->getInt(0, 6);
		if ($kind === 0) {
			return null;
		}

		if ($kind === 1) {
			return $this->response(status: 404, body: '', etag: '');
		}

		if ($kind === 2) {
			return $this->response(status: 200, body: '{"files":"none"}', etag: '');
		}

		$files = [['filename' => 'README.md'], ['nope' => 1], ['filename' => 5]];
		foreach (['rijkshuisstijl', 'denhaag-design-tokens', 'Utrecht', 'other'] as $org) {
			if ($random->getInt(0, 1) === 1) {
				$files[] = ['filename' => 'proprietary/' . $org . '/src/tokens.json'];
			}
		}

		return $this->response(status: 200, body: (string)json_encode(['files' => $files]), etag: '');
	}//end compareOutcome()

	/**
	 * A response double.
	 *
	 * @param int $status The status code.
	 * @param string $body The body.
	 * @param string $etag The ETag header.
	 *
	 * @return IResponse
	 */
	private function response(int $status, string $body, string $etag): IResponse {
		$response = $this->createMock(originalClassName: IResponse::class);
		$response->method('getStatusCode')->willReturn($status);
		$response->method('getBody')->willReturn($body);
		$response->method('getHeader')->willReturnCallback(static fn (string $key): string => (['ETag' => $etag][$key] ?? ''));

		return $response;
	}//end response()
}//end class
