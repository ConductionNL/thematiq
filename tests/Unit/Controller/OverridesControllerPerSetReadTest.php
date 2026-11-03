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

		$this->request = $this->createMock(originalClassName: IRequest::class);
		$this->overrides = $this->createMock(originalClassName: CustomOverridesService::class);

		$this->controller = new OverridesController(
			appName: 'thematiq',
			request: $this->request,
			overridesService: $this->overrides,
			cssParser: new CssParserService(),
			auditService: $this->createMock(originalClassName: ThemingAuditService::class),
			config: $this->createMock(originalClassName: IConfig::class),
			themingService: $this->createMock(originalClassName: ThemingService::class)
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
	 *
	 * @return void
	 */
	public function testReadsTheNamedSetsOverrides(): void {
		$this->asking(tokenSet: 'custom-openwoo');
		$this->overrides->expects($this->once())
			->method('read')
			->with('custom-openwoo')
			->willReturn(['--color-primary' => '#112233']);

		$data = $this->controller->getOverrides()->getData();

		$this->assertSame(expected: ['--color-primary' => '#112233'], actual: $data['overrides']);
		$this->assertNotEmpty(actual: $data['registry']);
		$this->assertNotEmpty(actual: $data['tabs']);
	}//end testReadsTheNamedSetsOverrides()

	/**
	 * Anything but a non-empty string is no set named at all.
	 *
	 * @param mixed $tokenSet The parameter's value.
	 *
	 * @dataProvider noSetProvider
	 *
	 * @return void
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
	 *
	 * @return void
	 */
	public function testExportsTheNamedSetsFile(): void {
		$this->asking(tokenSet: 'custom-openwoo');
		$this->overrides->expects($this->once())
			->method('getRawContent')
			->with('custom-openwoo')
			->willReturn(":root {\n  --color-primary: #112233;\n}\n");

		$response = $this->controller->exportOverrides();

		// The response's own headers: Response::getHeaders() merges in request headers through
		// Server::get(), which needs a Nextcloud server.
		$headers = (new \ReflectionProperty(\OCP\AppFramework\Http\Response::class, 'headers'))->getValue($response);
		$this->assertSame(expected: 'text/css', actual: $headers['Content-Type']);
		$this->assertStringContainsString(needle: 'custom-overrides.css', haystack: $headers['Content-Disposition']);
	}//end testExportsTheNamedSetsFile()
}//end class
