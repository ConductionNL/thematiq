<?php

/**
 * Tests for DocumentStyleController.
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
 * @spec openspec/specs/document-house-style/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Controller;

use OCA\Thematiq\Controller\DocumentStyleController;
use OCA\Thematiq\Kernel\Contracts\DocumentAssetServiceInterface;
use OCA\Thematiq\Service\DocumentStyleService;
use OCA\Thematiq\Settings\Admin;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;

/**
 * Over a mocked profile, asset store and request.
 *
 * @spec openspec/specs/document-house-style/spec.md
 */
final class DocumentStyleControllerTest extends TestCase {

	/**
	 * The profile.
	 *
	 * @var DocumentStyleService&MockObject
	 */
	private DocumentStyleService&MockObject $style;

	/**
	 * The document assets.
	 *
	 * @var DocumentAssetServiceInterface&MockObject
	 */
	private DocumentAssetServiceInterface&MockObject $assets;

	/**
	 * The request.
	 *
	 * @var IRequest&MockObject
	 */
	private IRequest&MockObject $request;

	/**
	 * A temporary uploaded file, removed after the test.
	 *
	 * @var string|null
	 */
	private ?string $tmp = null;

	/**
	 * Fresh collaborators per test; the settings read answers with fixed values.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->style = $this->createMock(originalClassName: DocumentStyleService::class);
		$this->style->method('forUser')->willReturnCallback(fn (?string $uid): array => ['for' => $uid]);
		$this->assets = $this->createMock(originalClassName: DocumentAssetServiceInterface::class);
		$this->assets->method('getFooterLine')->willReturn('Zuiddrecht');
		$this->assets->method('getAssets')->willReturn(['logo' => ['mime' => 'image/png']]);
		$this->request = $this->createMock(originalClassName: IRequest::class);
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
	 * The controller, with a signed-in user or none.
	 *
	 * @param string|null $uid The signed-in user's id.
	 *
	 * @return DocumentStyleController
	 */
	private function controller(?string $uid = 'anna'): DocumentStyleController {
		$session = $this->createMock(originalClassName: IUserSession::class);
		$user = null;
		if ($uid !== null) {
			$user = $this->createMock(originalClassName: IUser::class);
			$user->method('getUID')->willReturn($uid);
		}

		$session->method('getUser')->willReturn($user);
		$l10n = $this->createMock(originalClassName: IL10N::class);
		$l10n->method('t')->willReturnCallback(fn (string $text): string => 'T:' . $text);

		return new DocumentStyleController(
			appName: 'thematiq',
			request: $this->request,
			style: $this->style,
			assets: $this->assets,
			userSession: $session,
			l10n: $l10n
		);
	}//end controller()

	/**
	 * The settings answer the block reads.
	 *
	 * @return array<string, mixed>
	 */
	private static function settings(): array {
		return ['footerLine' => 'Zuiddrecht', 'assets' => ['logo' => ['mime' => 'image/png']], 'profile' => ['for' => null]];
	}//end settings()

	/**
	 * The profile is the signed-in user's, and without a user it is the instance's.
	 *
	 * @return void
	 */
	public function testShowReturnsTheProfileOfTheSignedInUser(): void {
		$this->assertSame(expected: ['for' => 'anna'], actual: $this->controller()->show()->getData());
		$this->assertSame(expected: ['for' => null], actual: $this->controller(uid: null)->show()->getData());
	}//end testShowReturnsTheProfileOfTheSignedInUser()

	/**
	 * An asset is served with its type and the headers that keep an SVG inert.
	 *
	 * @return void
	 */
	public function testAssetServesTheImageWithHardenedHeaders(): void {
		$this->assets->method('read')->with('logo')->willReturn(['bytes' => '<svg/>', 'mime' => 'image/svg+xml']);

		$response = $this->controller()->asset(kind: 'logo');
		$this->assertInstanceOf(expected: DataDisplayResponse::class, actual: $response);
		$this->assertSame(expected: 200, actual: $response->getStatus());
		$this->assertSame(expected: '<svg/>', actual: $response->getData());
		$headers = (new ReflectionProperty(Response::class, 'headers'))->getValue($response);
		$this->assertSame(expected: 'image/svg+xml', actual: $headers['Content-Type']);
		$this->assertSame(expected: 'nosniff', actual: $headers['X-Content-Type-Options']);
		$this->assertSame(expected: "default-src 'none'; style-src 'unsafe-inline'; img-src data:", actual: $headers['Content-Security-Policy']);
		$this->assertSame(expected: 'private, max-age=3600', actual: $headers['Cache-Control']);
	}//end testAssetServesTheImageWithHardenedHeaders()

	/**
	 * A missing asset is a bare 404.
	 *
	 * @return void
	 */
	public function testAssetIsNotFoundWithoutAFile(): void {
		$this->assets->method('read')->willReturn(null);

		$response = $this->controller()->asset(kind: 'cover');
		$this->assertNotInstanceOf(expected: DataDisplayResponse::class, actual: $response);
		$this->assertSame(expected: 404, actual: $response->getStatus());
	}//end testAssetIsNotFoundWithoutAFile()

	/**
	 * The settings carry the footer line, the assets and the instance profile.
	 *
	 * @return void
	 */
	public function testSettingsReturnsTheBlock(): void {
		$this->assertSame(expected: self::settings(), actual: $this->controller()->settings()->getData());
	}//end testSettingsReturnsTheBlock()

	/**
	 * A saved footer line answers with the settings.
	 *
	 * @return void
	 */
	public function testSaveFooterStoresTheLineAndAnswersWithTheSettings(): void {
		$this->assets->expects($this->once())->method('setFooterLine')->with('Gemeente Zuiddrecht')->willReturn('Gemeente Zuiddrecht');

		$response = $this->controller()->saveFooter(footerLine: 'Gemeente Zuiddrecht');
		$this->assertSame(expected: 200, actual: $response->getStatus());
		$this->assertSame(expected: self::settings(), actual: $response->getData());
	}//end testSaveFooterStoresTheLineAndAnswersWithTheSettings()

	/**
	 * A refused footer line is a 422 with the rule.
	 *
	 * @return void
	 */
	public function testSaveFooterRefusesAnInvalidLine(): void {
		$this->assets->method('setFooterLine')->willThrowException(new RuntimeException('too long'));

		$response = $this->controller()->saveFooter(footerLine: str_repeat(string: 'x', times: 201));
		$this->assertSame(expected: 422, actual: $response->getStatus());
		$this->assertSame(expected: ['error' => 'T:The footer line is one line of at most 200 characters.'], actual: $response->getData());
	}//end testSaveFooterRefusesAnInvalidLine()

	/**
	 * The uploads that are not a file are refused with 400 before anything is stored.
	 *
	 * @return array<string, array{mixed}>
	 */
	public static function noFileProvider(): array {
		return [
			'nothing' => [null],
			'empty' => [[]],
			'no tmp_name' => [['name' => 'logo.png']],
			'an upload error' => [['tmp_name' => '/tmp/x', 'error' => 4]],
		];
	}//end noFileProvider()

	/**
	 * Without a usable upload the answer is 400.
	 *
	 * @param mixed $file The uploaded file.
	 *
	 * @return void
	 */
	#[DataProvider('noFileProvider')]
	public function testUploadWithoutAFileIsABadRequest(mixed $file): void {
		$this->request->method('getUploadedFile')->with('file')->willReturn($file);
		$this->assets->expects($this->never())->method('store');

		$response = $this->controller()->upload(kind: 'logo');
		$this->assertSame(expected: 400, actual: $response->getStatus());
		$this->assertSame(expected: ['error' => 'T:No file uploaded.'], actual: $response->getData());
	}//end testUploadWithoutAFileIsABadRequest()

	/**
	 * An upload stores the file's bytes and answers with the settings.
	 *
	 * @return void
	 */
	public function testUploadStoresTheBytes(): void {
		$this->tmp = tempnam(directory: sys_get_temp_dir(), prefix: 'tq');
		file_put_contents(filename: $this->tmp, data: 'PNGDATA');
		$this->request->method('getUploadedFile')->willReturn(['tmp_name' => $this->tmp, 'error' => 0]);
		$this->assets->expects($this->once())->method('store')->with('cover', 'PNGDATA')->willReturn([]);

		$response = $this->controller()->upload(kind: 'cover');
		$this->assertSame(expected: 200, actual: $response->getStatus());
		$this->assertSame(expected: self::settings(), actual: $response->getData());
	}//end testUploadStoresTheBytes()

	/**
	 * A refused upload answers with the store's code and its reason.
	 *
	 * @return array<string, array{int, string}>
	 */
	public static function refusedUploadProvider(): array {
		return [
			'too large' => [413, 'T:The file is larger than 2 MB.'],
			'unknown kind' => [404, 'T:Unknown document image.'],
			'not an image' => [422, 'T:Upload a PNG, JPEG or WebP image, or an SVG without script.'],
		];
	}//end refusedUploadProvider()

	/**
	 * A refused upload carries the store's status and the matching reason.
	 *
	 * @param int $code The store's error code.
	 * @param string $expected The expected reason.
	 *
	 * @return void
	 */
	#[DataProvider('refusedUploadProvider')]
	public function testUploadReportsTheStoresRefusal(int $code, string $expected): void {
		$this->tmp = tempnam(directory: sys_get_temp_dir(), prefix: 'tq');
		$this->request->method('getUploadedFile')->willReturn(['tmp_name' => $this->tmp]);
		$this->assets->method('store')->willThrowException(new RuntimeException('refused', $code));

		$response = $this->controller()->upload(kind: 'logo');
		$this->assertSame(expected: $code, actual: $response->getStatus());
		$this->assertSame(expected: ['error' => $expected], actual: $response->getData());
	}//end testUploadReportsTheStoresRefusal()

	/**
	 * A store failure without an HTTP code (a storage error, code 0) answers 422, never a status of 0.
	 *
	 * @return void
	 */
	public function testAStoreFailureWithoutAStatusCodeIsA422(): void {
		$this->tmp = tempnam(directory: sys_get_temp_dir(), prefix: 'tq');
		$this->request->method('getUploadedFile')->willReturn(['tmp_name' => $this->tmp]);
		$this->assets->method('store')->willThrowException(new RuntimeException('disk full'));

		$response = $this->controller()->upload(kind: 'logo');
		$this->assertSame(expected: 422, actual: $response->getStatus());
		$this->assertSame(expected: ['error' => 'T:Upload a PNG, JPEG or WebP image, or an SVG without script.'], actual: $response->getData());
	}//end testAStoreFailureWithoutAStatusCodeIsA422()

	/**
	 * Removing an asset answers with the settings, and an unknown one with 404.
	 *
	 * @return void
	 */
	public function testRemoveDeletesOrIsNotFound(): void {
		$this->assets->expects($this->exactly(count: 2))->method('delete')->willReturnCallback(
			function (string $kind): void {
				if ($kind !== 'logo') {
					throw new RuntimeException('unknown');
				}
			}
		);

		$removed = $this->controller()->remove(kind: 'logo');
		$this->assertSame(expected: self::settings(), actual: $removed->getData());

		$missing = $this->controller()->remove(kind: 'banner');
		$this->assertSame(expected: 404, actual: $missing->getStatus());
		$this->assertSame(expected: ['error' => 'T:Unknown document image.'], actual: $missing->getData());
	}//end testRemoveDeletesOrIsNotFound()

	/**
	 * Reading is for every signed-in user; managing is an admin setting.
	 *
	 * @return void
	 */
	public function testTheEndpointsCarryTheirAuthorisation(): void {
		foreach (['show', 'asset'] as $method) {
			$reflection = new ReflectionMethod(DocumentStyleController::class, $method);
			$this->assertCount(expectedCount: 1, haystack: $reflection->getAttributes(NoAdminRequired::class), message: $method);
		}

		foreach (['settings', 'saveFooter', 'upload', 'remove'] as $method) {
			$reflection = new ReflectionMethod(DocumentStyleController::class, $method);
			$attributes = $reflection->getAttributes(AuthorizedAdminSetting::class);
			$this->assertCount(expectedCount: 1, haystack: $attributes, message: $method);
			$this->assertSame(expected: [Admin::class], actual: $attributes[0]->getArguments(), message: $method);
			$this->assertSame(expected: [], actual: $reflection->getAttributes(NoAdminRequired::class), message: $method);
		}

		$this->assertInstanceOf(expected: JSONResponse::class, actual: $this->controller()->settings());
	}//end testTheEndpointsCarryTheirAuthorisation()
}//end class
