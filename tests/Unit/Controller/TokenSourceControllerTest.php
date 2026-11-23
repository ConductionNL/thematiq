<?php

/**
 * Tests for TokenSourceController.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Tests
 * @package   OCA\Thematiq
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 *
 * @spec openspec/specs/multi-brand-token-sources/spec.md#requirement-a-new-version-of-a-source-updates-every-brand-together
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Controller;

use OCA\Thematiq\Controller\TokenSourceController;
use OCA\Thematiq\Service\BrandDocument;
use OCA\Thematiq\Service\CustomTokenSetValidator;
use OCA\Thematiq\Service\MultiBrandImportService;
use OCA\Thematiq\Settings\Admin;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\IL10N;
use OCP\IRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;

/**
 * Over a mocked import service and request.
 *
 * @spec openspec/specs/multi-brand-token-sources/spec.md#requirement-a-new-version-of-a-source-updates-every-brand-together
 */
final class TokenSourceControllerTest extends TestCase {

	/**
	 * The import service.
	 *
	 * @var MultiBrandImportService&MockObject
	 */
	private MultiBrandImportService&MockObject $imports;

	/**
	 * A temporary uploaded file, removed after the test.
	 *
	 * @var string|null
	 */
	private ?string $tmp = null;

	/**
	 * A fresh import service per test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->imports = $this->createMock(originalClassName: MultiBrandImportService::class);
	}//end setUp()

	/**
	 * Remove the temporary file.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		if ($this->tmp !== null && is_file(filename: $this->tmp) === true) {
			unlink(filename: $this->tmp);
		}
	}//end tearDown()

	/**
	 * The controller over a request with an optional upload and pasted content.
	 *
	 * @param array<string, mixed>|null $file    The uploaded `file`, or null.
	 * @param string                    $content The pasted `content`.
	 *
	 * @return TokenSourceController
	 */
	private function controller(?array $file = null, string $content = ''): TokenSourceController {
		$request = $this->createMock(originalClassName: IRequest::class);
		$request->method('getUploadedFile')->with('file')->willReturn($file);
		$request->method('getParam')->with('content', '')->willReturn($content);
		$l10n = $this->createMock(originalClassName: IL10N::class);
		$l10n->method('t')->willReturnCallback(fn (string $text): string => 'T:'.$text);

		return new TokenSourceController(appName: 'thematiq', request: $request, imports: $this->imports, l: $l10n);
	}//end controller()

	/**
	 * The list wraps the records in an object, so an empty list is `{}` in JSON.
	 *
	 * @return void
	 */
	public function testListReturnsTheSourcesAsAnObject(): void {
		$this->imports->method('records')->willReturn([]);
		$this->assertSame(expected: '{"sources":{}}', actual: json_encode(value: $this->controller()->list()->getData()));

		$imports = $this->createMock(originalClassName: MultiBrandImportService::class);
		$imports->method('records')->willReturn(['src1' => ['brands' => ['a']]]);
		$this->imports = $imports;
		$data = $this->controller()->list()->getData();
		$this->assertEquals(expected: (object)['src1' => ['brands' => ['a']]], actual: $data['sources']);
	}//end testListReturnsTheSourcesAsAnObject()

	/**
	 * Pasted content reaches the service without a file name, and the report comes back with status ok.
	 *
	 * @return void
	 */
	public function testUpdateWithPastedContentPassesItOn(): void {
		$this->imports->expects($this->once())->method('update')->with(
			'src1',
			$this->callback(callback: fn (BrandDocument $doc): bool => $doc->content === '{"a":1}' && $doc->fileName === null)
		)->willReturn(['updated' => ['a'], 'missing' => [], 'new' => []]);

		$response = $this->controller(content: '{"a":1}')->update(sourceId: 'src1');
		$this->assertSame(expected: 200, actual: $response->getStatus());
		$this->assertSame(expected: ['status' => 'ok', 'updated' => ['a'], 'missing' => [], 'new' => []], actual: $response->getData());
	}//end testUpdateWithPastedContentPassesItOn()

	/**
	 * An uploaded file wins over pasted content and is read from its temporary path.
	 *
	 * @return void
	 */
	public function testUpdateReadsAnUploadedFile(): void {
		$this->tmp = tempnam(directory: sys_get_temp_dir(), prefix: 'tq');
		file_put_contents(filename: $this->tmp, data: '{"from":"file"}');
		$this->imports->expects($this->once())->method('update')->with(
			'src1',
			$this->callback(callback: fn (BrandDocument $doc): bool => $doc->content === '{"from":"file"}' && $doc->fileName === 'brands.json')
		)->willReturn([]);

		$file = ['tmp_name' => $this->tmp, 'name' => 'brands.json', 'size' => 15];
		$response = $this->controller(file: $file, content: '{"from":"paste"}')->update(sourceId: 'src1');
		$this->assertSame(expected: ['status' => 'ok'], actual: $response->getData());
	}//end testUpdateReadsAnUploadedFile()

	/**
	 * A file over the limit is refused with 413 before the service is called.
	 *
	 * @return void
	 */
	public function testUpdateRefusesAnOversizedFile(): void {
		$this->imports->expects($this->never())->method('update');
		$file = ['tmp_name' => '/does/not/matter', 'name' => 'big.json', 'size' => CustomTokenSetValidator::MAX_SIZE + 1];

		$response = $this->controller(file: $file)->update(sourceId: 'src1');
		$this->assertSame(expected: 413, actual: $response->getStatus());
		$this->assertSame(expected: ['error' => 'T:The file exceeds the 512 KB size limit.'], actual: $response->getData());
	}//end testUpdateRefusesAnOversizedFile()

	/**
	 * An upload without a temporary path counts as no upload, and empty content is a 400.
	 *
	 * @return void
	 */
	public function testUpdateWithoutInputIsABadRequest(): void {
		$this->imports->expects($this->never())->method('update');

		$response = $this->controller(file: ['tmp_name' => ''], content: "  \n")->update(sourceId: 'src1');
		$this->assertSame(expected: 400, actual: $response->getStatus());
		$this->assertSame(expected: ['error' => 'T:Choose a file or paste the contents of a theme file.'], actual: $response->getData());
	}//end testUpdateWithoutInputIsABadRequest()

	/**
	 * Pasted content over the limit is refused with 413.
	 *
	 * @return void
	 */
	public function testUpdateRefusesOversizedPastedContent(): void {
		$this->imports->expects($this->never())->method('update');

		$response = $this->controller(content: str_repeat(string: 'a', times: CustomTokenSetValidator::MAX_SIZE + 1))->update(sourceId: 'src1');
		$this->assertSame(expected: 413, actual: $response->getStatus());
		$this->assertSame(expected: ['error' => 'T:Pasted content exceeds the 512 KB size limit.'], actual: $response->getData());
	}//end testUpdateRefusesOversizedPastedContent()

	/**
	 * Pasted content of exactly the limit is accepted.
	 *
	 * @return void
	 */
	public function testUpdateAcceptsPastedContentAtTheLimit(): void {
		$this->imports->expects($this->once())->method('update')->willReturn([]);

		$response = $this->controller(content: str_repeat(string: 'a', times: CustomTokenSetValidator::MAX_SIZE))->update(sourceId: 'src1');
		$this->assertSame(expected: 200, actual: $response->getStatus());
	}//end testUpdateAcceptsPastedContentAtTheLimit()

	/**
	 * The service's failure codes map to 404 (with its own text), 409, and 422 for the rest.
	 *
	 * @return array<string, array{int, int, string}>
	 */
	public static function failureProvider(): array {
		return [
			'unknown source' => [404, 404, 'T:There is no token source with this id.'],
			'conflict'       => [409, 409, 'Brand "a" is in use.'],
			'invalid'        => [0, 422, 'Brand "a" is in use.'],
			'other code'     => [500, 422, 'Brand "a" is in use.'],
		];
	}//end failureProvider()

	/**
	 * A failing update answers with the mapped status and error.
	 *
	 * @param int    $thrown   The exception code.
	 * @param int    $status   The expected HTTP status.
	 * @param string $expected The expected error.
	 *
	 * @return void
	 */
	#[DataProvider('failureProvider')]
	public function testUpdateMapsServiceFailures(int $thrown, int $status, string $expected): void {
		$this->imports->method('update')->willThrowException(new RuntimeException('Brand "a" is in use.', $thrown));

		$response = $this->controller(content: '{}')->update(sourceId: 'nope');
		$this->assertSame(expected: $status, actual: $response->getStatus());
		$this->assertSame(expected: ['error' => $expected], actual: $response->getData());
	}//end testUpdateMapsServiceFailures()

	/**
	 * Both endpoints are admin settings endpoints.
	 *
	 * @return void
	 */
	public function testBothEndpointsNeedTheAdminSetting(): void {
		foreach (['list', 'update'] as $method) {
			$attributes = (new ReflectionMethod(TokenSourceController::class, $method))->getAttributes(AuthorizedAdminSetting::class);
			$this->assertCount(expectedCount: 1, haystack: $attributes, message: $method);
			$this->assertSame(expected: ['settings' => Admin::class], actual: $attributes[0]->getArguments(), message: $method);
		}
	}//end testBothEndpointsNeedTheAdminSetting()
}//end class
