<?php

/**
 * Tests for PreferencesController.
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
 * @spec openspec/changes/apps-menu-walkthrough/specs/apps-menu-walkthrough/spec.md#requirement-finishing-or-closing-records-the-seen-version-req-tqwt-004
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Controller;

use OCA\Thematiq\Controller\PreferencesController;
use OCA\Thematiq\Service\WalkthroughPreferences;
use OCP\App\IAppManager;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Over an in-memory user config.
 *
 * @spec openspec/changes/apps-menu-walkthrough/specs/apps-menu-walkthrough/spec.md#requirement-finishing-or-closing-records-the-seen-version-req-tqwt-004
 */
final class PreferencesControllerTest extends TestCase {

	/**
	 * The stored user values, by user id and key joined with a slash.
	 *
	 * @var array<string, string>
	 */
	private array $stored = [];

	/**
	 * The controller with a signed-in user, or none.
	 *
	 * @param string|null $uid The signed-in user's id.
	 *
	 * @return PreferencesController
	 */
	private function controller(?string $uid): PreferencesController {
		$config = $this->createMock(originalClassName: IConfig::class);
		$config->method('getUserValue')->willReturnCallback(
			fn (string $user, string $app, string $key, $default = '') => ($this->stored[$user.'/'.$key] ?? $default)
		);
		$config->method('setUserValue')->willReturnCallback(function (string $user, string $app, string $key, $value): void {
			$this->stored[$user.'/'.$key] = (string)$value;
		});
		$session = $this->createMock(originalClassName: IUserSession::class);
		$user = null;
		if ($uid !== null) {
			$user = $this->createMock(originalClassName: IUser::class);
			$user->method('getUID')->willReturn($uid);
		}

		$session->method('getUser')->willReturn($user);

		return new PreferencesController(
			appName: 'thematiq',
			request: $this->createMock(originalClassName: IRequest::class),
			preferences: new WalkthroughPreferences(config: $config, appManager: $this->createMock(originalClassName: IAppManager::class)),
			userSession: $session
		);
	}//end controller()

	/**
	 * Scenario: one person's tour state is not another's. The routes take no user id and need a
	 * session, without being admin-only.
	 *
	 * @return void
	 */
	public function testItWritesOnlyTheSignedInUser(): void {
		$response = $this->controller(uid: 'anna')->update(key: WalkthroughPreferences::SEEN, value: '1.2.49');
		$this->assertSame(expected: 200, actual: $response->getStatus());
		$this->assertSame(expected: ['value' => '1.2.49'], actual: $response->getData());
		$this->assertSame(expected: ['anna/'.WalkthroughPreferences::SEEN => '1.2.49'], actual: $this->stored);

		$this->assertSame(expected: ['value' => '1.2.49'], actual: $this->controller(uid: 'anna')->show(key: WalkthroughPreferences::SEEN)->getData());
		$this->assertSame(expected: ['value' => null], actual: $this->controller(uid: 'bram')->show(key: WalkthroughPreferences::SEEN)->getData());

		foreach (['show', 'update'] as $method) {
			$reflection = new ReflectionMethod(PreferencesController::class, $method);
			$names = array_map(static fn ($param): string => $param->getName(), $reflection->getParameters());
			$this->assertNotContains(needle: 'userId', haystack: $names);
			$this->assertNotEmpty(actual: $reflection->getAttributes(NoAdminRequired::class));
			$this->assertEmpty(actual: $reflection->getAttributes(PublicPage::class));
			$this->assertEmpty(actual: $reflection->getAttributes(NoCSRFRequired::class));
		}
	}//end testItWritesOnlyTheSignedInUser()

	/**
	 * Scenario: only the two tour keys are served. Another key answers 404 and stores nothing.
	 *
	 * @return void
	 */
	public function testOnlyTheTourKeysAreServed(): void {
		$response = $this->controller(uid: 'anna')->update(key: 'token_set', value: 'rijkshuisstijl');
		$this->assertSame(expected: 404, actual: $response->getStatus());
		$this->assertSame(expected: 404, actual: $this->controller(uid: 'anna')->show(key: 'token_set')->getStatus());
		$this->assertSame(expected: [], actual: $this->stored);
	}//end testOnlyTheTourKeysAreServed()

	/**
	 * Without a signed-in user the routes answer 401; a value that is not text answers 400.
	 *
	 * @return void
	 */
	public function testItNeedsASessionAndText(): void {
		$this->assertSame(expected: 401, actual: $this->controller(uid: null)->show(key: WalkthroughPreferences::SEEN)->getStatus());
		$this->assertSame(expected: 401, actual: $this->controller(uid: null)->update(key: WalkthroughPreferences::SEEN, value: '1.2.49')->getStatus());
		$this->assertSame(expected: 400, actual: $this->controller(uid: 'anna')->update(key: WalkthroughPreferences::PROGRESS, value: ['tourId' => 'x'])->getStatus());
		$this->assertSame(expected: [], actual: $this->stored);
	}//end testItNeedsASessionAndText()
}//end class
