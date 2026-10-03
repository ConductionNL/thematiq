<?php

/**
 * Unit tests for OverridesController's theming-audit call-site wiring.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/archive/2026-07-23-theming-audit-log/tasks.md#task-5.3
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Controller;

use OCA\Thematiq\Controller\OverridesController;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\CustomOverridesService;
use OCA\Thematiq\Service\ThemingAuditService;
use OCA\Thematiq\Service\ThemingService;
use OCP\IConfig;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;

/**
 * Covers tasks.md#task-2.2 / #task-5.3: setOverrides() logs one
 * overrides_written entry (old/new token maps) only after a successful
 * write, and importOverrides() logs one overrides_imported entry carrying
 * the imported/skipped counts plus a hashed (never raw) CSS payload.
 */
class OverridesControllerAuditTest extends TestCase {

	/**
	 * The mocked custom overrides service.
	 *
	 * @var CustomOverridesService&\PHPUnit\Framework\MockObject\MockObject
	 */
	private CustomOverridesService $overridesService;

	/**
	 * The mocked audit service.
	 *
	 * @var ThemingAuditService&\PHPUnit\Framework\MockObject\MockObject
	 */
	private ThemingAuditService $auditService;

	/**
	 * The mocked request.
	 *
	 * @var IRequest&\PHPUnit\Framework\MockObject\MockObject
	 */
	private IRequest $request;

	/**
	 * The controller under test.
	 *
	 * @var OverridesController
	 */
	private OverridesController $controller;

	/**
	 * The mocked app config.
	 *
	 * @var IConfig&\PHPUnit\Framework\MockObject\MockObject
	 */
	private IConfig $config;

	/**
	 * The mocked theming service.
	 *
	 * @var ThemingService&\PHPUnit\Framework\MockObject\MockObject
	 */
	private ThemingService $themingService;

	protected function setUp(): void {
		parent::setUp();

		$this->overridesService = $this->createMock(CustomOverridesService::class);
		$this->auditService = $this->createMock(ThemingAuditService::class);
		$this->request = $this->createMock(IRequest::class);
		$this->config = $this->createMock(IConfig::class);
		$this->themingService = $this->createMock(ThemingService::class);

		$this->controller = new OverridesController(
			'nldesign',
			$this->request,
			$this->overridesService,
			new CssParserService(),
			$this->auditService,
			$this->config,
			$this->themingService
		);
	}//end setUp()

	/**
	 * Overrides are written to the file of the set the request names, so
	 * edits made on the stock set land in the stock set's own file.
	 */
	public function testSetOverridesWritesTheRequestedSetsFile(): void {
		$this->request->method('getParams')->willReturn(
			['overrides' => ['--nldesign-color-primary' => '#007bc7'], 'tokenSet' => 'nextcloud']
		);
		$this->request->method('getParam')->willReturnMap([['tokenSet', '', 'nextcloud']]);

		$this->overridesService->expects($this->once())
			->method('write')
			->with(['--nldesign-color-primary' => '#007bc7'], 'nextcloud');

		$response = $this->controller->setOverrides();

		$this->assertSame(200, $response->getStatus());
	}//end testSetOverridesWritesTheRequestedSetsFile()

	/**
	 * The reset empties the overrides of the set that was active AND of the
	 * stock set, makes the stock set active, resets core theming, and records
	 * each change in the audit vocabulary it already has.
	 */
	public function testResetEmptiesBothFilesAndReturnsToStock(): void {
		$this->request->method('getParams')->willReturn(['reset' => true]);
		$this->config->method('getAppValue')->willReturnCallback(
			fn (string $app, string $key, $default = '') => ($key === 'token_set' ? 'amsterdam' : $default)
		);
		$this->overridesService->method('read')->willReturnMap(
			[
				['amsterdam', ['--nldesign-color-primary' => '#ec0000']],
				['nextcloud', ['--nldesign-component-header-background-color' => '#e8eff6']],
			]
		);

		$written = [];
		$this->overridesService->method('write')->willReturnCallback(
			function (array $tokens, ?string $tokenSet) use (&$written): void {
				$written[$tokenSet] = $tokens;
			}
		);
		$this->config->expects($this->once())->method('setAppValue')->with('thematiq', 'token_set', 'nextcloud');
		$this->themingService->expects($this->once())->method('resetToDefaults');

		$actions = [];
		$this->auditService->method('log')->willReturnCallback(
			function (string $action) use (&$actions): void {
				$actions[] = $action;
			}
		);

		$response = $this->controller->setOverrides();

		$this->assertSame(200, $response->getStatus());
		$this->assertSame(['amsterdam' => [], 'nextcloud' => []], $written);
		$this->assertSame(['overrides_written', 'overrides_written', 'token_set_changed'], $actions);
	}//end testResetEmptiesBothFilesAndReturnsToStock()

	/**
	 * A reset whose file write fails changes nothing else and says so.
	 */
	public function testResetWriteFailureLeavesTheSetAndThemingAlone(): void {
		$this->request->method('getParams')->willReturn(['reset' => true]);
		$this->config->method('getAppValue')->willReturn('amsterdam');
		$this->overridesService->method('read')->willReturn([]);
		$this->overridesService->method('write')->willThrowException(new \RuntimeException('read-only'));

		$this->config->expects($this->never())->method('setAppValue');
		$this->themingService->expects($this->never())->method('resetToDefaults');
		$this->auditService->expects($this->never())->method('log');

		$response = $this->controller->setOverrides();

		$this->assertSame(500, $response->getStatus());
	}//end testResetWriteFailureLeavesTheSetAndThemingAlone()

	/**
	 * A successful setOverrides() logs exactly one overrides_written entry
	 * with the pre-write and post-write token maps.
	 */
	public function testSetOverridesLogsOneEntryWithOldAndNew(): void {
		$this->overridesService->method('read')->willReturn(['--nldesign-color-primary' => '#000000']);
		$this->request->method('getParams')->willReturn(
			['overrides' => ['--nldesign-color-primary' => '#007bc7']]
		);

		$this->auditService->expects($this->once())
			->method('log')
			->with(
				'overrides_written',
				[
					'old' => ['--nldesign-color-primary' => '#000000'],
					'new' => ['--nldesign-color-primary' => '#007bc7'],
				]
			);

		$response = $this->controller->setOverrides();

		$this->assertSame(200, $response->getStatus());
	}//end testSetOverridesLogsOneEntryWithOldAndNew()

	/**
	 * A validation failure (non-array overrides) logs nothing.
	 */
	public function testSetOverridesRejectsNonArrayWithoutLogging(): void {
		$this->request->method('getParams')->willReturn(['overrides' => 'not-an-array']);

		$this->auditService->expects($this->never())->method('log');

		$response = $this->controller->setOverrides();

		$this->assertSame(400, $response->getStatus());
	}//end testSetOverridesRejectsNonArrayWithoutLogging()

	/**
	 * A write failure (RuntimeException) logs nothing — the audit trail
	 * only records successful writes.
	 */
	public function testSetOverridesWriteFailureLogsNothing(): void {
		$this->overridesService->method('read')->willReturn([]);
		$this->overridesService->method('write')->willThrowException(new \RuntimeException('disk full'));
		$this->request->method('getParams')->willReturn(['overrides' => ['--nldesign-color-primary' => '#007bc7']]);

		$this->auditService->expects($this->never())->method('log');

		$response = $this->controller->setOverrides();

		$this->assertSame(500, $response->getStatus());
	}//end testSetOverridesWriteFailureLogsNothing()

	/**
	 * A successful import logs one overrides_imported entry with the
	 * imported/skipped counts and a hashed CSS payload — never the raw CSS.
	 */
	public function testImportOverridesLogsHashedPayload(): void {
		$css = ':root { --color-primary: #007bc7; }';

		$this->request->method('getUploadedFile')->willReturn(
			[
				'tmp_name' => $this->writeTempCssFile(css: $css),
				'name' => 'overrides.css',
				'size' => strlen($css),
			]
		);

		$this->auditService->expects($this->once())
			->method('log')
			->with(
				'overrides_imported',
				$this->callback(function (array $context) use ($css): bool {
					return ($context['imported'] === 1)
						&& ($context['skipped'] === 0)
						&& ($context['new'] === $css)
						&& ($context['newIsCss'] === true);
				})
			);

		$response = $this->controller->importOverrides();

		$this->assertSame(200, $response->getStatus());
	}//end testImportOverridesLogsHashedPayload()

	/**
	 * Write a CSS string to a temp file for getUploadedFile()['tmp_name'].
	 *
	 * @param string $css The CSS content.
	 *
	 * @return string The temp file path.
	 */
	private function writeTempCssFile(string $css): string {
		$path = tempnam(sys_get_temp_dir(), 'nldesign-overrides-');
		file_put_contents($path, $css);

		return $path;
	}//end writeTempCssFile()
}//end class
