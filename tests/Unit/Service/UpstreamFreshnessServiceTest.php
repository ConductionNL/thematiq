<?php

/**
 * Unit tests for UpstreamFreshnessService.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/archive/2026-07-23-upstream-token-freshness/tasks.md#task-5.1
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

/**
 * Covers openspec/specs/upstream-freshness/spec.md: the disabled/zero-egress
 * path, the 304 steady state, the 200-with-attributed-change path, the
 * generic-notice fallback, silent failure containment, the custom-set /
 * no-upstreamRef exclusion, dismissal-and-resurface semantics, and the
 * two-request-per-run hard cap.
 */
class UpstreamFreshnessServiceTest extends TestCase {

	/**
	 * In-memory app config store backing the IConfig mock, so getAppValue()/
	 * setAppValue() behave like real persisted state across one test.
	 *
	 * @var array<string, string>
	 */
	private array $configStore = [];

	/**
	 * Reset the fake config store before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->configStore = [];
	}//end setUp()

	/**
	 * Build an IConfig mock backed by $this->configStore.
	 *
	 * @return IConfig
	 */
	private function makeConfig(): IConfig {
		$config = $this->createMock(originalClassName: IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			function (string $app, string $key, $default = '') {
				return ($this->configStore[$key] ?? $default);
			}
		);
		$config->method('setAppValue')->willReturnCallback(
			function (string $app, string $key, $value) {
				$this->configStore[$key] = $value;
			}
		);

		return $config;
	}//end makeConfig()

	/**
	 * Build the service under test, with a stubbed TokenSetService and an
	 * optionally-supplied IClientService / LoggerInterface mock.
	 *
	 * @param array<int, array<string, mixed>> $tokenSets The installed token sets TokenSetService returns.
	 * @param IClientService|null $clientService An optional pre-configured client service mock.
	 * @param LoggerInterface|null $logger An optional pre-configured logger mock.
	 *
	 * @return UpstreamFreshnessService
	 */
	private function makeService(
		array $tokenSets = [],
		?IClientService $clientService = null,
		?LoggerInterface $logger = null,
	): UpstreamFreshnessService {
		$tokenSetService = $this->createMock(originalClassName: TokenSetService::class);
		$tokenSetService->method('getAvailableTokenSets')->willReturn($tokenSets);

		return new UpstreamFreshnessService(
			config: $this->makeConfig(),
			clientService: ($clientService ?? $this->createMock(originalClassName: IClientService::class)),
			tokenSetService: $tokenSetService,
			logger: ($logger ?? $this->createMock(originalClassName: LoggerInterface::class))
		);
	}//end makeService()

	/**
	 * (a) Disabled ⇒ runCheck() performs zero HTTP calls.
	 *
	 * @return void
	 */
	public function testDisabledPerformsZeroHttpCalls(): void {
		$clientService = $this->createMock(originalClassName: IClientService::class);
		$clientService->expects($this->never())->method('newClient');

		$service = $this->makeService(tokenSets: [], clientService: $clientService);

		$this->assertFalse(condition: $service->isEnabled());
		$service->runCheck();

		$this->assertNull(actual: $service->getStatus()['lastChecked']);
	}//end testDisabledPerformsZeroHttpCalls()

	/**
	 * (b) 304 ⇒ only upstream_checked_at updated, no notices.
	 *
	 * @return void
	 */
	public function test304OnlyUpdatesCheckedAtAndProducesNoNotices(): void {
		$response = $this->createMock(originalClassName: IResponse::class);
		$response->method('getStatusCode')->willReturn(304);

		$client = $this->createMock(originalClassName: IClient::class);
		$client->expects($this->once())->method('get')->willReturn($response);

		$clientService = $this->createMock(originalClassName: IClientService::class);
		$clientService->method('newClient')->willReturn($client);

		$service = $this->makeService(tokenSets: [], clientService: $clientService);
		$service->setEnabled(enabled: true);
		$service->runCheck();

		$status = $service->getStatus();
		$this->assertNotNull(actual: $status['lastChecked']);
		$this->assertSame(expected: [], actual: $status['notices']);
	}//end test304OnlyUpdatesCheckedAtAndProducesNoNotices()

	/**
	 * (c) 200 with SHA equal to all installed refs ⇒ no notices, and no
	 * second (compare) request is made since nothing is stale.
	 *
	 * @return void
	 */
	public function test200WithUnchangedHeadShaProducesNoNotices(): void {
		$sha = str_repeat('a', 40);

		$response = $this->createMock(originalClassName: IResponse::class);
		$response->method('getStatusCode')->willReturn(200);
		$response->method('getBody')->willReturn($sha);
		$response->method('getHeader')->willReturn('"etag-1"');

		$client = $this->createMock(originalClassName: IClient::class);
		$client->expects($this->once())->method('get')->willReturn($response);

		$clientService = $this->createMock(originalClassName: IClientService::class);
		$clientService->method('newClient')->willReturn($client);

		$tokenSets = [
			['id' => 'utrecht', 'name' => 'Gemeente Utrecht', 'upstreamRef' => $sha, 'upstreamVersion' => '1.2.0'],
		];

		$service = $this->makeService(tokenSets: $tokenSets, clientService: $clientService);
		$service->setEnabled(enabled: true);
		$service->runCheck();

		$this->assertSame(expected: [], actual: $service->getStatus()['notices']);
	}//end test200WithUnchangedHeadShaProducesNoNotices()

	/**
	 * (d) 200 with a new SHA + a successful compare listing
	 * proprietary/utrecht-design-tokens/... ⇒ a notice for utrecht carrying
	 * its recorded upstreamVersion, none for the untouched set.
	 *
	 * @return void
	 */
	public function test200WithNewShaAndSuccessfulCompareProducesPerSetNotice(): void {
		$oldSha = str_repeat('a', 40);
		$newSha = str_repeat('b', 40);

		$freshResponse = $this->createMock(originalClassName: IResponse::class);
		$freshResponse->method('getStatusCode')->willReturn(200);
		$freshResponse->method('getBody')->willReturn($newSha);
		$freshResponse->method('getHeader')->willReturn('"etag-2"');

		$compareBody = json_encode(
			['files' => [['filename' => 'proprietary/utrecht-design-tokens/src/brand/utrecht/color.tokens.json']]]
		);
		$compareResponse = $this->createMock(originalClassName: IResponse::class);
		$compareResponse->method('getStatusCode')->willReturn(200);
		$compareResponse->method('getBody')->willReturn($compareBody);

		$client = $this->createMock(originalClassName: IClient::class);
		$client->expects($this->exactly(count: 2))->method('get')->willReturnOnConsecutiveCalls($freshResponse, $compareResponse);

		$clientService = $this->createMock(originalClassName: IClientService::class);
		$clientService->method('newClient')->willReturn($client);

		$tokenSets = [
			['id' => 'utrecht', 'name' => 'Gemeente Utrecht', 'upstreamRef' => $oldSha, 'upstreamVersion' => '1.2.0'],
			['id' => 'amsterdam', 'name' => 'Amsterdam', 'upstreamRef' => $oldSha, 'upstreamVersion' => '2.0.0'],
		];

		$service = $this->makeService(tokenSets: $tokenSets, clientService: $clientService);
		$service->setEnabled(enabled: true);
		$service->runCheck();

		$notices = $service->getStatus()['notices'];
		$this->assertCount(expectedCount: 1, haystack: $notices);
		$this->assertSame(expected: 'utrecht', actual: $notices[0]['setId']);
		$this->assertSame(expected: '1.2.0', actual: $notices[0]['upstreamVersion']);
	}//end test200WithNewShaAndSuccessfulCompareProducesPerSetNotice()

	/**
	 * (e) The compare request fails ⇒ a single generic notice, no exception.
	 *
	 * @return void
	 */
	public function testCompareFailureProducesGenericNoticeWithoutException(): void {
		$newSha = str_repeat('c', 40);

		$freshResponse = $this->createMock(originalClassName: IResponse::class);
		$freshResponse->method('getStatusCode')->willReturn(200);
		$freshResponse->method('getBody')->willReturn($newSha);
		$freshResponse->method('getHeader')->willReturn('"etag-3"');

		$client = $this->createMock(originalClassName: IClient::class);
		$client->expects($this->exactly(count: 2))->method('get')->willReturnCallback(
			function () use ($freshResponse) {
				static $call = 0;
				$call++;
				if ($call === 1) {
					return $freshResponse;
				}

				throw new \RuntimeException('compare request failed');
			}
		);

		$clientService = $this->createMock(originalClassName: IClientService::class);
		$clientService->method('newClient')->willReturn($client);

		$tokenSets = [
			['id' => 'utrecht', 'name' => 'Gemeente Utrecht', 'upstreamRef' => str_repeat('a', 40), 'upstreamVersion' => '1.2.0'],
		];

		$service = $this->makeService(tokenSets: $tokenSets, clientService: $clientService);
		$service->setEnabled(enabled: true);
		$service->runCheck();

		$notices = $service->getStatus()['notices'];
		$this->assertCount(expectedCount: 1, haystack: $notices);
		$this->assertSame(expected: '__generic__', actual: $notices[0]['setId']);
		$this->assertSame(expected: $newSha, actual: $notices[0]['headSha']);
	}//end testCompareFailureProducesGenericNoticeWithoutException()

	/**
	 * (f) The freshness request itself throws (timeout/DNS) ⇒ no exception
	 * escapes, prior notices are preserved untouched, and the failure is
	 * logged.
	 *
	 * @return void
	 */
	public function testFreshnessRequestThrowLeavesPriorNoticesUntouched(): void {
		$this->configStore['upstream_updates'] = json_encode(
			[
				'utrecht' => [
					'installedRef' => 'old-ref',
					'installedVersion' => '1.0.0',
					'headSha' => 'old-head-sha',
					'upstreamVersion' => '1.0.0',
					'detectedAt' => 111,
				],
			]
		);

		$client = $this->createMock(originalClassName: IClient::class);
		$client->method('get')->willThrowException(new \RuntimeException('DNS failure'));

		$clientService = $this->createMock(originalClassName: IClientService::class);
		$clientService->method('newClient')->willReturn($client);

		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		$logger->expects($this->atLeastOnce())->method('info');

		$service = $this->makeService(tokenSets: [], clientService: $clientService, logger: $logger);
		$service->setEnabled(enabled: true);

		// Must not throw.
		$service->runCheck();

		$notices = $service->getStatus()['notices'];
		$this->assertCount(expectedCount: 1, haystack: $notices);
		$this->assertSame(expected: 'utrecht', actual: $notices[0]['setId']);
		$this->assertSame(expected: '1.0.0', actual: $notices[0]['upstreamVersion']);
	}//end testFreshnessRequestThrowLeavesPriorNoticesUntouched()

	/**
	 * A malformed 200 body (unparseable head SHA) is discarded without
	 * creating a notice or updating the stored ETag.
	 *
	 * @return void
	 */
	public function testMalformedHeadShaBodyIsDiscarded(): void {
		$response = $this->createMock(originalClassName: IResponse::class);
		$response->method('getStatusCode')->willReturn(200);
		$response->method('getBody')->willReturn('<html>not a sha</html>');
		$response->method('getHeader')->willReturn('"should-not-be-stored"');

		$client = $this->createMock(originalClassName: IClient::class);
		$client->expects($this->once())->method('get')->willReturn($response);

		$clientService = $this->createMock(originalClassName: IClientService::class);
		$clientService->method('newClient')->willReturn($client);

		$service = $this->makeService(tokenSets: [], clientService: $clientService);
		$service->setEnabled(enabled: true);
		$service->runCheck();

		$this->assertSame(expected: [], actual: $service->getStatus()['notices']);
		$this->assertSame(expected: '', actual: ($this->configStore['upstream_etag'] ?? ''));
	}//end testMalformedHeadShaBodyIsDiscarded()

	/**
	 * (g) Sets without upstreamRef, and all custom-* sets, are excluded from
	 * comparison entirely — no notices, and since nothing is stale the
	 * compare request is never made (only the freshness GET runs).
	 *
	 * @return void
	 */
	public function testSetsWithoutUpstreamRefAndCustomSetsAreExcluded(): void {
		$newSha = str_repeat('d', 40);

		$response = $this->createMock(originalClassName: IResponse::class);
		$response->method('getStatusCode')->willReturn(200);
		$response->method('getBody')->willReturn($newSha);
		$response->method('getHeader')->willReturn('"etag-4"');

		$client = $this->createMock(originalClassName: IClient::class);
		$client->expects($this->once())->method('get')->willReturn($response);

		$clientService = $this->createMock(originalClassName: IClientService::class);
		$clientService->method('newClient')->willReturn($client);

		$tokenSets = [
			['id' => 'nextcloud', 'name' => 'Nextcloud'],
			['id' => 'custom-gemeente-voorbeeld', 'name' => 'Custom', 'upstreamRef' => 'zzzz', 'custom' => true],
		];

		$service = $this->makeService(tokenSets: $tokenSets, clientService: $clientService);
		$service->setEnabled(enabled: true);
		$service->runCheck();

		$this->assertSame(expected: [], actual: $service->getStatus()['notices']);
	}//end testSetsWithoutUpstreamRefAndCustomSetsAreExcluded()

	/**
	 * (h) Dismissing a notice at its current version hides it; a later
	 * detection carrying a newer version for the same set re-surfaces it.
	 *
	 * @return void
	 */
	public function testDismissalHidesNoticeUntilNewerVersionDetected(): void {
		$this->configStore['upstream_updates'] = json_encode(
			[
				'utrecht' => [
					'installedRef' => 'old-ref',
					'installedVersion' => '0.9.0',
					'headSha' => 'sha1',
					'upstreamVersion' => '1.0.0',
					'detectedAt' => 1,
				],
			]
		);

		$service = $this->makeService(tokenSets: []);
		$service->dismiss(setId: 'utrecht', versionOrSha: '1.0.0');

		$this->assertSame(expected: [], actual: $service->getStatus()['notices']);

		// A newer detection (different version) re-surfaces regardless of
		// the prior dismissal.
		$this->configStore['upstream_updates'] = json_encode(
			[
				'utrecht' => [
					'installedRef' => 'old-ref',
					'installedVersion' => '0.9.0',
					'headSha' => 'sha2',
					'upstreamVersion' => '1.1.0',
					'detectedAt' => 2,
				],
			]
		);

		$notices = $service->getStatus()['notices'];
		$this->assertCount(expectedCount: 1, haystack: $notices);
		$this->assertSame(expected: '1.1.0', actual: $notices[0]['upstreamVersion']);
	}//end testDismissalHidesNoticeUntilNewerVersionDetected()

	/**
	 * (i) Never more than two HTTP requests per run, even with several
	 * stale, attributable sets.
	 *
	 * @return void
	 */
	public function testNeverMoreThanTwoRequestsPerRun(): void {
		$oldSha = str_repeat('e', 40);
		$newSha = str_repeat('f', 40);

		$freshResponse = $this->createMock(originalClassName: IResponse::class);
		$freshResponse->method('getStatusCode')->willReturn(200);
		$freshResponse->method('getBody')->willReturn($newSha);
		$freshResponse->method('getHeader')->willReturn('"etag-5"');

		$compareBody = json_encode(
			[
				'files' => [
					['filename' => 'proprietary/utrecht-design-tokens/src/brand/utrecht/color.tokens.json'],
					['filename' => 'proprietary/amsterdam-design-tokens/src/brand/amsterdam/color.tokens.json'],
					['filename' => 'proprietary/denhaag-design-tokens/src/brand/denhaag/color.tokens.json'],
				],
			]
		);
		$compareResponse = $this->createMock(originalClassName: IResponse::class);
		$compareResponse->method('getStatusCode')->willReturn(200);
		$compareResponse->method('getBody')->willReturn($compareBody);

		$client = $this->createMock(originalClassName: IClient::class);
		$client->expects($this->atMost(allowedInvocations: 2))->method('get')->willReturnOnConsecutiveCalls($freshResponse, $compareResponse);

		$clientService = $this->createMock(originalClassName: IClientService::class);
		$clientService->method('newClient')->willReturn($client);

		$tokenSets = [
			['id' => 'utrecht', 'name' => 'Utrecht', 'upstreamRef' => $oldSha, 'upstreamVersion' => '1.0.0'],
			['id' => 'amsterdam', 'name' => 'Amsterdam', 'upstreamRef' => $oldSha, 'upstreamVersion' => '2.0.0'],
			['id' => 'denhaag', 'name' => 'Den Haag', 'upstreamRef' => $oldSha, 'upstreamVersion' => '3.0.0'],
			['id' => 'rotterdam', 'name' => 'Rotterdam', 'upstreamRef' => $oldSha, 'upstreamVersion' => '4.0.0'],
		];

		$service = $this->makeService(tokenSets: $tokenSets, clientService: $clientService);
		$service->setEnabled(enabled: true);
		$service->runCheck();

		$notices = $service->getStatus()['notices'];
		$this->assertCount(expectedCount: 3, haystack: $notices);
	}//end testNeverMoreThanTwoRequestsPerRun()
}//end class
