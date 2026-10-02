<?php

/**
 * Unit tests for delegated group house styles: the mapping fields, the subadmin
 * choice, the admin endpoints, the subadmin endpoints and the personal section.
 *
 * GroupThemingService is the real class on an in-memory app config, so a choice is
 * proven to land in the mapping the resolution reads, not only to call a method.
 *
 * @category Test
 * @package  OCA\Thematiq\Tests\Unit\Service
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/specs/per-group-theming/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Controller\MyGroupsController;
use OCA\Thematiq\Controller\SettingsController;
use OCA\Thematiq\Service\ActiveTokenSetService;
use OCA\Thematiq\Service\AppThemingService;
use OCA\Thematiq\Service\ComplianceReportService;
use OCA\Thematiq\Service\DelegatedGroupThemingService;
use OCA\Thematiq\Service\EmailThemingService;
use OCA\Thematiq\Service\Exception\DelegationRefusedException;
use OCA\Thematiq\Service\Exception\GroupThemingValidationException;
use OCA\Thematiq\Service\GroupThemingService;
use OCA\Thematiq\Service\ThemePreviewService;
use OCA\Thematiq\Service\ThemingAuditService;
use OCA\Thematiq\Service\ThemingService;
use OCA\Thematiq\Service\TokenSetPreviewService;
use OCA\Thematiq\Service\TokenSetService;
use OCA\Thematiq\Service\UpstreamFreshnessService;
use OCA\Thematiq\Settings\Personal;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\Group\ISubAdmin;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IConfig;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Tests for delegated group house styles.
 */
class DelegatedGroupThemingServiceTest extends TestCase {

	/**
	 * In-memory app config.
	 *
	 * @var array<string, string>
	 */
	private array $app = [];

	/**
	 * Subadmin pairs, as "uid:group".
	 *
	 * @var array<int, string>
	 */
	private array $subAdmins = ['anna:gemeente-a'];

	/**
	 * Audit entries written.
	 *
	 * @var array<int, array{0: string, 1: array<string, mixed>}>
	 */
	private array $audited = [];

	/**
	 * The signed-in user.
	 *
	 * @var string|null
	 */
	private ?string $sessionUid = 'anna';

	/**
	 * The available token sets.
	 *
	 * @var array<int, string>
	 */
	private const SETS = ['rijkshuisstijl', 'gemeente-a-huisstijl', 'amsterdam', 'concern'];

	/**
	 * Seed a mapping: gemeente-a delegated, gemeente-b delegated, concern locked.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->app['group_token_sets'] = json_encode(
			[
				['group' => 'gemeente-a', 'tokenSet' => 'rijkshuisstijl', 'delegated' => true, 'allowedTokenSets' => ['rijkshuisstijl', 'gemeente-a-huisstijl']],
				['group' => 'gemeente-b', 'tokenSet' => 'rijkshuisstijl', 'delegated' => true, 'allowedTokenSets' => ['rijkshuisstijl', 'amsterdam']],
				['group' => 'concern', 'tokenSet' => 'concern'],
			]
		);
	}//end setUp()

	/**
	 * The in-memory config.
	 *
	 * @return IConfig The config.
	 */
	private function config(): IConfig {
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(fn (string $app, string $key, $default = '') => ($this->app[$key] ?? $default));
		$config->method('setAppValue')->willReturnCallback(
			function (string $app, string $key, $value): void {
				$this->app[$key] = (string)$value;
			}
		);

		return $config;
	}//end config()

	/**
	 * The token set service double.
	 *
	 * @return TokenSetService The double.
	 */
	private function tokenSets(): TokenSetService {
		$tokenSets = $this->createMock(TokenSetService::class);
		$tokenSets->method('isValidTokenSet')->willReturnCallback(fn (string $id): bool => in_array($id, self::SETS, true));
		$tokenSets->method('getPublicCatalogue')->willReturn(
			array_map(
				fn (string $id): array => ['id' => $id, 'name' => ucfirst($id), 'design_system' => 'nldesign', 'theming' => ['primary_color' => '#154273'], 'wcagLevel' => 'AA'],
				self::SETS
			)
		);

		return $tokenSets;
	}//end tokenSets()

	/**
	 * The group manager double.
	 *
	 * @return IGroupManager The double.
	 */
	private function groupManager(): IGroupManager {
		$groupManager = $this->createMock(IGroupManager::class);
		$groupManager->method('groupExists')->willReturn(true);
		$groupManager->method('search')->willReturn([]);
		$groupManager->method('get')->willReturnCallback(
			function (string $gid): IGroup {
				$group = $this->createMock(IGroup::class);
				$group->method('getGID')->willReturn($gid);
				$group->method('getDisplayName')->willReturn('Group ' . $gid);

				return $group;
			}
		);

		return $groupManager;
	}//end groupManager()

	/**
	 * The real group theming service.
	 *
	 * @return GroupThemingService The service.
	 */
	private function groupTheming(): GroupThemingService {
		$cache = $this->createMock(ICache::class);
		$cacheFactory = $this->createMock(ICacheFactory::class);
		$cacheFactory->method('createDistributed')->willReturn($cache);

		return new GroupThemingService(
			$this->config(),
			$this->groupManager(),
			$this->createMock(IUserSession::class),
			$this->tokenSets(),
			$this->createMock(ThemePreviewService::class),
			$cacheFactory
		);
	}//end groupTheming()

	/**
	 * The service under test.
	 *
	 * @return DelegatedGroupThemingService The service.
	 */
	private function service(): DelegatedGroupThemingService {
		$userManager = $this->createMock(IUserManager::class);
		$userManager->method('get')->willReturnCallback(
			function (string $uid): IUser {
				$user = $this->createMock(IUser::class);
				$user->method('getUID')->willReturn($uid);

				return $user;
			}
		);
		$subAdmin = $this->createMock(ISubAdmin::class);
		$subAdmin->method('isSubAdminOfGroup')->willReturnCallback(
			fn (IUser $user, IGroup $group): bool => in_array($user->getUID() . ':' . $group->getGID(), $this->subAdmins, true)
		);
		$audit = $this->createMock(ThemingAuditService::class);
		$audit->method('log')->willReturnCallback(
			function (string $action, array $context = []): void {
				$this->audited[] = [$action, $context];
			}
		);

		return new DelegatedGroupThemingService($this->groupTheming(), $this->tokenSets(), $this->groupManager(), $userManager, $subAdmin, $audit);
	}//end service()

	/**
	 * The session double.
	 *
	 * @return IUserSession The session.
	 */
	private function session(): IUserSession {
		$session = $this->createMock(IUserSession::class);
		$user = null;
		if ($this->sessionUid !== null) {
			$user = $this->createMock(IUser::class);
			$user->method('getUID')->willReturn($this->sessionUid);
		}

		$session->method('getUser')->willReturn($user);

		return $session;
	}//end session()

	/**
	 * The entry of a group in the stored mapping.
	 *
	 * @param string $group The group.
	 *
	 * @return array<string, mixed>|null The entry.
	 */
	private function entry(string $group): ?array {
		foreach ($this->groupTheming()->getMapping() as $entry) {
			if ($entry['group'] === $group) {
				return $entry;
			}
		}

		return null;
	}//end entry()

	/**
	 * A delegated entry keeps its fields; an old entry reads as not delegated.
	 *
	 * @return void
	 */
	public function testDelegatedEntryRoundTripsAndOldEntryReadsAsLocked(): void {
		$this->assertSame(['rijkshuisstijl', 'gemeente-a-huisstijl'], $this->entry('gemeente-a')['allowedTokenSets']);
		$this->assertTrue($this->entry('gemeente-a')['delegated']);
		$this->assertSame(['group' => 'concern', 'tokenSet' => 'concern'], $this->entry('concern'));

		$saved = $this->groupTheming()->setMapping(entries: $this->groupTheming()->getMapping());
		$this->assertTrue($saved[0]['delegated']);
		$this->assertArrayNotHasKey('delegated', $saved[2]);
	}//end testDelegatedEntryRoundTripsAndOldEntryReadsAsLocked()

	/**
	 * An allowed list without the current set is refused, and so is an empty one.
	 *
	 * @return void
	 */
	public function testAllowedListMustHoldTheCurrentSet(): void {
		foreach ([['amsterdam'], [], ['missing-set', 'rijkshuisstijl']] as $allowed) {
			try {
				$this->groupTheming()->setMapping(
					entries: [['group' => 'gemeente-a', 'tokenSet' => 'rijkshuisstijl', 'delegated' => true, 'allowedTokenSets' => $allowed]]
				);
				$this->fail('Refused: ' . json_encode($allowed));
			} catch (GroupThemingValidationException $e) {
				$this->addToAssertionCount(1);
			}
		}
	}//end testAllowedListMustHoldTheCurrentSet()

	/**
	 * The subadmin of a delegated group sets an allowed set: mapping updated, generation bumped, audited.
	 *
	 * @return void
	 */
	public function testSubadminOfTheGroupChoosesAnAllowedSet(): void {
		$generation = (int)($this->app['group_token_sets_generation'] ?? 0);

		$entry = $this->service()->setDelegatedTokenSet(uid: 'anna', group: 'gemeente-a', tokenSet: 'gemeente-a-huisstijl');

		$this->assertSame('gemeente-a-huisstijl', $entry['tokenSet']);
		$this->assertSame('gemeente-a-huisstijl', $this->entry('gemeente-a')['tokenSet']);
		$this->assertTrue($this->entry('gemeente-a')['delegated'], 'The subadmin cannot drop the delegation.');
		$this->assertSame($generation + 1, (int)$this->app['group_token_sets_generation']);
		$this->assertSame('token_set_changed', $this->audited[0][0]);
		$this->assertSame(['actor' => 'anna', 'old' => 'rijkshuisstijl', 'new' => 'gemeente-a-huisstijl', 'group' => 'gemeente-a', 'delegated' => true], $this->audited[0][1]);
	}//end testSubadminOfTheGroupChoosesAnAllowedSet()

	/**
	 * Another group, a set outside the list, and a locked group are refused; nothing changes.
	 *
	 * @return void
	 */
	public function testRefusedChoicesChangeNothing(): void {
		$this->subAdmins[] = 'anna:concern';
		$before = $this->app['group_token_sets'];
		$cases = [
			['gemeente-b', 'amsterdam'],
			['gemeente-a', 'amsterdam'],
			['concern', 'concern'],
		];
		foreach ($cases as [$group, $set]) {
			try {
				$this->service()->setDelegatedTokenSet(uid: 'anna', group: $group, tokenSet: $set);
				$this->fail('Refused: ' . $group . ' ' . $set);
			} catch (DelegationRefusedException $e) {
				$this->addToAssertionCount(1);
			}
		}

		$this->assertSame($before, $this->app['group_token_sets']);
		$this->assertSame([], $this->audited);
	}//end testRefusedChoicesChangeNothing()

	/**
	 * Locking a group back keeps its current set and removes it from the subadmin's list.
	 *
	 * @return void
	 */
	public function testLockingBackKeepsTheSetAndHidesTheGroup(): void {
		$this->service()->setDelegatedTokenSet(uid: 'anna', group: 'gemeente-a', tokenSet: 'gemeente-a-huisstijl');
		$this->assertSame('gemeente-a', $this->service()->listForUser(uid: 'anna')[0]['group']);

		$mapping = $this->groupTheming()->getMapping();
		$mapping[0] = ['group' => $mapping[0]['group'], 'tokenSet' => $mapping[0]['tokenSet']];
		$this->groupTheming()->setMapping(entries: $mapping);

		$this->assertSame('gemeente-a-huisstijl', $this->entry('gemeente-a')['tokenSet']);
		$this->assertSame([], $this->service()->listForUser(uid: 'anna'));
		$this->assertFalse($this->service()->hasDelegatedGroups(uid: 'anna'));
	}//end testLockingBackKeepsTheSetAndHidesTheGroup()

	/**
	 * The list names each allowed set with its contrast result.
	 *
	 * @return void
	 */
	public function testListShowsAllowedSetsWithContrast(): void {
		$list = $this->service()->listForUser(uid: 'anna');

		$this->assertCount(1, $list);
		$this->assertSame('Group gemeente-a', $list[0]['displayName']);
		$this->assertSame(['rijkshuisstijl', 'gemeente-a-huisstijl'], array_column($list[0]['allowedTokenSets'], 'id'));
		$this->assertSame('AA', $list[0]['allowedTokenSets'][0]['wcagLevel']);
	}//end testListShowsAllowedSetsWithContrast()

	/**
	 * The admin endpoints carry the delegation fields both ways.
	 *
	 * @return void
	 */
	public function testAdminEndpointsCarryTheDelegationFields(): void {
		$controller = new SettingsController(
			'thematiq',
			$this->createMock(IRequest::class),
			$this->config(),
			$this->tokenSets(),
			$this->createMock(ThemingService::class),
			$this->createMock(TokenSetPreviewService::class),
			$this->createMock(AppThemingService::class),
			$this->createMock(ComplianceReportService::class),
			$this->createMock(ThemingAuditService::class),
			$this->createMock(EmailThemingService::class),
			$this->createMock(UpstreamFreshnessService::class),
			$this->groupTheming(),
			$this->createMock(ActiveTokenSetService::class)
		);

		$response = $controller->setGroupTheming(
			[['group' => 'gemeente-a', 'tokenSet' => 'rijkshuisstijl', 'delegated' => true, 'allowedTokenSets' => ['rijkshuisstijl', 'amsterdam']]]
		);
		$this->assertSame(200, $response->getStatus());
		$mapping = $controller->getGroupTheming()->getData()['mapping'];
		$this->assertSame(['rijkshuisstijl', 'amsterdam'], $mapping[0]['allowedTokenSets']);
		$this->assertTrue($mapping[0]['delegated']);
	}//end testAdminEndpointsCarryTheDelegationFields()

	/**
	 * The subadmin endpoints: a choice for the own group, 403 for another group or a plain user.
	 *
	 * @return void
	 */
	public function testSubadminEndpoints(): void {
		foreach (['index', 'update'] as $method) {
			$this->assertCount(1, (new ReflectionMethod(MyGroupsController::class, $method))->getAttributes(NoAdminRequired::class));
		}

		$controller = new MyGroupsController('thematiq', $this->createMock(IRequest::class), $this->service(), $this->session());
		$this->assertSame('gemeente-a', $controller->index()->getData()['groups'][0]['group']);
		$this->assertSame(403, $controller->update(group: 'gemeente-b', tokenSet: 'amsterdam')->getStatus());
		$this->assertSame(403, $controller->update(group: 'gemeente-a', tokenSet: 'amsterdam')->getStatus());
		$this->assertSame(200, $controller->update(group: 'gemeente-a', tokenSet: 'gemeente-a-huisstijl')->getStatus());
		$this->assertSame('gemeente-a-huisstijl', $this->entry('gemeente-a')['tokenSet']);

		$this->sessionUid = 'bob';
		$plain = new MyGroupsController('thematiq', $this->createMock(IRequest::class), $this->service(), $this->session());
		$this->assertSame([], $plain->index()->getData()['groups']);
		$this->assertSame(403, $plain->update(group: 'gemeente-a', tokenSet: 'rijkshuisstijl')->getStatus());
	}//end testSubadminEndpoints()

	/**
	 * The personal block shows only for a subadmin of a delegated group.
	 *
	 * @return void
	 */
	public function testPersonalSectionOnlyForSubadminsOfDelegatedGroups(): void {
		$this->assertSame(Personal::SECTION, (new Personal($this->service(), $this->session()))->getSection());

		$this->sessionUid = 'bob';
		$this->assertNull((new Personal($this->service(), $this->session()))->getSection());

		$this->sessionUid = null;
		$this->assertNull((new Personal($this->service(), $this->session()))->getSection());
	}//end testPersonalSectionOnlyForSubadminsOfDelegatedGroups()
}//end class
