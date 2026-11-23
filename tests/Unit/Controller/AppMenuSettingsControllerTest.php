<?php

/**
 * Tests for AppMenuSettingsController.
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
 * @spec openspec/specs/apps-menu-settings/spec.md#requirement-menu-settings-are-checked-before-they-are-stored
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Controller;

use OCA\Thematiq\Controller\AppMenuSettingsController;
use OCA\Thematiq\Service\AppMenuIcons;
use OCA\Thematiq\Service\AppMenuKeys;
use OCA\Thematiq\Service\AppMenuSettings;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\Response;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Over an in-memory config.
 *
 * @spec openspec/specs/apps-menu-settings/spec.md#requirement-menu-settings-are-checked-before-they-are-stored
 */
final class AppMenuSettingsControllerTest extends TestCase {

	/**
	 * The app values, by key.
	 *
	 * @var array<string, string>
	 */
	private array $app = [];

	/**
	 * The user values, by user and key.
	 *
	 * @var array<string, array<string, string>>
	 */
	private array $user = [];

	/**
	 * The icons the controller uses, or null for a bare mock.
	 *
	 * @var AppMenuIcons|null
	 */
	private ?AppMenuIcons $icons = null;

	/**
	 * The uploaded file the request carries, or null.
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $upload = null;

	/**
	 * The controller for a person, an administrator or nobody.
	 *
	 * @param string|null $uid The signed-in person.
	 * @param bool $admin Whether they are an administrator.
	 *
	 * @return AppMenuSettingsController
	 */
	private function controller(?string $uid, bool $admin = false): AppMenuSettingsController {
		$config = $this->createMock(originalClassName: IConfig::class);
		$config->method('getAppValue')->willReturnCallback(fn (string $app, string $key, $default = '') => ($this->app[$key] ?? $default));
		$config->method('setAppValue')->willReturnCallback(function (string $app, string $key, $value): void {
			$this->app[$key] = (string)$value;
		});
		$config->method('deleteAppValue')->willReturnCallback(function (string $app, string $key): void {
			unset($this->app[$key]);
		});
		$config->method('getUserValue')->willReturnCallback(
			fn (string $user, string $app, string $key, $default = '') => ($this->user[$user][$key] ?? $default)
		);
		$config->method('setUserValue')->willReturnCallback(function (string $user, string $app, string $key, $value): void {
			$this->user[$user][$key] = (string)$value;
		});
		$config->method('deleteUserValue')->willReturnCallback(function (string $user, string $app, string $key): void {
			unset($this->user[$user][$key]);
		});
		$session = $this->createMock(originalClassName: IUserSession::class);
		$user = null;
		if ($uid !== null) {
			$user = $this->createMock(originalClassName: IUser::class);
			$user->method('getUID')->willReturn($uid);
		}

		$session->method('getUser')->willReturn($user);
		$keys = $this->createMock(originalClassName: AppMenuKeys::class);
		$keys->method('known')->willReturn(['deck', 'files']);
		$groups = $this->createMock(originalClassName: IGroupManager::class);
		$groups->method('isAdmin')->willReturn($admin);
		$urls = $this->createMock(originalClassName: IURLGenerator::class);
		$urls->method('linkToRoute')->willReturn('/settings/apps');
		$l10n = $this->createMock(originalClassName: IL10N::class);
		$l10n->method('t')->willReturnArgument(0);
		$request = $this->createMock(originalClassName: IRequest::class);
		$request->method('getUploadedFile')->willReturn($this->upload);

		return new AppMenuSettingsController(
			appName: 'thematiq',
			request: $request,
			settings: new AppMenuSettings(config: $config),
			keys: $keys,
			icons: ($this->icons ?? $this->createMock(originalClassName: AppMenuIcons::class)),
			userSession: $session,
			groupManager: $groups,
			urls: $urls,
			l: $l10n
		);
	}//end controller()

	/**
	 * A person reads their settings without the defaults; an administrator also gets the
	 * defaults and the Manage apps address.
	 *
	 * @return void
	 */
	public function testAnAdministratorAlsoReadsTheDefaults(): void {
		$person = $this->controller(uid: 'anna')->show()->getData();
		$this->assertFalse(condition: $person['admin']);
		$this->assertArrayNotHasKey(key: 'defaults', array: $person);
		$this->assertArrayNotHasKey(key: 'manageUrl', array: $person);
		$this->assertSame(expected: 'Menu settings', actual: $person['labels']['title']);
		$this->assertArrayHasKey(key: 'news', array: $person['library']);

		$admin = $this->controller(uid: 'root', admin: true)->show()->getData();
		$this->assertSame(expected: 4, actual: $admin['defaults']['columns']);
		$this->assertSame(expected: '/settings/apps', actual: $admin['manageUrl']);
		$this->assertSame(expected: 401, actual: $this->controller(uid: null)->show()->getStatus());
	}//end testAnAdministratorAlsoReadsTheDefaults()

	/**
	 * Scenario: a person saves their own keys, may pin a new external app in the same save, and
	 * may not save a configuration key.
	 *
	 * @return void
	 */
	public function testAPersonSavesOnlyTheirOwnKeys(): void {
		$app = [
			'id' => 'intra',
			'title' => 'Intranet',
			'payoff' => '',
			'url' => 'https://intranet.example.org',
			'icon' => ['library' => 'news'],
			'visible' => true,
			'order' => 0,
		];
		$response = $this->controller(uid: 'anna')->save(settings: ['pinned' => ['deck', 'external-intra'], 'externalApps' => [$app]]);
		$this->assertSame(expected: 200, actual: $response->getStatus());
		$this->assertSame(expected: ['deck', 'external-intra'], actual: $response->getData()['effective']['pinned']);
		$this->assertSame(expected: [], actual: $this->app);

		$refused = $this->controller(uid: 'anna')->save(settings: ['columns' => 3]);
		$this->assertSame(expected: 400, actual: $refused->getStatus());
		$this->assertSame(expected: 'columns', actual: $refused->getData()['field']);
		$bad = $this->controller(uid: 'anna')->save(settings: ['externalApps' => [['url' => 'http://x.org'] + $app]]);
		$this->assertSame(expected: 'externalApps', actual: $bad->getData()['field']);
		$this->assertSame(expected: 401, actual: $this->controller(uid: null)->save(settings: [])->getStatus());
	}//end testAPersonSavesOnlyTheirOwnKeys()

	/**
	 * An administrator sets and resets the defaults; a person resets only their own keys.
	 *
	 * @return void
	 */
	public function testDefaultsAndResets(): void {
		$saved = $this->controller(uid: 'root', admin: true)->saveDefaults(settings: ['columns' => 3, 'iconColour' => 'neutral']);
		$this->assertSame(expected: 3, actual: $saved->getData()['defaults']['columns']);
		$this->assertSame(expected: 400, actual: $this->controller(uid: 'root', admin: true)->saveDefaults(settings: ['columns' => 9])->getStatus());

		$this->controller(uid: 'anna')->save(settings: ['hidden' => ['deck']]);
		$this->assertSame(expected: [], actual: $this->controller(uid: 'anna')->reset()->getData()['effective']['hidden']);
		$this->assertSame(expected: 3, actual: $this->controller(uid: 'anna')->show()->getData()['effective']['columns']);

		$this->controller(uid: 'root', admin: true)->resetDefaults();
		$this->assertSame(expected: [], actual: $this->app);
	}//end testDefaultsAndResets()

	/**
	 * The defaults are an administrator's; the person's endpoints are any signed-in user's,
	 * with the CSRF check except for reading an icon.
	 *
	 * @return void
	 */
	public function testWhoMayCallWhat(): void {
		foreach (['saveDefaults', 'resetDefaults'] as $name) {
			$method = new ReflectionMethod(AppMenuSettingsController::class, $name);
			$this->assertCount(expectedCount: 1, haystack: $method->getAttributes(AuthorizedAdminSetting::class), message: $name);
			$this->assertCount(expectedCount: 0, haystack: $method->getAttributes(NoAdminRequired::class), message: $name);
		}

		foreach (['show', 'save', 'reset', 'uploadIcon', 'icon'] as $name) {
			$method = new ReflectionMethod(AppMenuSettingsController::class, $name);
			$this->assertCount(expectedCount: 1, haystack: $method->getAttributes(NoAdminRequired::class), message: $name);
			$this->assertCount(expectedCount: 0, haystack: $method->getAttributes(PublicPage::class), message: $name);
			$csrf = 0;
			if ($name === 'icon') {
				$csrf = 1;
			}

			$this->assertCount(expectedCount: $csrf, haystack: $method->getAttributes(NoCSRFRequired::class), message: $name);
		}
	}//end testWhoMayCallWhat()

	/**
	 * An icon is served so it can run nothing; a missing one is a 404.
	 *
	 * @return void
	 */
	public function testAnIconIsServedSafely(): void {
		$this->assertSame(expected: 404, actual: $this->controller(uid: 'anna')->icon(id: str_repeat('a', 32))->getStatus());

		$this->icons = $this->createMock(originalClassName: AppMenuIcons::class);
		$this->icons->method('read')->willReturn(['bytes' => '<svg/>', 'mime' => 'image/svg+xml']);
		$served = $this->controller(uid: 'anna')->icon(id: str_repeat('a', 32));
		$this->assertSame(expected: 200, actual: $served->getStatus());
		$headers = (new ReflectionProperty(Response::class, 'headers'))->getValue($served);
		$this->assertSame(expected: 'image/svg+xml', actual: $headers['Content-Type']);
		$this->assertSame(expected: 'nosniff', actual: $headers['X-Content-Type-Options']);
		$this->assertStringStartsWith(prefix: "default-src 'none'", string: $headers['Content-Security-Policy']);
	}//end testAnIconIsServedSafely()

	/**
	 * An upload without a file is a 400, a refused image a 422, a kept one its id.
	 *
	 * @return void
	 */
	public function testAnUploadedIconIsKeptOrRefused(): void {
		$this->assertSame(expected: 400, actual: $this->controller(uid: 'anna')->uploadIcon()->getStatus());

		$tmp = (string)tempnam(sys_get_temp_dir(), 'icon');
		file_put_contents($tmp, 'bytes');
		$this->upload = ['tmp_name' => $tmp, 'error' => 0];
		$this->icons = $this->createMock(originalClassName: AppMenuIcons::class);
		$this->icons->method('store')->willReturnOnConsecutiveCalls(null, str_repeat('b', 32));
		$controller = $this->controller(uid: 'anna');
		$this->assertSame(expected: 422, actual: $controller->uploadIcon()->getStatus());
		$this->assertSame(expected: ['id' => str_repeat('b', 32)], actual: $controller->uploadIcon()->getData());
		unlink($tmp);
	}//end testAnUploadedIconIsKeptOrRefused()
}//end class
