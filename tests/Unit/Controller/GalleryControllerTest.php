<?php

/**
 * Unit tests for GalleryController: browse, toggle and install, admin-only.
 *
 * @category Test
 * @package  OCA\Thematiq\Tests\Unit\Controller
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/specs/theme-gallery/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Controller;

use OCA\Thematiq\Controller\GalleryController;
use OCA\Thematiq\Service\Exception\GalleryException;
use OCA\Thematiq\Service\GalleryInstallService;
use OCA\Thematiq\Service\ThemeGalleryService;
use OCA\Thematiq\Settings\Admin;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the gallery endpoints.
 */
class GalleryControllerTest extends TestCase {

	/**
	 * The gallery mock.
	 *
	 * @var ThemeGalleryService&MockObject
	 */
	private ThemeGalleryService $gallery;

	/**
	 * The installer mock.
	 *
	 * @var GalleryInstallService&MockObject
	 */
	private GalleryInstallService $installer;

	/**
	 * The request mock.
	 *
	 * @var IRequest&MockObject
	 */
	private IRequest $request;

	/**
	 * The controller under test.
	 *
	 * @var GalleryController
	 */
	private GalleryController $controller;

	/**
	 * Set up.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->gallery = $this->createMock(originalClassName: ThemeGalleryService::class);
		$this->installer = $this->createMock(originalClassName: GalleryInstallService::class);
		$this->request = $this->createMock(originalClassName: IRequest::class);
		$this->controller = new GalleryController(appName: 'thematiq', request: $this->request, gallery: $this->gallery, installer: $this->installer);
	}//end setUp()

	/**
	 * Browsing returns what the gallery service says.
	 *
	 * @return void
	 */
	public function testIndexReturnsTheBrowseResult(): void {
		$this->gallery->method('browse')->willReturn(['enabled' => false, 'host' => 'raw.githubusercontent.com', 'reachable' => null, 'entries' => []]);

		$response = $this->controller->index();

		$this->assertSame(expected: 200, actual: $response->getStatus());
		$this->assertSame(expected: 'raw.githubusercontent.com', actual: $response->getData()['host']);
	}//end testIndexReturnsTheBrowseResult()

	/**
	 * The toggle stores a boolean and answers with the new browse result.
	 *
	 * @return void
	 */
	public function testSetEnabledStoresTheToggle(): void {
		$this->request->method('getParam')->with('enabled')->willReturn(true);
		$this->gallery->expects($this->once())->method('setEnabled')->with(true);
		$this->gallery->method('browse')->willReturn(['enabled' => true, 'host' => 'h', 'reachable' => true, 'entries' => []]);

		$this->assertTrue(condition: $this->controller->setEnabled()->getData()['enabled']);
	}//end testSetEnabledStoresTheToggle()

	/**
	 * An install answers with the stored set.
	 *
	 * @return void
	 */
	public function testInstallAnswersWithTheStoredSet(): void {
		$this->installer->method('install')->with('provincie-utrecht')->willReturn(['id' => 'custom-provincie-utrecht', 'updated' => false]);

		$response = $this->controller->install(id: 'provincie-utrecht');

		$this->assertSame(expected: 200, actual: $response->getStatus());
		$this->assertSame(expected: 'custom-provincie-utrecht', actual: $response->getData()['id']);
	}//end testInstallAnswersWithTheStoredSet()

	/**
	 * A refused install answers with its status and reason.
	 *
	 * @return void
	 */
	public function testARefusedInstallAnswersWithTheReason(): void {
		$this->installer->method('install')->willThrowException(new GalleryException(
			message: 'The downloaded file does not match the gallery index.',
			code: 422
		));

		$response = $this->controller->install(id: 'provincie-utrecht');

		$this->assertSame(expected: 422, actual: $response->getStatus());
		$this->assertSame(expected: 'The downloaded file does not match the gallery index.', actual: $response->getData()['error']);
	}//end testARefusedInstallAnswersWithTheReason()

	/**
	 * Scenario "A non-admin cannot install": every endpoint carries the admin attribute.
	 *
	 * @return void
	 */
	public function testEveryEndpointIsAdminOnly(): void {
		foreach (['index', 'setEnabled', 'install'] as $method) {
			$attributes = (new \ReflectionMethod(GalleryController::class, $method))->getAttributes(AuthorizedAdminSetting::class);
			$this->assertNotEmpty(actual: $attributes, message: "GalleryController::{$method}() must carry #[AuthorizedAdminSetting]");
			$this->assertSame(expected: Admin::class, actual: $attributes[0]->getArguments()[0]);
		}
	}//end testEveryEndpointIsAdminOnly()

	/**
	 * The three routes exist.
	 *
	 * @return void
	 */
	public function testTheRoutesAreRegistered(): void {
		$routes = require __DIR__ . '/../../../appinfo/routes.php';
		$found = [];
		foreach ($routes['routes'] as $route) {
			if (str_starts_with($route['name'], 'gallery#') === true) {
				$found[$route['name']] = $route['verb'] . ' ' . $route['url'];
			}
		}

		$this->assertSame(
			expected: [
				'gallery#index' => 'GET /settings/gallery',
				'gallery#setEnabled' => 'POST /settings/gallery',
				'gallery#install' => 'POST /settings/gallery/{id}/install',
			],
			actual: $found
		);
	}//end testTheRoutesAreRegistered()
}//end class
