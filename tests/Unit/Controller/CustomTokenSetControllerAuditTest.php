<?php

/**
 * Unit tests for CustomTokenSetController's theming-audit call-site wiring.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/theming-audit-log/tasks.md#task-5.3
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Controller;

use OCA\Thematiq\Controller\CustomTokenSetController;
use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\CustomTokenSetService;
use OCA\Thematiq\Service\CustomTokenSetValidator;
use OCA\Thematiq\Service\DesignTokensMapper;
use OCA\Thematiq\Service\FontService;
use OCA\Thematiq\Service\ThemingAuditService;
use OCA\Thematiq\Service\ThemingService;
use OCA\Thematiq\Service\TokenSetConverterService;
use OCP\App\IAppManager;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Covers tasks.md#task-2.3 / #task-5.3: upload() logs one
 * custom_set_uploaded entry (id, name, declaration count, content hash) and
 * delete() logs one custom_set_deleted entry that records whether the
 * delete reset the active token set to `nextcloud`.
 */
class CustomTokenSetControllerAuditTest extends TestCase {

	/**
	 * In-memory appconfig store: key => value.
	 *
	 * @var array<string, string>
	 */
	private array $appConfig = ['token_set' => 'nextcloud'];

	/**
	 * The mocked storage/lifecycle service.
	 *
	 * @var CustomTokenSetService&\PHPUnit\Framework\MockObject\MockObject
	 */
	private CustomTokenSetService $service;

	/**
	 * The mocked audit service.
	 *
	 * @var ThemingAuditService&\PHPUnit\Framework\MockObject\MockObject
	 */
	private ThemingAuditService $auditService;

	/**
	 * Core theming, so the delete path's undo can be asserted.
	 *
	 * @var ThemingService&\PHPUnit\Framework\MockObject\MockObject
	 */
	private ThemingService $themingService;

	/**
	 * The mocked request.
	 *
	 * @var IRequest&\PHPUnit\Framework\MockObject\MockObject
	 */
	private IRequest $request;

	/**
	 * The controller under test.
	 *
	 * @var CustomTokenSetController
	 */
	private CustomTokenSetController $controller;

	protected function setUp(): void {
		parent::setUp();

		$this->service = $this->createMock(CustomTokenSetService::class);
		$this->auditService = $this->createMock(ThemingAuditService::class);
		$this->themingService = $this->createMock(ThemingService::class);
		$this->request = $this->createMock(IRequest::class);

		$l = $this->createMock(IL10N::class);
		$l->method('t')->willReturnCallback(fn (string $text, array $params = []) => $text);

		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			fn (string $app, string $key, $default = '') => ($this->appConfig[$key] ?? $default)
		);

		// A REAL converter: every upload now runs through it before the
		// validator, so a bare mock returns null and the controller has no CSS
		// to validate. Its app path is the repo root, which is where the
		// mapping table it reads lives.
		$repoAppManager = $this->createMock(IAppManager::class);
		$repoAppManager->method('getAppPath')->willReturn(dirname(__DIR__, 3));
		$converter = new TokenSetConverterService(
			$repoAppManager,
			new CssParserService(),
			new ContrastService(),
			new DesignTokensMapper(),
			$this->createMock(FontService::class),
			$this->createMock(LoggerInterface::class)
		);

		$this->controller = new CustomTokenSetController(
			'nldesign',
			$this->request,
			$this->service,
			new CustomTokenSetValidator(),
			new CssParserService(),
			$l,
			$this->auditService,
			$config,
			$converter,
			$this->themingService
		);
	}//end setUp()

	/**
	 * A successful CSS upload logs one custom_set_uploaded entry with the
	 * id, name, declaration count, and a hashed content identifier.
	 */
	public function testUploadLogsOneEntry(): void {
		$css = ':root { --nldesign-color-primary: #007bc7; }';

		$this->request->method('getParam')->willReturnCallback(
			function (string $key, $default = null) {
				if ($key === 'name') {
					return 'Gemeente Voorbeeld';
				}

				return $default;
			}
		);
		$this->request->method('getUploadedFile')->willReturn(
			[
				'tmp_name' => $this->writeTempCssFile(css: $css),
				'name' => 'gemeente.css',
				'size' => strlen($css),
			]
		);

		$this->service->method('slugify')->willReturn('gemeente-voorbeeld');
		$this->service->method('store')->willReturn(['id' => 'custom-gemeente-voorbeeld', 'warnings' => []]);
		$this->service->method('getRawContent')->willReturn($css);

		$this->auditService->expects($this->once())
			->method('log')
			->with(
				'custom_set_uploaded',
				$this->callback(function (array $context): bool {
					return ($context['id'] === 'custom-gemeente-voorbeeld')
						&& ($context['name'] === 'Gemeente Voorbeeld')
						&& ($context['declarationCount'] === 1)
						&& str_starts_with($context['contentHash'], 'sha256:');
				})
			);

		$response = $this->controller->upload();

		$this->assertSame(200, $response->getStatus());
	}//end testUploadLogsOneEntry()

	/**
	 * Deleting the currently active custom set logs custom_set_deleted with
	 * activeReset === true.
	 */
	public function testDeleteActiveSetLogsActiveReset(): void {
		$this->appConfig['token_set'] = 'custom-gemeente-voorbeeld';

		$this->service->method('isCustomId')->willReturn(true);
		$this->service->method('getRawContent')->willReturn(':root { --nldesign-color-primary: #007bc7; }');
		$this->service->method('delete')->willReturn(true);

		$this->auditService->expects($this->once())
			->method('log')
			->with(
				'custom_set_deleted',
				$this->callback(function (array $context): bool {
					return ($context['id'] === 'custom-gemeente-voorbeeld')
						&& ($context['activeReset'] === true)
						&& str_starts_with($context['contentHash'], 'sha256:');
				})
			);

		$response = $this->controller->delete(id: 'custom-gemeente-voorbeeld');

		$this->assertSame(200, $response->getStatus());
	}//end testDeleteActiveSetLogsActiveReset()

	/**
	 * Deleting the ACTIVE custom set also undoes what that set pushed into
	 * Nextcloud's own theming.
	 *
	 * Resetting `token_set` alone left the deleted set's primary colour and
	 * logo on the login page, in e-mails and in the mobile apps, with no set
	 * left in the dropdown to explain where they came from.
	 */
	public function testDeleteActiveSetResetsCoreTheming(): void {
		$this->appConfig['token_set'] = 'custom-gemeente-voorbeeld';

		$this->service->method('isCustomId')->willReturn(true);
		$this->service->method('getRawContent')->willReturn(':root { --nldesign-color-primary: #007bc7; }');
		$this->service->method('delete')->willReturn(true);

		$this->themingService->expects($this->once())->method('resetToDefaults');

		$this->controller->delete(id: 'custom-gemeente-voorbeeld');
	}//end testDeleteActiveSetResetsCoreTheming()

	/**
	 * Deleting a set that is NOT active leaves core theming alone — it belongs
	 * to whichever set is still applied.
	 */
	public function testDeleteInactiveSetLeavesCoreThemingAlone(): void {
		$this->appConfig['token_set'] = 'nextcloud';

		$this->service->method('isCustomId')->willReturn(true);
		$this->service->method('getRawContent')->willReturn(':root { --nldesign-color-primary: #007bc7; }');
		$this->service->method('delete')->willReturn(true);

		$this->themingService->expects($this->never())->method('resetToDefaults');

		$this->controller->delete(id: 'custom-gemeente-voorbeeld');
	}//end testDeleteInactiveSetLeavesCoreThemingAlone()

	/**
	 * Deleting a non-active custom set logs activeReset === false.
	 */
	public function testDeleteInactiveSetLogsNoActiveReset(): void {
		$this->appConfig['token_set'] = 'nextcloud';

		$this->service->method('isCustomId')->willReturn(true);
		$this->service->method('getRawContent')->willReturn(':root { --nldesign-color-primary: #007bc7; }');
		$this->service->method('delete')->willReturn(true);

		$this->auditService->expects($this->once())
			->method('log')
			->with(
				'custom_set_deleted',
				$this->callback(fn (array $context): bool => ($context['activeReset'] === false))
			);

		$this->controller->delete(id: 'custom-gemeente-voorbeeld');
	}//end testDeleteInactiveSetLogsNoActiveReset()

	/**
	 * A 404 (nothing to delete) logs nothing.
	 */
	public function testDeleteNotFoundLogsNothing(): void {
		$this->service->method('isCustomId')->willReturn(true);
		$this->service->method('getRawContent')->willReturn(null);
		$this->service->method('delete')->willReturn(false);

		$this->auditService->expects($this->never())->method('log');

		$response = $this->controller->delete(id: 'custom-does-not-exist');

		$this->assertSame(404, $response->getStatus());
	}//end testDeleteNotFoundLogsNothing()

	/**
	 * Write a CSS string to a temp file for getUploadedFile()['tmp_name'].
	 *
	 * @param string $css The CSS content.
	 *
	 * @return string The temp file path.
	 */
	private function writeTempCssFile(string $css): string {
		$path = tempnam(sys_get_temp_dir(), 'nldesign-customset-');
		file_put_contents($path, $css);

		return $path;
	}//end writeTempCssFile()
}//end class
