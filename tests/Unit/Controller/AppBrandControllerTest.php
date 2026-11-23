<?php

/**
 * Tests for AppBrandController.
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
 * @spec openspec/specs/per-app-theming/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Controller;

use OCA\Thematiq\Controller\AppBrandController;
use OCA\Thematiq\Service\AppBrandService;
use OCA\Thematiq\Service\AppBrandSources;
use OCA\Thematiq\Service\AppThemingService;
use OCA\Thematiq\Service\Exception\AppBrandException;
use OCA\Thematiq\Service\TokenSetService;
use OCA\Thematiq\Settings\Admin;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\Response;
use OCP\IL10N;
use OCP\IRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Over mocked brand, app theming and token set services.
 *
 * @spec openspec/specs/per-app-theming/spec.md
 */
final class AppBrandControllerTest extends TestCase {

	/**
	 * The brands.
	 *
	 * @var AppBrandService&MockObject
	 */
	private AppBrandService&MockObject $brands;

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
	 * Fresh doubles per test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->brands = $this->createMock(originalClassName: AppBrandService::class);
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
	 * The controller.
	 *
	 * @return AppBrandController
	 */
	private function controller(): AppBrandController {
		$appTheming = $this->createMock(originalClassName: AppThemingService::class);
		$appTheming->method('getThemableApps')->willReturn(
			[
				['id' => 'files', 'themed' => true],
				['id' => 'settings', 'themed' => false],
				['id' => 'deck', 'themed' => true],
			]
		);
		$tokenSets = $this->createMock(originalClassName: TokenSetService::class);
		$tokenSets->method('getPublicCatalogue')->willReturn([['id' => 'zuiddrecht']]);
		$l10n = $this->createMock(originalClassName: IL10N::class);
		$l10n->method('t')->willReturnCallback(fn (string $text): string => 'T:' . $text);

		return new AppBrandController(
			appName: 'thematiq',
			request: $this->request,
			sources: new AppBrandSources(brands: $this->brands, appTheming: $appTheming, tokenSets: $tokenSets),
			l10n: $l10n
		);
	}//end controller()

	/**
	 * The index lists the brands as an object, only the themed apps, and the sets.
	 *
	 * @return void
	 */
	public function testIndexListsBrandsThemedAppsAndSets(): void {
		$this->brands->method('getBrands')->willReturn([]);

		$data = $this->controller()->index()->getData();

		$this->assertSame(expected: '{}', actual: json_encode(value: $data['brands']));
		$this->assertSame(expected: ['files', 'deck'], actual: array_column($data['apps'], 'id'));
		$this->assertSame(expected: [0, 1], actual: array_keys($data['apps']));
		$this->assertSame(expected: [['id' => 'zuiddrecht']], actual: $data['tokenSets']);
	}//end testIndexListsBrandsThemedAppsAndSets()

	/**
	 * A saved brand answers with what the service stored.
	 *
	 * @return void
	 */
	public function testSaveAnswersWithTheStoredBrand(): void {
		$this->brands->expects($this->once())->method('setBrand')->with('deck', 'zuiddrecht')->willReturn(['deck' => ['tokenSet' => 'zuiddrecht']]);

		$response = $this->controller()->save(appId: 'deck', tokenSet: 'zuiddrecht');
		$this->assertSame(expected: 200, actual: $response->getStatus());
		$this->assertSame(expected: ['deck' => ['tokenSet' => 'zuiddrecht']], actual: $response->getData());
	}//end testSaveAnswersWithTheStoredBrand()

	/**
	 * Every refusal reason with its status and text.
	 *
	 * @return array<string, array{string, int, string}>
	 */
	public static function refusalProvider(): array {
		return [
			'protected' => [AppBrandException::PROTECTED, 403, 'T:The settings pages always follow the house style. This app cannot get its own brand.'],
			'not installed' => [AppBrandException::NOT_INSTALLED, 404, 'T:This app is not installed.'],
			'excluded' => [AppBrandException::EXCLUDED, 422, 'T:This app is excluded from theming. Include it under Theming per app first.'],
			'unknown set' => [AppBrandException::UNKNOWN_SET, 422, 'T:This token set is not available.'],
			'restricted set' => [
				AppBrandException::RESTRICTED_SET,
				409,
				'T:Confirm first that your organisation may use this house style. You do that under House styles of other organisations.',
			],
			'other reason' => ['something-new', 422, 'something-new'],
		];
	}//end refusalProvider()

	/**
	 * A refused brand answers with the exception's status, the reason and its text.
	 *
	 * @param string $reason The reason.
	 * @param int $status The status.
	 * @param string $expected The text.
	 *
	 * @return void
	 */
	#[DataProvider('refusalProvider')]
	public function testSaveRefusalCarriesReasonStatusAndText(string $reason, int $status, string $expected): void {
		$this->brands->method('setBrand')->willThrowException(new AppBrandException(reason: $reason, status: $status));

		$response = $this->controller()->save(appId: 'settings', tokenSet: 'x');
		$this->assertSame(expected: $status, actual: $response->getStatus());
		$this->assertSame(expected: ['error' => $expected, 'reason' => $reason], actual: $response->getData());
	}//end testSaveRefusalCarriesReasonStatusAndText()

	/**
	 * Removing a brand asks the service and confirms.
	 *
	 * @return void
	 */
	public function testRemoveRemovesTheBrand(): void {
		$this->brands->expects($this->once())->method('removeBrand')->with('deck');

		$this->assertSame(expected: ['removed' => true], actual: $this->controller()->remove(appId: 'deck')->getData());
	}//end testRemoveRemovesTheBrand()

	/**
	 * Uploads that are not a usable file.
	 *
	 * @return array<string, array{mixed}>
	 */
	public static function noFileProvider(): array {
		return [
			'nothing' => [null],
			'no tmp_name' => [['name' => 'logo.png']],
			'an upload error' => [['tmp_name' => '/tmp/x', 'error' => 1]],
		];
	}//end noFileProvider()

	/**
	 * Without a usable upload the answer is 400 and nothing is stored.
	 *
	 * @param mixed $file The uploaded file.
	 *
	 * @return void
	 */
	#[DataProvider('noFileProvider')]
	public function testUploadWithoutAFileIsABadRequest(mixed $file): void {
		$this->request->method('getUploadedFile')->with('file')->willReturn($file);
		$this->brands->expects($this->never())->method('storeLogo');

		$response = $this->controller()->uploadLogo(appId: 'deck', size: 'large');
		$this->assertSame(expected: 400, actual: $response->getStatus());
		$this->assertSame(expected: ['error' => 'T:No file uploaded.'], actual: $response->getData());
	}//end testUploadWithoutAFileIsABadRequest()

	/**
	 * An upload stores the file's bytes for the app and size.
	 *
	 * @return void
	 */
	public function testUploadStoresTheLogo(): void {
		$this->tmp = tempnam(directory: sys_get_temp_dir(), prefix: 'tq');
		file_put_contents(filename: $this->tmp, data: 'PNG');
		$this->request->method('getUploadedFile')->willReturn(['tmp_name' => $this->tmp, 'error' => 0]);
		$this->brands->expects($this->once())->method('storeLogo')->with('deck', 'small', 'PNG')->willReturn(['deck' => ['small' => true]]);

		$this->assertSame(expected: ['deck' => ['small' => true]], actual: $this->controller()->uploadLogo(appId: 'deck', size: 'small')->getData());
	}//end testUploadStoresTheLogo()

	/**
	 * A refused logo carries the store's reason.
	 *
	 * @return void
	 */
	public function testUploadRefusalCarriesTheReason(): void {
		$this->tmp = tempnam(directory: sys_get_temp_dir(), prefix: 'tq');
		$this->request->method('getUploadedFile')->willReturn(['tmp_name' => $this->tmp]);
		$this->brands->method('storeLogo')->willThrowException(new AppBrandException(reason: AppBrandException::TOO_LARGE, status: 413));

		$response = $this->controller()->uploadLogo(appId: 'deck', size: 'large');
		$this->assertSame(expected: 413, actual: $response->getStatus());
		$this->assertSame(expected: ['error' => 'T:The logo is larger than 1 MB.', 'reason' => 'too-large'], actual: $response->getData());

		$this->brands = $this->createMock(originalClassName: AppBrandService::class);
		$this->brands->method('storeLogo')->willThrowException(new AppBrandException(reason: AppBrandException::BAD_IMAGE));
		$response = $this->controller()->uploadLogo(appId: 'deck', size: 'large');
		$this->assertSame(expected: 'T:Upload a PNG, JPEG or WebP image, or an SVG without script.', actual: $response->getData()['error']);
	}//end testUploadRefusalCarriesTheReason()

	/**
	 * A logo is served with its type and the headers that keep an SVG inert; a missing one is a bare 404.
	 *
	 * @return void
	 */
	public function testLogoServesTheImageOrIsNotFound(): void {
		$this->brands->method('readLogo')->willReturnMap(
			[
				['deck', 'large', ['bytes' => '<svg/>', 'mime' => 'image/svg+xml']],
				['files', 'large', null],
			]
		);

		$response = $this->controller()->logo(appId: 'deck', size: 'large');
		$this->assertInstanceOf(expected: DataDisplayResponse::class, actual: $response);
		$this->assertSame(expected: '<svg/>', actual: $response->getData());
		$headers = (new ReflectionProperty(Response::class, 'headers'))->getValue($response);
		$this->assertSame(expected: 'image/svg+xml', actual: $headers['Content-Type']);
		$this->assertSame(expected: 'nosniff', actual: $headers['X-Content-Type-Options']);
		$this->assertSame(expected: "default-src 'none'; style-src 'unsafe-inline'; img-src data:", actual: $headers['Content-Security-Policy']);
		$this->assertSame(expected: 'private, max-age=86400', actual: $headers['Cache-Control']);

		$missing = $this->controller()->logo(appId: 'files', size: 'large');
		$this->assertNotInstanceOf(expected: DataDisplayResponse::class, actual: $missing);
		$this->assertSame(expected: 404, actual: $missing->getStatus());
	}//end testLogoServesTheImageOrIsNotFound()

	/**
	 * The logo is for every signed-in user; managing brands is an admin setting.
	 *
	 * @return void
	 */
	public function testTheEndpointsCarryTheirAuthorisation(): void {
		$logo = new ReflectionMethod(AppBrandController::class, 'logo');
		$this->assertCount(expectedCount: 1, haystack: $logo->getAttributes(NoAdminRequired::class));

		foreach (['index', 'save', 'remove', 'uploadLogo'] as $method) {
			$reflection = new ReflectionMethod(AppBrandController::class, $method);
			$attributes = $reflection->getAttributes(AuthorizedAdminSetting::class);
			$this->assertCount(expectedCount: 1, haystack: $attributes, message: $method);
			$this->assertSame(expected: [Admin::class], actual: $attributes[0]->getArguments(), message: $method);
			$this->assertSame(expected: [], actual: $reflection->getAttributes(NoAdminRequired::class), message: $method);
		}
	}//end testTheEndpointsCarryTheirAuthorisation()
}//end class
