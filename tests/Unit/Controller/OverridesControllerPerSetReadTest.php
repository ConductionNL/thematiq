<?php

/**
 * Unit tests for which set's overrides OverridesController reads and exports.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
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
 * The editor asks for the overrides of the set it is showing, which need not
 * be the instance's active one. Reading and exporting follow the `tokenSet`
 * parameter; without one — or with something that is not a set id — the
 * service decides, which means the active set.
 */
class OverridesControllerPerSetReadTest extends TestCase {

	/**
	 * The mocked request.
	 *
	 * @var IRequest&\PHPUnit\Framework\MockObject\MockObject
	 */
	private IRequest $request;

	/**
	 * The mocked overrides service.
	 *
	 * @var CustomOverridesService&\PHPUnit\Framework\MockObject\MockObject
	 */
	private CustomOverridesService $overrides;

	/**
	 * The controller under test.
	 *
	 * @var OverridesController
	 */
	private OverridesController $controller;

	protected function setUp(): void {
		parent::setUp();

		$this->request = $this->createMock(IRequest::class);
		$this->overrides = $this->createMock(CustomOverridesService::class);

		$this->controller = new OverridesController(
			'thematiq',
			$this->request,
			$this->overrides,
			new CssParserService(),
			$this->createMock(ThemingAuditService::class),
			$this->createMock(IConfig::class),
			$this->createMock(ThemingService::class)
		);
	}//end setUp()

	/**
	 * Send `tokenSet` with the request.
	 *
	 * @param mixed $tokenSet The parameter's value.
	 *
	 * @return void
	 */
	private function asking($tokenSet): void {
		$this->request->method('getParam')->willReturnMap([['tokenSet', '', $tokenSet]]);
	}//end asking()

	/**
	 * The named set's overrides come back with the registry the editor
	 * renders them against.
	 */
	public function testReadsTheNamedSetsOverrides(): void {
		$this->asking(tokenSet: 'custom-openwoo');
		$this->overrides->expects($this->once())
			->method('read')
			->with('custom-openwoo')
			->willReturn(['--color-primary' => '#112233']);

		$data = $this->controller->getOverrides()->getData();

		$this->assertSame(['--color-primary' => '#112233'], $data['overrides']);
		$this->assertNotEmpty($data['registry']);
		$this->assertNotEmpty($data['tabs']);
	}//end testReadsTheNamedSetsOverrides()

	/**
	 * Anything but a non-empty string is no set named at all.
	 *
	 * @param mixed $tokenSet The parameter's value.
	 *
	 * @dataProvider noSetProvider
	 */
	public function testNoSetNamedLeavesTheChoiceToTheService($tokenSet): void {
		$this->asking(tokenSet: $tokenSet);
		$this->overrides->expects($this->once())->method('read')->with(null)->willReturn([]);

		$this->controller->getOverrides();
	}//end testNoSetNamedLeavesTheChoiceToTheService()

	/**
	 * Parameter values that name no set.
	 *
	 * @return array<string, array{0: mixed}>
	 */
	public static function noSetProvider(): array {
		return [
			'empty' => [''],
			'not a string' => [['custom-openwoo']],
		];
	}//end noSetProvider()

	/**
	 * The export is the named set's file, as a CSS download.
	 */
	public function testExportsTheNamedSetsFile(): void {
		$this->asking(tokenSet: 'custom-openwoo');
		$this->overrides->expects($this->once())
			->method('getRawContent')
			->with('custom-openwoo')
			->willReturn(":root {\n  --color-primary: #112233;\n}\n");

		$response = $this->controller->exportOverrides();

		$this->assertSame('text/css', $response->getHeaders()['Content-Type']);
		$this->assertStringContainsString('custom-overrides.css', $response->getHeaders()['Content-Disposition']);
	}//end testExportsTheNamedSetsFile()
}//end class
