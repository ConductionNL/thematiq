<?php

/**
 * GET /icons/{name} serves the active pack's icon.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Test
 * @package   OCA\Thematiq\Tests\Unit\Controller
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Controller;

use OCA\Thematiq\Controller\IconController;
use OCA\Thematiq\Service\DesignSystemService;
use OCA\Thematiq\Service\GroupThemingService;
use OCP\App\IAppManager;
use OCP\AppFramework\Http\NotFoundResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IURLGenerator;
use PHPUnit\Framework\TestCase;

/**
 * DesignSystemService::resolveIconPath() had no caller, so choosing a design
 * system changed no icon (#663). This asserts the wiring from the caller: the
 * route is registered, and the controller drives the REAL service against a
 * pack layout on disk, so switching the request's token set switches the file.
 *
 * @spec openspec/specs/icon-packs/spec.md#requirement-icon-path-resolver-by-name
 */
class IconControllerTest extends TestCase {

	/**
	 * The temp app directory standing in for the app path.
	 *
	 * @var string
	 */
	private string $appDir;

	/**
	 * Lay out two design systems with one pack each, and a token set for each.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->appDir = sys_get_temp_dir() . '/thematiq-icon-route-' . uniqid();
		mkdir($this->appDir . '/img/icons/rvo', 0777, true);
		mkdir($this->appDir . '/img/icons/dsfr', 0777, true);
		file_put_contents($this->appDir . '/img/icons/rvo/home.svg', '<svg>rvo</svg>');
		file_put_contents($this->appDir . '/img/icons/dsfr/home.svg', '<svg>dsfr</svg>');
		file_put_contents(
			$this->appDir . '/design-systems.json',
			(string)json_encode(
				[
					['id' => 'nldesign', 'icon_pack' => ['rvo']],
					['id' => 'lasuite', 'icon_pack' => 'dsfr'],
					['id' => 'none'],
				]
			)
		);
		file_put_contents(
			$this->appDir . '/token-sets.json',
			(string)json_encode(
				[
					['id' => 'rijkshuisstijl', 'design_system' => 'nldesign'],
					['id' => 'lasuite-violet', 'design_system' => 'lasuite'],
					['id' => 'nextcloud', 'design_system' => 'none'],
				]
			)
		);
	}//end setUp()

	/**
	 * Remove the temp app directory.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		foreach (['img/icons/rvo/home.svg', 'img/icons/dsfr/home.svg', 'design-systems.json', 'token-sets.json'] as $file) {
			@unlink($this->appDir . '/' . $file);
		}

		foreach (['img/icons/rvo', 'img/icons/dsfr', 'img/icons', 'img', ''] as $dir) {
			@rmdir($this->appDir . '/' . $dir);
		}

		parent::tearDown();
	}//end tearDown()

	/**
	 * A controller whose request resolves to a given token set.
	 *
	 * @param string $tokenSet The token set the request resolves to.
	 *
	 * @return IconController The controller.
	 */
	private function controller(string $tokenSet): IconController {
		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getAppPath')->willReturn($this->appDir);
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			static fn (string $app, string $key, $default = '') => $default
		);

		$groupTheming = $this->createMock(GroupThemingService::class);
		$groupTheming->method('resolveTokenSetForRequest')->willReturn($tokenSet);

		$urls = $this->createMock(IURLGenerator::class);
		$urls->method('imagePath')->willReturnCallback(
			static fn (string $appName, string $file) => '/apps/' . $appName . '/img/' . $file
		);

		return new IconController(
			$this->createMock(IRequest::class),
			new DesignSystemService($appManager, $config),
			$groupTheming,
			$urls
		);
	}//end controller()

	/**
	 * The same name serves the file of the pack the request's design system uses.
	 *
	 * @return void
	 */
	public function testTheIconFollowsTheDesignSystem(): void {
		$nl = $this->controller(tokenSet: 'rijkshuisstijl')->show(name: 'home');
		$this->assertInstanceOf(RedirectResponse::class, $nl);
		$this->assertSame('/apps/thematiq/img/icons/rvo/home.svg', $nl->getRedirectURL());

		$fr = $this->controller(tokenSet: 'lasuite-violet')->show(name: 'home');
		$this->assertInstanceOf(RedirectResponse::class, $fr);
		$this->assertSame('/apps/thematiq/img/icons/dsfr/home.svg', $fr->getRedirectURL());
	}//end testTheIconFollowsTheDesignSystem()

	/**
	 * An unknown name, a set with no pack, and a traversal attempt answer 404.
	 *
	 * @return void
	 */
	public function testNoIconAnswers404(): void {
		$this->assertInstanceOf(NotFoundResponse::class, $this->controller(tokenSet: 'rijkshuisstijl')->show(name: 'missing'));
		$this->assertInstanceOf(NotFoundResponse::class, $this->controller(tokenSet: 'nextcloud')->show(name: 'home'));
		$this->assertInstanceOf(NotFoundResponse::class, $this->controller(tokenSet: 'rijkshuisstijl')->show(name: '..'));
	}//end testNoIconAnswers404()

	/**
	 * The route reaches the controller method.
	 *
	 * @return void
	 */
	public function testTheRouteIsRegistered(): void {
		$routes = require __DIR__ . '/../../../appinfo/routes.php';
		$this->assertContains(
			['name' => 'icon#show', 'url' => '/icons/{name}', 'verb' => 'GET'],
			$routes['routes']
		);
		$this->assertTrue(method_exists(IconController::class, 'show'));
	}//end testTheRouteIsRegistered()
}//end class
