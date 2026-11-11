<?php

/**
 * Unit tests for FontController.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/custom-fonts/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Controller;

use OCA\Thematiq\Controller\FontController;
use OCA\Thematiq\Service\FontService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\IL10N;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;

/**
 * Unit tests for the font controller.
 *
 * Covers tasks.md#task-5.3: upload maps validator errors to 413/422/409,
 * serve returns immutable cache headers + an ETag and 404 for an unknown
 * id, the css endpoint returns text/css (empty stylesheet with no fonts),
 * and the auth posture is asserted by reflection: upload/list/delete carry
 * AuthorizedAdminSetting, serve/css carry PublicPage + NoCSRFRequired.
 */
class FontControllerTest extends TestCase {

	/**
	 * The mocked font service.
	 *
	 * @var FontService&\PHPUnit\Framework\MockObject\MockObject
	 */
	private FontService $service;

	/**
	 * The mocked request.
	 *
	 * @var IRequest&\PHPUnit\Framework\MockObject\MockObject
	 */
	private IRequest $request;

	/**
	 * The controller under test.
	 *
	 * @var FontController
	 */
	private FontController $controller;

	/**
	 * A temp file standing in for an uploaded font's tmp_name.
	 *
	 * @var string
	 */
	private string $tmpUploadPath;

	/**
	 * Set up mocked collaborators before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->service = $this->createMock(originalClassName: FontService::class);

		$l = $this->createMock(originalClassName: IL10N::class);
		$l->method('t')->willReturnCallback(fn (string $text, $params = []) => $text);

		$this->request = $this->createMock(originalClassName: IRequest::class);

		$this->tmpUploadPath = sys_get_temp_dir() . '/nldesign-font-upload-' . uniqid();
		file_put_contents($this->tmpUploadPath, "wOF2\x00\x01payload");

		$this->controller = new FontController(appName: 'nldesign', request: $this->request, service: $this->service, l: $l);
	}//end setUp()

	/**
	 * Remove the temp upload file after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		if (file_exists($this->tmpUploadPath) === true) {
			unlink($this->tmpUploadPath);
		}
		parent::tearDown();
	}//end tearDown()

	/**
	 * Read a Response's raw headers array via reflection.
	 *
	 * `Response::getHeaders()` merges in server-dependent defaults
	 * (`Server::get(IRequest::class)` for `X-Request-Id`, a live user
	 * session, …) that need a full Nextcloud bootstrap and are unrelated to
	 * what this controller sets — so header assertions read the private
	 * `$headers` property directly instead of calling the live-container
	 * dependent public accessor.
	 *
	 * @param \OCP\AppFramework\Http\Response $response The response.
	 *
	 * @return array<string, mixed> The raw headers this controller set.
	 */
	private function rawHeaders($response): array {
		$prop = new ReflectionProperty(\OCP\AppFramework\Http\Response::class, 'headers');
		$prop->setAccessible(true);

		return $prop->getValue($response);
	}//end rawHeaders()

	/**
	 * Stub the request to return a well-formed uploaded file of the given
	 * size, plus name/role params.
	 *
	 * @param int $size The reported upload size.
	 *
	 * @return void
	 */
	private function stubUpload(int $size): void {
		$this->request->method('getParam')->willReturnMap([
			['name', '', 'Rijks Sans'],
			['role', 'body', 'body'],
		]);
		$this->request->method('getUploadedFile')->willReturn([
			'tmp_name' => $this->tmpUploadPath,
			'name' => 'font.woff2',
			'size' => $size,
		]);
	}//end stubUpload()

	/**
	 * A successful upload returns the stored id.
	 *
	 * @return void
	 */
	public function testUploadSuccessReturnsId(): void {
		$this->stubUpload(size: 20);
		$this->service->method('store')->willReturn(['id' => 'custom-rijks-sans']);

		$response = $this->controller->upload();

		$this->assertSame(expected: 200, actual: $response->getStatus());
		$this->assertSame(expected: ['id' => 'custom-rijks-sans'], actual: $response->getData());
	}//end testUploadSuccessReturnsId()

	/**
	 * Missing uploaded file returns 400.
	 *
	 * @return void
	 */
	public function testUploadNoFileReturns400(): void {
		$this->request->method('getParam')->willReturnMap([
			['name', '', 'Rijks Sans'],
			['role', 'body', 'body'],
		]);
		$this->request->method('getUploadedFile')->willReturn(null);

		$response = $this->controller->upload();

		$this->assertSame(expected: 400, actual: $response->getStatus());
	}//end testUploadNoFileReturns400()

	/**
	 * A reported size over the 2 MB cap is rejected with 413 before the
	 * service is even invoked.
	 *
	 * @return void
	 */
	public function testUploadOversizeReturns413(): void {
		$this->stubUpload(size: ((2 * 1024 * 1024) + 1));
		$this->service->expects($this->never())->method('store');

		$response = $this->controller->upload();

		$this->assertSame(expected: 413, actual: $response->getStatus());
	}//end testUploadOversizeReturns413()

	/**
	 * A validator/service RuntimeException(422) surfaces as a 422 response.
	 *
	 * @return void
	 */
	public function testUploadServiceRejectsBadNameAs422(): void {
		$this->stubUpload(size: 20);
		$this->service->method('store')->willThrowException(
			new RuntimeException('A font name must contain at least one letter or digit.', 422)
		);

		$response = $this->controller->upload();

		$this->assertSame(expected: 422, actual: $response->getStatus());
	}//end testUploadServiceRejectsBadNameAs422()

	/**
	 * A collision (RuntimeException 409) surfaces as a 409 response.
	 *
	 * @return void
	 */
	public function testUploadServiceRejectsCollisionAs409(): void {
		$this->stubUpload(size: 20);
		$this->service->method('store')->willThrowException(
			new RuntimeException('A font named "Rijks Sans" already exists. Delete it first.', 409)
		);

		$response = $this->controller->upload();

		$this->assertSame(expected: 409, actual: $response->getStatus());
	}//end testUploadServiceRejectsCollisionAs409()

	/**
	 * A non-HTTP exception code is clamped to 500.
	 *
	 * @return void
	 */
	public function testUploadServiceUnexpectedCodeClampsTo500(): void {
		$this->stubUpload(size: 20);
		$this->service->method('store')->willThrowException(new RuntimeException('boom', 0));

		$response = $this->controller->upload();

		$this->assertSame(expected: 500, actual: $response->getStatus());
	}//end testUploadServiceUnexpectedCodeClampsTo500()

	/**
	 * list() returns the service's font list.
	 *
	 * @return void
	 */
	public function testListReturnsFonts(): void {
		$this->service->method('list')->willReturn([['id' => 'custom-rijks-sans', 'name' => 'Rijks Sans']]);

		$response = $this->controller->list();

		$this->assertSame(expected: ['fonts' => [['id' => 'custom-rijks-sans', 'name' => 'Rijks Sans']]], actual: $response->getData());
	}//end testListReturnsFonts()

	/**
	 * delete() returns ok on success.
	 *
	 * @return void
	 */
	public function testDeleteSuccess(): void {
		$this->service->method('delete')->with('custom-rijks-sans')->willReturn(true);

		$response = $this->controller->delete(id: 'custom-rijks-sans');

		$this->assertSame(expected: ['status' => 'ok'], actual: $response->getData());
	}//end testDeleteSuccess()

	/**
	 * delete() returns 404 for an unknown id.
	 *
	 * @return void
	 */
	public function testDeleteNotFoundReturns404(): void {
		$this->service->method('delete')->willReturn(false);

		$response = $this->controller->delete(id: 'custom-ghost');

		$this->assertSame(expected: 404, actual: $response->getStatus());
	}//end testDeleteNotFoundReturns404()

	/**
	 * serve() returns the font bytes with immutable cache headers and an
	 * ETag derived from the manifest revision.
	 *
	 * @return void
	 */
	public function testServeReturnsImmutableCacheHeadersAndETag(): void {
		$this->service->method('readFontBytes')->with('custom-rijks-sans')->willReturn('wOF2payload');
		$this->service->method('getEntry')->willReturn(['rev' => 3]);

		$response = $this->controller->serve(id: 'custom-rijks-sans');

		$this->assertSame(expected: 200, actual: $response->getStatus());
		$headers = $this->rawHeaders(response: $response);
		$this->assertArrayHasKey(key: 'Cache-Control', array: $headers);
		$this->assertStringContainsString(needle: 'public', haystack: $headers['Cache-Control']);
		$this->assertStringContainsString(needle: 'max-age=31536000', haystack: $headers['Cache-Control']);
		$this->assertStringContainsString(needle: 'immutable', haystack: $headers['Cache-Control']);
		$this->assertSame(expected: '3', actual: $response->getETag());
	}//end testServeReturnsImmutableCacheHeadersAndETag()

	/**
	 * serve() returns a bare 404 for an unknown id.
	 *
	 * @return void
	 */
	public function testServeReturns404ForUnknownId(): void {
		$this->service->method('readFontBytes')->willReturn(null);

		$response = $this->controller->serve(id: 'custom-ghost');

		$this->assertSame(expected: 404, actual: $response->getStatus());
	}//end testServeReturns404ForUnknownId()

	/**
	 * css() returns text/css with an empty body when no fonts exist.
	 *
	 * @return void
	 */
	public function testCssEmptyStylesheetWhenNoFonts(): void {
		$this->service->method('buildCss')->willReturn('');
		$this->service->method('getRevision')->willReturn(0);

		$response = $this->controller->css();

		$this->assertSame(expected: 200, actual: $response->getStatus());
		$this->assertSame(expected: 'text/css', actual: $this->rawHeaders(response: $response)['Content-Type']);
		$this->assertSame(expected: '', actual: $response->render());
	}//end testCssEmptyStylesheetWhenNoFonts()

	/**
	 * css() returns the generated stylesheet with the revision as ETag.
	 *
	 * @return void
	 */
	public function testCssReturnsGeneratedStylesheet(): void {
		$this->service->method('buildCss')->willReturn('@font-face { font-family: "Rijks Sans"; }');
		$this->service->method('getRevision')->willReturn(2);

		$response = $this->controller->css();

		$this->assertStringContainsString(needle: '@font-face', haystack: $response->render());
		$this->assertSame(expected: '2', actual: $response->getETag());
	}//end testCssReturnsGeneratedStylesheet()

	/**
	 * upload/list/delete are admin-only (AuthorizedAdminSetting).
	 *
	 * @return void
	 */
	public function testUploadListDeleteCarryAuthorizedAdminSetting(): void {
		foreach (['upload', 'list', 'delete'] as $method) {
			$ref = new ReflectionMethod(FontController::class, $method);
			$this->assertNotEmpty(
				actual: $ref->getAttributes(AuthorizedAdminSetting::class),
				message: $method . '() must carry #[AuthorizedAdminSetting]'
			);
		}
	}//end testUploadListDeleteCarryAuthorizedAdminSetting()

	/**
	 * serve/css are deliberately public (PublicPage + NoCSRFRequired).
	 *
	 * @return void
	 */
	public function testServeAndCssCarryPublicPage(): void {
		foreach (['serve', 'css'] as $method) {
			$ref = new ReflectionMethod(FontController::class, $method);
			$this->assertNotEmpty(
				actual: $ref->getAttributes(PublicPage::class),
				message: $method . '() must carry #[PublicPage]'
			);
			$this->assertNotEmpty(
				actual: $ref->getAttributes(NoCSRFRequired::class),
				message: $method . '() must carry #[NoCSRFRequired]'
			);
		}
	}//end testServeAndCssCarryPublicPage()
	/**
	 * Every upload refusal with its exact answer, and the arguments a stored upload passes on.
	 *
	 * @return void
	 */
	public function testEveryUploadAnswerExactly(): void {
		$cases = [
			[[], [400, 'No font file uploaded.']],
			[['size' => 1], [400, 'No font file uploaded.']],
			[['tmp_name' => $this->tmpUploadPath, 'size' => (2 * 1024 * 1024 + 1)], [413, 'Font file exceeds the 2 MB size limit.']],
			[['tmp_name' => $this->tmpUploadPath . '-missing', 'size' => 1], [400, 'Could not read the uploaded file.']],
		];
		foreach ($cases as [$file, $answer]) {
			$request = $this->createMock(originalClassName: IRequest::class);
			$request->method('getParam')->willReturnMap([['name', '', ' Rijks Sans '], ['role', 'body', ' heading ']]);
			$request->method('getUploadedFile')->willReturn($file);
			$service = $this->createMock(originalClassName: FontService::class);
			$service->expects($this->never())->method('store');
			$l = $this->createMock(originalClassName: IL10N::class);
			$l->method('t')->willReturnCallback(fn (string $text) => $text);
			$response = @(new FontController(appName: 'thematiq', request: $request, service: $service, l: $l))->upload();
			$this->assertSame(expected: $answer, actual: [$response->getStatus(), $response->getData()['error']], message: (string)json_encode($file));
		}

		foreach ([[['tmp_name' => $this->tmpUploadPath, 'size' => 7], 7], [['tmp_name' => $this->tmpUploadPath], 13]] as [$file, $size]) {
			$request = $this->createMock(originalClassName: IRequest::class);
			$request->method('getParam')->willReturnMap([['name', '', ' Rijks Sans '], ['role', 'body', ' heading ']]);
			$request->method('getUploadedFile')->willReturn($file);
			$service = $this->createMock(originalClassName: FontService::class);
			$service->expects($this->once())->method('store')->with('Rijks Sans', 'heading', "wOF2\x00\x01payload", $size)->willReturn(['id' => 'custom-x', 'other' => 1]);
			$l = $this->createMock(originalClassName: IL10N::class);
			$response = (new FontController(appName: 'thematiq', request: $request, service: $service, l: $l))->upload();
			$this->assertSame(expected: [200, ['id' => 'custom-x']], actual: [$response->getStatus(), $response->getData()]);
		}
	}//end testEveryUploadAnswerExactly()
}//end class
