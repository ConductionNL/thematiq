<?php

/**
 * Unit tests for OverridesController keeping Nextcloud's branding with a theme.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Controller;

use OCA\Thematiq\Controller\OverridesController;
use OCA\Thematiq\Service\BrandingCaptureService;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\CustomOverridesService;
use OCA\Thematiq\Service\ThemingAuditService;
use OCA\Thematiq\Service\ThemingService;
use OCP\IConfig;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;

/**
 * Saving overrides with `captureTheming` copies Nextcloud's branding into the
 * theme being saved — never into the stock set, whose branding is Nextcloud's
 * own settings — and hands the captured block back to the editor.
 */
class OverridesControllerCaptureTest extends TestCase {

	/**
	 * The mocked request.
	 *
	 * @var IRequest&\PHPUnit\Framework\MockObject\MockObject
	 */
	private IRequest $request;

	/**
	 * The mocked app config.
	 *
	 * @var IConfig&\PHPUnit\Framework\MockObject\MockObject
	 */
	private IConfig $config;

	/**
	 * The mocked branding capture.
	 *
	 * @var BrandingCaptureService&\PHPUnit\Framework\MockObject\MockObject
	 */
	private BrandingCaptureService $capture;

	/**
	 * The controller under test.
	 *
	 * @var OverridesController
	 */
	private OverridesController $controller;

	protected function setUp(): void {
		parent::setUp();

		$this->request = $this->createMock(IRequest::class);
		$this->config = $this->createMock(IConfig::class);
		$this->capture = $this->createMock(BrandingCaptureService::class);

		$this->controller = new OverridesController(
			'thematiq',
			$this->request,
			$this->createMock(CustomOverridesService::class),
			new CssParserService(),
			$this->createMock(ThemingAuditService::class),
			$this->config,
			$this->createMock(ThemingService::class),
			$this->capture
		);
	}//end setUp()

	/**
	 * Point the request at a set, asking for the branding to be captured.
	 *
	 * @param string $tokenSet The `tokenSet` parameter ('' for none).
	 *
	 * @return void
	 */
	private function saving(string $tokenSet): void {
		$this->request->method('getParams')->willReturn(['overrides' => ['--color-primary' => '#112233']]);
		$this->request->method('getParam')->willReturnMap(
			[
				['tokenSet', '', $tokenSet],
				['captureTheming', false, true],
			]
		);
	}//end saving()

	/**
	 * The theme being saved gets the branding, and the editor gets it back.
	 */
	public function testCapturesIntoTheSavedTheme(): void {
		$this->saving(tokenSet: 'custom-openwoo');
		$this->capture->expects($this->once())
			->method('capture')
			->with('custom-openwoo')
			->willReturn(['captured' => true, 'primary_color' => '#23845c']);

		$response = $this->controller->setOverrides();

		$this->assertSame(200, $response->getStatus());
		$this->assertSame(['captured' => true, 'primary_color' => '#23845c'], $response->getData()['theming']);
	}//end testCapturesIntoTheSavedTheme()

	/**
	 * The stock set never captures: its branding is Nextcloud's own.
	 */
	public function testTheStockSetIsNeverCaptured(): void {
		$this->saving(tokenSet: 'nextcloud');
		$this->capture->expects($this->never())->method('capture');

		$response = $this->controller->setOverrides();

		$this->assertArrayNotHasKey('theming', $response->getData());
	}//end testTheStockSetIsNeverCaptured()

	/**
	 * Without a set named, the instance's active set is the one saved.
	 */
	public function testNoSetNamedCapturesIntoTheActiveSet(): void {
		$this->saving(tokenSet: '');
		$this->config->method('getAppValue')->willReturn('amsterdam');
		$this->capture->expects($this->once())->method('capture')->with('amsterdam')->willReturn(['captured' => true]);

		$this->controller->setOverrides();
	}//end testNoSetNamedCapturesIntoTheActiveSet()
}//end class
