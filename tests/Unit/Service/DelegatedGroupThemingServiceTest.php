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
use OCA\Thematiq\Service\DelegatedChoice;
use OCA\Thematiq\Service\DelegatedGroupThemingService;
use OCA\Thematiq\Service\EmailThemingSettings;
use OCA\Thematiq\Service\Exception\DelegationRefusedException;
use OCA\Thematiq\Service\Exception\GroupThemingValidationException;
use OCA\Thematiq\Service\GroupThemingService;
use OCA\Thematiq\Service\SettingsConfigServices;
use OCA\Thematiq\Service\SettingsReportServices;
use OCA\Thematiq\Service\SettingsTokenSetServices;
use OCA\Thematiq\Service\ThemePreviewService;
use OCA\Thematiq\Service\ThemingAuditService;
use OCA\Thematiq\Service\ThemingService;
use OCA\Thematiq\Service\TokenSetPreviewService;
use OCA\Thematiq\Service\TokenSetService;
use OCA\Thematiq\Service\UpstreamFreshnessService;
use OCA\Thematiq\Settings\GroupHouseStyle;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\Group\ISubAdmin;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IConfig;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IL10N;
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
		$config = $this->createMock(originalClassName: IConfig::class);
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
		$tokenSets = $this->createMock(originalClassName: TokenSetService::class);
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
		$groupManager = $this->createMock(originalClassName: IGroupManager::class);
		$groupManager->method('groupExists')->willReturn(true);
		$groupManager->method('search')->willReturn([]);
		$groupManager->method('get')->willReturnCallback(
			function (string $gid): IGroup {
				$group = $this->createMock(originalClassName: IGroup::class);
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
		$cache = $this->createMock(originalClassName: ICache::class);
		$cacheFactory = $this->createMock(originalClassName: ICacheFactory::class);
		$cacheFactory->method('createDistributed')->willReturn($cache);

		return new GroupThemingService(
			config: $this->config(),
			groupManager: $this->groupManager(),
			userSession: $this->createMock(originalClassName: IUserSession::class),
			tokenSetService: $this->tokenSets(),
			previewService: $this->createMock(originalClassName: ThemePreviewService::class),
			cacheFactory: $cacheFactory
		);
	}//end groupTheming()

	/**
	 * The service under test.
	 *
	 * @return DelegatedGroupThemingService The service.
	 */
	private function service(): DelegatedGroupThemingService {
		$userManager = $this->createMock(originalClassName: IUserManager::class);
		$userManager->method('get')->willReturnCallback(
			function (string $uid): IUser {
				$user = $this->createMock(originalClassName: IUser::class);
				$user->method('getUID')->willReturn($uid);

				return $user;
			}
		);
		$subAdmin = $this->createMock(originalClassName: ISubAdmin::class);
		$subAdmin->method('isSubAdminOfGroup')->willReturnCallback(
			fn (IUser $user, IGroup $group): bool => in_array($user->getUID() . ':' . $group->getGID(), $this->subAdmins, true)
		);
		$audit = $this->createMock(originalClassName: ThemingAuditService::class);
		$audit->method('log')->willReturnCallback(
			function (string $action, array $context = []): void {
				$this->audited[] = [$action, $context];
			}
		);

		return new DelegatedGroupThemingService(
			groupTheming: $this->groupTheming(),
			tokenSets: $this->tokenSets(),
			groupManager: $this->groupManager(),
			userManager: $userManager,
			subAdmin: $subAdmin,
			audit: $audit
		);
	}//end service()

	/**
	 * The session double.
	 *
	 * @return IUserSession The session.
	 */
	private function session(): IUserSession {
		$session = $this->createMock(originalClassName: IUserSession::class);
		$user = null;
		if ($this->sessionUid !== null) {
			$user = $this->createMock(originalClassName: IUser::class);
			$user->method('getUID')->willReturn($this->sessionUid);
		}

		$session->method('getUser')->willReturn($user);

		return $session;
	}//end session()

	/**
	 * A translator that returns the source text.
	 *
	 * @return IL10N The translator.
	 */
	private function l10n(): IL10N {
		$l10n = $this->createMock(originalClassName: IL10N::class);
		$l10n->method('t')->willReturnArgument(0);

		return $l10n;
	}//end l10n()

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
		$this->assertSame(expected: ['rijkshuisstijl', 'gemeente-a-huisstijl'], actual: $this->entry(group: 'gemeente-a')['allowedTokenSets']);
		$this->assertTrue(condition: $this->entry(group: 'gemeente-a')['delegated']);
		$this->assertSame(expected: ['group' => 'concern', 'tokenSet' => 'concern'], actual: $this->entry(group: 'concern'));

		$saved = $this->groupTheming()->setMapping(entries: $this->groupTheming()->getMapping());
		$this->assertTrue(condition: $saved[0]['delegated']);
		$this->assertArrayNotHasKey(key: 'delegated', array: $saved[2]);
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
				$this->fail(message: 'Refused: ' . json_encode($allowed));
			} catch (GroupThemingValidationException $e) {
				$this->addToAssertionCount(count: 1);
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

		$entry = $this->service()->setDelegatedTokenSet(uid: 'anna', choice: new DelegatedChoice(group: 'gemeente-a', tokenSet: 'gemeente-a-huisstijl'));

		$this->assertSame(expected: 'gemeente-a-huisstijl', actual: $entry['tokenSet']);
		$this->assertSame(expected: 'gemeente-a-huisstijl', actual: $this->entry(group: 'gemeente-a')['tokenSet']);
		$this->assertTrue(condition: $this->entry(group: 'gemeente-a')['delegated'], message: 'The subadmin cannot drop the delegation.');
		$this->assertSame(expected: $generation + 1, actual: (int)$this->app['group_token_sets_generation']);
		$this->assertSame(expected: 'token_set_changed', actual: $this->audited[0][0]);
		$this->assertSame(
			expected: ['actor' => 'anna', 'old' => 'rijkshuisstijl', 'new' => 'gemeente-a-huisstijl', 'group' => 'gemeente-a', 'delegated' => true],
			actual: $this->audited[0][1]
		);
	}//end testSubadminOfTheGroupChoosesAnAllowedSet()

	/**
	 * A stale entry of another group, here an allowed set that was removed,
	 * does not block the subadmin's choice, and stays as it was stored.
	 *
	 * @return void
	 */
	public function testAStaleEntryOfAnotherGroupDoesNotBlockTheChoice(): void {
		$mapping = json_decode($this->app['group_token_sets'], true);
		$mapping[1]['allowedTokenSets'] = ['rijkshuisstijl', 'removed-set'];
		$this->app['group_token_sets'] = json_encode($mapping);

		$entry = $this->service()->setDelegatedTokenSet(uid: 'anna', choice: new DelegatedChoice(group: 'gemeente-a', tokenSet: 'gemeente-a-huisstijl'));

		$this->assertSame(expected: 'gemeente-a-huisstijl', actual: $entry['tokenSet']);
		$this->assertSame(expected: 'gemeente-a-huisstijl', actual: $this->entry(group: 'gemeente-a')['tokenSet']);
		$this->assertSame(expected: ['rijkshuisstijl', 'removed-set'], actual: $this->entry(group: 'gemeente-b')['allowedTokenSets']);
		$this->assertSame(expected: ['group' => 'concern', 'tokenSet' => 'concern'], actual: $this->entry(group: 'concern'));
	}//end testAStaleEntryOfAnotherGroupDoesNotBlockTheChoice()

	/**
	 * When the group's own entry no longer validates, the subadmin gets a 422
	 * with a plain message rather than the reason meant for the administrator.
	 *
	 * @return void
	 */
	public function testARefusedSaveGivesTheSubadminAPlainMessage(): void {
		$mapping = json_decode($this->app['group_token_sets'], true);
		$mapping[0]['allowedTokenSets'] = ['rijkshuisstijl', 'gemeente-a-huisstijl', 'removed-set'];
		$this->app['group_token_sets'] = json_encode($mapping);
		$before = $this->app['group_token_sets'];

		$controller = new MyGroupsController(
			appName: 'thematiq',
			request: $this->createMock(originalClassName: IRequest::class),
			delegation: $this->service(),
			userSession: $this->session(),
			l10n: $this->l10n()
		);
		$response = $controller->update(group: 'gemeente-a', tokenSet: 'gemeente-a-huisstijl');

		$this->assertSame(expected: 422, actual: $response->getStatus());
		$this->assertStringContainsString(needle: 'Ask an administrator', haystack: $response->getData()['error']);
		$this->assertStringNotContainsString(needle: 'removed-set', haystack: $response->getData()['error']);
		$this->assertSame(expected: $before, actual: $this->app['group_token_sets']);
	}//end testARefusedSaveGivesTheSubadminAPlainMessage()

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
				$this->service()->setDelegatedTokenSet(uid: 'anna', choice: new DelegatedChoice(group: $group, tokenSet: $set));
				$this->fail(message: 'Refused: ' . $group . ' ' . $set);
			} catch (DelegationRefusedException $e) {
				$this->addToAssertionCount(count: 1);
			}
		}

		$this->assertSame(expected: $before, actual: $this->app['group_token_sets']);
		$this->assertSame(expected: [], actual: $this->audited);
	}//end testRefusedChoicesChangeNothing()

	/**
	 * Locking a group back keeps its current set and removes it from the subadmin's list.
	 *
	 * @return void
	 */
	public function testLockingBackKeepsTheSetAndHidesTheGroup(): void {
		$this->service()->setDelegatedTokenSet(uid: 'anna', choice: new DelegatedChoice(group: 'gemeente-a', tokenSet: 'gemeente-a-huisstijl'));
		$this->assertSame(expected: 'gemeente-a', actual: $this->service()->listForUser(uid: 'anna')[0]['group']);

		$mapping = $this->groupTheming()->getMapping();
		$mapping[0] = ['group' => $mapping[0]['group'], 'tokenSet' => $mapping[0]['tokenSet']];
		$this->groupTheming()->setMapping(entries: $mapping);

		$this->assertSame(expected: 'gemeente-a-huisstijl', actual: $this->entry(group: 'gemeente-a')['tokenSet']);
		$this->assertSame(expected: [], actual: $this->service()->listForUser(uid: 'anna'));
		$this->assertFalse(condition: $this->service()->hasDelegatedGroups(uid: 'anna'));
	}//end testLockingBackKeepsTheSetAndHidesTheGroup()

	/**
	 * The list names each allowed set with its contrast result.
	 *
	 * @return void
	 */
	public function testListShowsAllowedSetsWithContrast(): void {
		$list = $this->service()->listForUser(uid: 'anna');

		$this->assertCount(expectedCount: 1, haystack: $list);
		$this->assertSame(expected: 'Group gemeente-a', actual: $list[0]['displayName']);
		$this->assertSame(expected: ['rijkshuisstijl', 'gemeente-a-huisstijl'], actual: array_column($list[0]['allowedTokenSets'], 'id'));
		$this->assertSame(expected: 'AA', actual: $list[0]['allowedTokenSets'][0]['wcagLevel']);
	}//end testListShowsAllowedSetsWithContrast()

	/**
	 * The admin endpoints carry the delegation fields both ways.
	 *
	 * @return void
	 */
	public function testAdminEndpointsCarryTheDelegationFields(): void {
		$controller = new SettingsController(
			appName: 'thematiq',
			request: $this->createMock(originalClassName: IRequest::class),
			settings: new SettingsConfigServices(
				config: $this->config(),
				auditService: $this->createMock(originalClassName: ThemingAuditService::class),
				appThemingService: $this->createMock(originalClassName: AppThemingService::class),
				groupThemingService: $this->groupTheming()
			),
			tokenSets: new SettingsTokenSetServices(
				tokenSetService: $this->tokenSets(),
				previewService: $this->createMock(originalClassName: TokenSetPreviewService::class),
				activeTokenSet: $this->createMock(originalClassName: ActiveTokenSetService::class),
				themingService: $this->createMock(originalClassName: ThemingService::class)
			),
			reports: new SettingsReportServices(
				complianceService: $this->createMock(originalClassName: ComplianceReportService::class),
				emailTheming: $this->createMock(originalClassName: EmailThemingSettings::class),
				freshnessService: $this->createMock(originalClassName: UpstreamFreshnessService::class)
			)
		);

		$response = $controller->setGroupTheming(
			[['group' => 'gemeente-a', 'tokenSet' => 'rijkshuisstijl', 'delegated' => true, 'allowedTokenSets' => ['rijkshuisstijl', 'amsterdam']]]
		);
		$this->assertSame(expected: 200, actual: $response->getStatus());
		$mapping = $controller->getGroupTheming()->getData()['mapping'];
		$this->assertSame(expected: ['rijkshuisstijl', 'amsterdam'], actual: $mapping[0]['allowedTokenSets']);
		$this->assertTrue(condition: $mapping[0]['delegated']);
	}//end testAdminEndpointsCarryTheDelegationFields()

	/**
	 * The subadmin endpoints: a choice for the own group, 403 for another group or a plain user.
	 *
	 * @return void
	 */
	public function testSubadminEndpoints(): void {
		// The endpoints need the delegated setting, not just a session.
		foreach (['index', 'update'] as $method) {
			$reflection = new ReflectionMethod(MyGroupsController::class, $method);
			$this->assertCount(expectedCount: 0, haystack: $reflection->getAttributes(NoAdminRequired::class));
			$granted = $reflection->getAttributes(AuthorizedAdminSetting::class);
			$this->assertCount(expectedCount: 1, haystack: $granted);
			$this->assertSame(expected: GroupHouseStyle::class, actual: $granted[0]->getArguments()['settings']);
		}

		$controller = new MyGroupsController(
			appName: 'thematiq',
			request: $this->createMock(originalClassName: IRequest::class),
			delegation: $this->service(),
			userSession: $this->session(),
			l10n: $this->l10n()
		);
		$this->assertSame(expected: 'gemeente-a', actual: $controller->index()->getData()['groups'][0]['group']);
		$this->assertSame(expected: 403, actual: $controller->update(group: 'gemeente-b', tokenSet: 'amsterdam')->getStatus());
		$this->assertSame(expected: 403, actual: $controller->update(group: 'gemeente-a', tokenSet: 'amsterdam')->getStatus());
		$this->assertSame(expected: 200, actual: $controller->update(group: 'gemeente-a', tokenSet: 'gemeente-a-huisstijl')->getStatus());
		$this->assertSame(expected: 'gemeente-a-huisstijl', actual: $this->entry(group: 'gemeente-a')['tokenSet']);

		$this->sessionUid = 'bob';
		$plain = new MyGroupsController(
			appName: 'thematiq',
			request: $this->createMock(originalClassName: IRequest::class),
			delegation: $this->service(),
			userSession: $this->session(),
			l10n: $this->l10n()
		);
		$this->assertSame(expected: [], actual: $plain->index()->getData()['groups']);
		$this->assertSame(expected: 403, actual: $plain->update(group: 'gemeente-a', tokenSet: 'rijkshuisstijl')->getStatus());
	}//end testSubadminEndpoints()

	/**
	 * The delegated admin block shows only for a subadmin of a delegated
	 * group, and tells Administration privileges its name.
	 *
	 * @return void
	 */
	public function testGroupHouseStyleBlockOnlyForSubadminsOfDelegatedGroups(): void {
		$block = new GroupHouseStyle(delegation: $this->service(), userSession: $this->session(), l10n: $this->l10n());
		$this->assertSame(expected: GroupHouseStyle::SECTION, actual: $block->getSection());
		$this->assertSame(expected: 'House style of my groups', actual: $block->getName());
		$this->assertSame(expected: [], actual: $block->getAuthorizedAppConfig());
		$this->assertSame(expected: 'settings/group-house-style', actual: $block->getForm()->getTemplateName());

		$this->sessionUid = 'bob';
		$this->assertNull(actual: (new GroupHouseStyle(
			delegation: $this->service(),
			userSession: $this->session(),
			l10n: $this->l10n()
		))->getSection());

		$this->sessionUid = null;
		$this->assertNull(actual: (new GroupHouseStyle(
			delegation: $this->service(),
			userSession: $this->session(),
			l10n: $this->l10n()
		))->getSection());
	}//end testGroupHouseStyleBlockOnlyForSubadminsOfDelegatedGroups()

	/**
	 * Each refusal names its reason, checked in order: subadmin first, then delegated, then allowed.
	 *
	 * @return void
	 */
	public function testEachRefusalNamesItsReason(): void {
		$this->subAdmins[] = 'anna:concern';
		$this->subAdmins[] = 'anna:ghost';
		$cases = [
			['gemeente-b', 'amsterdam', 'You are not a subadmin of this group.'],
			['concern', 'concern', 'The house style of this group is not delegated.'],
			['ghost', 'rijkshuisstijl', 'The house style of this group is not delegated.'],
			['gemeente-a', 'amsterdam', 'This token set is not allowed for this group.'],
		];
		foreach ($cases as [$group, $set, $message]) {
			try {
				$this->service()->requireDelegatedChoice(uid: 'anna', choice: new DelegatedChoice(group: $group, tokenSet: $set));
				$this->fail(message: 'Refused: ' . $group);
			} catch (DelegationRefusedException $e) {
				$this->assertSame(expected: $message, actual: $e->getMessage());
			}
		}

		$this->assertSame(expected: 'gemeente-a', actual: $this->service()->requireDelegatedChoice(uid: 'anna', choice: new DelegatedChoice(group: 'gemeente-a', tokenSet: 'gemeente-a-huisstijl'))['group']);
	}//end testEachRefusalNamesItsReason()

	/**
	 * Choosing the set the group already has changes and audits nothing, and returns the entry.
	 *
	 * @return void
	 */
	public function testChoosingTheCurrentSetChangesNothing(): void {
		$before = $this->app['group_token_sets'];

		$entry = $this->service()->setDelegatedTokenSet(uid: 'anna', choice: new DelegatedChoice(group: 'gemeente-a', tokenSet: 'rijkshuisstijl'));

		$this->assertSame(expected: ['group' => 'gemeente-a', 'tokenSet' => 'rijkshuisstijl'], actual: array_intersect_key($entry, ['group' => 1, 'tokenSet' => 1]));
		$this->assertSame(expected: $before, actual: $this->app['group_token_sets']);
		$this->assertSame(expected: [], actual: $this->audited);
	}//end testChoosingTheCurrentSetChangesNothing()

	/**
	 * The list holds only the subadmin's delegated groups, in mapping order, each with the fields the page reads.
	 *
	 * @return void
	 */
	public function testTheListHoldsOnlyTheSubadminsDelegatedGroups(): void {
		$this->subAdmins[] = 'anna:gemeente-b';
		$this->subAdmins[] = 'anna:concern';

		$list = $this->service()->listForUser(uid: 'anna');

		$this->assertSame(expected: ['gemeente-a', 'gemeente-b'], actual: array_column($list, 'group'));
		$this->assertSame(expected: ['group', 'displayName', 'tokenSet', 'allowedTokenSets'], actual: array_keys($list[1]));
		$this->assertSame(expected: ['rijkshuisstijl', 'amsterdam'], actual: array_column($list[1]['allowedTokenSets'], 'id'));
		$this->assertSame(expected: [], actual: $this->service()->listForUser(uid: 'bert'));
	}//end testTheListHoldsOnlyTheSubadminsDelegatedGroups()
}//end class
