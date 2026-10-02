<?php

/**
 * Unit tests for what OverridesController::setOverrides() accepts and reports.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/authoring-token-value-types/tasks.md#task-2.1
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Controller;

use OCA\Thematiq\Controller\OverridesController;
use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\CustomOverridesService;
use OCA\Thematiq\Service\DarkPaletteService;
use OCA\Thematiq\Service\RuntimeFile\DirectoryRuntimeFileStore;
use OCA\Thematiq\Service\ThemingAuditService;
use OCA\Thematiq\Service\ThemingService;
use OCP\App\IAppManager;
use OCP\IConfig;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Thematiq#694: a save with an unknown token name or an unsafe value answered
 * 200 while the token was dropped, the audit entry listed tokens that never
 * reached the file, and a write failure returned the absolute file path.
 *
 * The overrides service here is the REAL CustomOverridesService writing to a
 * temporary app directory, so what the controller reports is compared with
 * what actually lands in custom-overrides.css.
 */
class OverridesControllerValidationTest extends TestCase {

	/**
	 * Temporary app root holding css/custom-overrides.css.
	 *
	 * @var string
	 */
	private string $appDir;

	/**
	 * The real overrides service.
	 *
	 * @var CustomOverridesService
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

	protected function setUp(): void {
		parent::setUp();

		$this->appDir = sys_get_temp_dir() . '/thematiq-694-' . bin2hex(random_bytes(4));
		mkdir($this->appDir . '/css', 0777, true);

		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getAppPath')->willReturn($this->appDir);

		$parser = new CssParserService();
		$darkPalette = new DarkPaletteService(new ContrastService(), $parser, $appManager, $this->createMock(LoggerInterface::class));
		$this->overridesService = new CustomOverridesService(new DirectoryRuntimeFileStore($appManager->getAppPath('thematiq')), $parser, $darkPalette);
		$this->overridesService->write(tokens: ['--color-primary' => '#000000']);

		$this->auditService = $this->createMock(ThemingAuditService::class);
		$this->request = $this->createMock(IRequest::class);
	}//end setUp()

	protected function tearDown(): void {
		foreach (glob($this->appDir . '/css/*') ?: [] as $file) {
			unlink($file);
		}

		rmdir($this->appDir . '/css');
		rmdir($this->appDir);
		parent::tearDown();
	}//end tearDown()

	/**
	 * Build the controller around a given overrides service.
	 *
	 * @param CustomOverridesService $service The overrides service.
	 *
	 * @return OverridesController
	 */
	private function controller(CustomOverridesService $service): OverridesController {
		return new OverridesController(
			'thematiq',
			$this->request,
			$service,
			new CssParserService(),
			$this->auditService,
			$this->createMock(IConfig::class),
			$this->createMock(ThemingService::class)
		);
	}//end controller()

	/**
	 * An unknown token name is refused with 400 naming it, and nothing is written.
	 */
	public function testUnknownTokenIs400(): void {
		$this->request->method('getParams')->willReturn(
			['overrides' => ['--color-primary' => '#112233', '--not-a-registry-token' => '#445566']]
		);
		$this->auditService->expects($this->never())->method('log');

		$response = $this->controller(service: $this->overridesService)->setOverrides();

		$this->assertSame(400, $response->getStatus());
		$this->assertStringContainsString('--not-a-registry-token', json_encode($response->getData()));
		$this->assertSame(['--color-primary' => '#000000'], $this->overridesService->read());
	}//end testUnknownTokenIs400()

	/**
	 * A value the writer would drop (a CSS injection attempt) is refused with 400
	 * naming the token, and nothing is written.
	 */
	public function testWrongValueIs400(): void {
		$this->request->method('getParams')->willReturn(
			['overrides' => ['--color-primary' => 'red; } body { display: none']]
		);
		$this->auditService->expects($this->never())->method('log');

		$response = $this->controller(service: $this->overridesService)->setOverrides();

		$this->assertSame(400, $response->getStatus());
		$this->assertStringContainsString('--color-primary', json_encode($response->getData()));
		$this->assertSame(['--color-primary' => '#000000'], $this->overridesService->read());
	}//end testWrongValueIs400()

	/**
	 * A value that is not a string is refused with 400 instead of a type error.
	 */
	public function testNonStringValueIs400(): void {
		$this->request->method('getParams')->willReturn(
			['overrides' => ['--color-primary' => ['#112233']]]
		);

		$response = $this->controller(service: $this->overridesService)->setOverrides();

		$this->assertSame(400, $response->getStatus());
		$this->assertSame(['--color-primary' => '#000000'], $this->overridesService->read());
	}//end testNonStringValueIs400()

	/**
	 * A valid save reports the count that reached the file, and the audit entry
	 * records exactly what was written.
	 */
	public function testValidSaveReportsAndAuditsWhatWasWritten(): void {
		$this->request->method('getParams')->willReturn(
			['overrides' => ['--color-primary' => '#112233', '--color-primary-element' => '#445566']]
		);
		$this->auditService->expects($this->once())
			->method('log')
			->with(
				'overrides_written',
				[
					'old' => ['--color-primary' => '#000000'],
					'new' => ['--color-primary' => '#112233', '--color-primary-element' => '#445566'],
				]
			);

		$response = $this->controller(service: $this->overridesService)->setOverrides();

		$this->assertSame(200, $response->getStatus());
		$this->assertSame(2, $response->getData()['written']);
		$this->assertSame(
			['--color-primary' => '#112233', '--color-primary-element' => '#445566'],
			$this->overridesService->read()
		);
	}//end testValidSaveReportsAndAuditsWhatWasWritten()

	/**
	 * A write failure answers 500 with a generic message, never the file path
	 * the service puts in its exception text.
	 */
	public function testWriteFailureHidesPath(): void {
		$failing = $this->getMockBuilder(CustomOverridesService::class)
			->disableOriginalConstructor()
			->onlyMethods(['read', 'write', 'findRejected'])
			->getMock();
		$failing->method('read')->willReturn([]);
		$failing->method('findRejected')->willReturn([]);
		$failing->method('write')->willThrowException(
			new \RuntimeException('Could not write /var/www/html/custom_apps/thematiq/css/custom-overrides.css.tmp.')
		);
		$this->request->method('getParams')->willReturn(['overrides' => ['--color-primary' => '#112233']]);

		$response = $this->controller(service: $failing)->setOverrides();

		$this->assertSame(500, $response->getStatus());
		$this->assertStringNotContainsString('/var/www', json_encode($response->getData()));
		$this->assertStringNotContainsString('custom-overrides.css', json_encode($response->getData()));
	}//end testWriteFailureHidesPath()
}//end class
