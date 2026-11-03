<?php

/**
 * Every path that applies a token set asks the licence gate.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/token-set-licence-gate/specs/token-set-licences/spec.md#requirement-every-path-that-applies-a-set-asks-the-same-gate
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Command\ThemeSet;
use OCA\Thematiq\Service\ActiveTokenSetService;
use OCA\Thematiq\Service\AppBrandLogoStore;
use OCA\Thematiq\Service\AppBrandService;
use OCA\Thematiq\Service\AppThemingService;
use OCA\Thematiq\Service\BundleExtraSections;
use OCA\Thematiq\Service\ConfigBundleAppSettings;
use OCA\Thematiq\Service\ConfigBundleBranding;
use OCA\Thematiq\Service\ConfigBundleSections;
use OCA\Thematiq\Service\ConfigBundleService;
use OCA\Thematiq\Service\ConfigBundleTokenSources;
use OCA\Thematiq\Service\CssDeclarationScanner;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\CustomOverridesService;
use OCA\Thematiq\Service\CustomTokenSetsBundleSection;
use OCA\Thematiq\Service\CustomTokenSetService;
use OCA\Thematiq\Service\CustomTokenSetValidator;
use OCA\Thematiq\Service\DelegatedGroupThemingService;
use OCA\Thematiq\Service\EmailThemingService;
use OCA\Thematiq\Service\Exception\AppBrandException;
use OCA\Thematiq\Service\Exception\GroupThemingValidationException;
use OCA\Thematiq\Service\Exception\RestrictedSetNotConfirmedException;
use OCA\Thematiq\Service\FontService;
use OCA\Thematiq\Service\GroupDelegationRules;
use OCA\Thematiq\Service\GroupThemingService;
use OCA\Thematiq\Service\LayoutOptionValues;
use OCA\Thematiq\Service\RestrictedSetGate;
use OCA\Thematiq\Service\RestrictedSetsInBundle;
use OCA\Thematiq\Service\RuntimeFile\SetFileReader;
use OCA\Thematiq\Service\ScheduledCoreThemingSync;
use OCA\Thematiq\Service\ScheduledSwitchOrder;
use OCA\Thematiq\Service\ScheduledSwitchRuntime;
use OCA\Thematiq\Service\ScheduledSwitchService;
use OCA\Thematiq\Service\ScheduledSwitchStore;
use OCA\Thematiq\Service\ScheduledSwitchTargets;
use OCA\Thematiq\Service\ThemePreviewService;
use OCA\Thematiq\Service\ThemingAuditService;
use OCA\Thematiq\Service\TokenSetAudits;
use OCA\Thematiq\Service\TokenSetFiles;
use OCA\Thematiq\Service\TokenSetFontAuditService;
use OCA\Thematiq\Service\TokenSetRuntime;
use OCA\Thematiq\Service\TokenSetService;
use OCA\Thematiq\Service\UpstreamFreshnessService;
use OCA\Thematiq\Tests\Unit\Support\RestrictedSetFixture;
use OCP\App\IAppManager;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Group\ISubAdmin;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IConfig;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * A guard with a full test suite and no call site protects nothing. So each
 * test here starts at a caller that applies a set (the real service, over a
 * real TokenSetService and the real gate) and asserts two things: the
 * restricted set is refused and nothing is written, and after a confirmation
 * the same call goes through.
 */
#[CoversClass(ActiveTokenSetService::class)]
#[CoversClass(GroupThemingService::class)]
#[CoversClass(DelegatedGroupThemingService::class)]
#[CoversClass(ThemePreviewService::class)]
#[CoversClass(ScheduledSwitchService::class)]
#[UsesClass(ScheduledSwitchRuntime::class)]
#[UsesClass(ScheduledSwitchTargets::class)]
#[CoversClass(AppBrandService::class)]
#[CoversClass(ConfigBundleService::class)]
#[UsesClass(ConfigBundleAppSettings::class)]
#[UsesClass(ConfigBundleBranding::class)]
#[UsesClass(ConfigBundleSections::class)]
#[UsesClass(ConfigBundleTokenSources::class)]
#[CoversClass(CustomTokenSetsBundleSection::class)]
#[CoversClass(ThemeSet::class)]
#[CoversClass(TokenSetService::class)]
#[UsesClass(TokenSetAudits::class)]
#[UsesClass(TokenSetFiles::class)]
#[UsesClass(TokenSetRuntime::class)]
#[CoversClass(RestrictedSetGate::class)]
#[CoversClass(RestrictedSetsInBundle::class)]
#[CoversClass(RestrictedSetNotConfirmedException::class)]
#[CoversClass(GroupThemingValidationException::class)]
#[CoversClass(AppBrandException::class)]
#[UsesClass(GroupDelegationRules::class)]
#[UsesClass(ScheduledSwitchStore::class)]
#[UsesClass(SetFileReader::class)]
#[UsesClass(TokenSetFontAuditService::class)]
#[UsesClass(ScheduledSwitchOrder::class)]
#[UsesClass(LayoutOptionValues::class)]
#[UsesClass(CssParserService::class)]
#[UsesClass(CssDeclarationScanner::class)]
class RestrictedSetWiringTest extends TestCase {

	use RestrictedSetFixture;

	/**
	 * The in-memory config.
	 *
	 * @var IConfig
	 */
	private IConfig $config;

	/**
	 * The real token set service over the fixture directory.
	 *
	 * @var TokenSetService
	 */
	private TokenSetService $tokenSets;

	/**
	 * The clock, as a unix time.
	 *
	 * @var int
	 */
	private int $now;

	/**
	 * Set up each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->makeAppDir();
		$this->config = $this->memoryConfig();
		$this->tokenSets = $this->fixtureTokenSets(config: $this->config);
		$this->now = strtotime('2026-10-12T12:00:00+00:00');
	}//end setUp()

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$this->removeAppDir();
		parent::tearDown();
	}//end tearDown()

	/**
	 * The instance wide switch refuses and then accepts.
	 *
	 * @return void
	 */
	public function testTheInstanceWideSwitchRefusesAndThenAccepts(): void {
		$active = new ActiveTokenSetService(
			config: $this->config,
			tokenSets: $this->tokenSets,
			auditService: $this->createMock(originalClassName: ThemingAuditService::class)
		);

		try {
			$active->switchTo('owned');
			$this->fail(message: 'the restricted set became the active set without a confirmation');
		} catch (RestrictedSetNotConfirmedException $e) {
			$this->assertArrayNotHasKey(key: 'token_set', array: $this->appValues, message: 'nothing is written on a refusal');
		}

		$this->assertSame(expected: 'nextcloud', actual: $active->switchTo('open'), message: 'an open-licensed set needs no confirmation');

		$this->confirmSet(tokenSetId: 'owned');
		$active->switchTo('owned');
		$this->assertSame(expected: 'owned', actual: $this->appValues['token_set']);
	}//end testTheInstanceWideSwitchRefusesAndThenAccepts()

	/**
	 * The active set chosen again is not a new use.
	 *
	 * @return void
	 */
	public function testTheActiveSetChosenAgainIsNotANewUse(): void {
		$this->appValues['token_set'] = 'owned';
		$active = new ActiveTokenSetService(
			config: $this->config,
			tokenSets: $this->tokenSets,
			auditService: $this->createMock(originalClassName: ThemingAuditService::class)
		);

		$this->assertSame(expected: 'owned', actual: $active->switchTo('owned'));
	}//end testTheActiveSetChosenAgainIsNotANewUse()

	/**
	 * The command line switch refuses.
	 *
	 * @return void
	 */
	public function testTheCommandLineSwitchRefuses(): void {
		$active = new ActiveTokenSetService(
			config: $this->config,
			tokenSets: $this->tokenSets,
			auditService: $this->createMock(originalClassName: ThemingAuditService::class)
		);
		$tester = new CommandTester(new ThemeSet(
			active: $active,
			tokenSets: $this->tokenSets,
			coreSync: $this->createMock(originalClassName: ScheduledCoreThemingSync::class)
		));

		$this->assertSame(expected: Command::FAILURE, actual: $tester->execute(['token-set' => 'owned']));
		$this->assertStringContainsString(needle: 'Nothing was changed', haystack: $tester->getDisplay());
		$this->assertArrayNotHasKey(key: 'token_set', array: $this->appValues);

		$this->confirmSet(tokenSetId: 'owned');
		$this->assertSame(expected: Command::SUCCESS, actual: $tester->execute(['token-set' => 'owned']));
		$this->assertSame(expected: 'owned', actual: $this->appValues['token_set']);
	}//end testTheCommandLineSwitchRefuses()

	/**
	 * A group mapping refuses and then accepts.
	 *
	 * @return void
	 */
	public function testAGroupMappingRefusesAndThenAccepts(): void {
		$groups = $this->groupTheming();

		try {
			$groups->setMapping([['group' => 'sociaal', 'tokenSet' => 'open'], ['group' => 'ruimte', 'tokenSet' => 'owned']]);
			$this->fail(message: 'a group got the restricted set without a confirmation');
		} catch (GroupThemingValidationException $e) {
			$this->assertSame(expected: 'owned', actual: $e->getRestrictedSet());
			$this->assertArrayNotHasKey(key: 'group_token_sets', array: $this->appValues, message: 'the whole save is refused');
		}

		$this->confirmSet(tokenSetId: 'owned');
		$groups->setMapping([['group' => 'ruimte', 'tokenSet' => 'owned']]);
		$this->assertSame(expected: [['group' => 'ruimte', 'tokenSet' => 'owned']], actual: $groups->getMapping());
	}//end testAGroupMappingRefusesAndThenAccepts()

	/**
	 * A group that keeps its set is saved again but another group is not.
	 *
	 * @return void
	 */
	public function testAGroupThatKeepsItsSetIsSavedAgainButAnotherGroupIsNot(): void {
		$this->appValues['group_token_sets'] = (string)json_encode([['group' => 'ruimte', 'tokenSet' => 'owned']]);
		$groups = $this->groupTheming();

		$groups->setMapping([['group' => 'ruimte', 'tokenSet' => 'owned'], ['group' => 'sociaal', 'tokenSet' => 'open']]);
		$this->assertCount(expectedCount: 2, haystack: $groups->getMapping());

		$this->expectException(exception: GroupThemingValidationException::class);
		$groups->setMapping([['group' => 'ruimte', 'tokenSet' => 'owned'], ['group' => 'sociaal', 'tokenSet' => 'owned']]);
	}//end testAGroupThatKeepsItsSetIsSavedAgainButAnotherGroupIsNot()

	/**
	 * A delegated choice refuses.
	 *
	 * @return void
	 */
	public function testADelegatedChoiceRefuses(): void {
		$this->appValues['group_token_sets'] = (string)json_encode(
			[['group' => 'ruimte', 'tokenSet' => 'open', 'delegated' => true, 'allowedTokenSets' => ['open', 'owned']]]
		);
		$user = $this->createMock(originalClassName: IUser::class);
		$userManager = $this->createMock(originalClassName: IUserManager::class);
		$userManager->method('get')->willReturn($user);
		$groupManager = $this->createMock(originalClassName: IGroupManager::class);
		$groupManager->method('get')->willReturn($this->createMock(originalClassName: IGroup::class));
		$groupManager->method('groupExists')->willReturn(true);
		$subAdmin = $this->createMock(originalClassName: ISubAdmin::class);
		$subAdmin->method('isSubAdminOfGroup')->willReturn(true);
		$delegated = new DelegatedGroupThemingService(
			groupTheming: $this->groupTheming(),
			tokenSets: $this->tokenSets,
			groupManager: $groupManager,
			userManager: $userManager,
			subAdmin: $subAdmin,
			audit: $this->createMock(originalClassName: ThemingAuditService::class)
		);

		try {
			$delegated->setDelegatedTokenSet('sub', 'ruimte', 'owned');
			$this->fail(message: 'a subadmin chose the restricted set without a confirmation');
		} catch (GroupThemingValidationException $e) {
			$this->assertSame(expected: 'owned', actual: $e->getRestrictedSet());
		}

		$this->confirmSet(tokenSetId: 'owned');
		$this->assertSame(expected: 'owned', actual: $delegated->setDelegatedTokenSet('sub', 'ruimte', 'owned')['tokenSet']);
	}//end testADelegatedChoiceRefuses()

	/**
	 * A preview refuses to start and to publish.
	 *
	 * @return void
	 */
	public function testAPreviewRefusesToStartAndToPublish(): void {
		$preview = $this->preview();

		try {
			$preview->startPreview('admin', 'owned');
			$this->fail(message: 'a preview of the restricted set started without a confirmation');
		} catch (RestrictedSetNotConfirmedException $e) {
			$this->assertSame(expected: [], actual: $this->userValues);
		}

		// A preview that was started while the set was confirmed, and outlived the confirmation.
		$this->userValues['admin/preview_token_set'] = 'owned';
		$this->userValues['admin/preview_expires_at'] = (string)(time() + 600);
		try {
			$preview->publishPreview('admin');
			$this->fail(message: 'the restricted set was published without a confirmation');
		} catch (RestrictedSetNotConfirmedException $e) {
			$this->assertArrayNotHasKey(key: 'token_set', array: $this->appValues);
		}

		$this->confirmSet(tokenSetId: 'owned');
		$this->assertSame(expected: 'owned', actual: $preview->startPreview('admin', 'owned')['tokenSet']);
		$this->assertSame(expected: 'owned', actual: $preview->publishPreview('admin'));
		$this->assertSame(expected: 'owned', actual: $this->appValues['token_set']);
	}//end testAPreviewRefusesToStartAndToPublish()

	/**
	 * A preview of an unconfirmed set is not drawn.
	 *
	 * @return void
	 */
	public function testAPreviewOfAnUnconfirmedSetIsNotDrawn(): void {
		$this->userValues['admin/preview_token_set'] = 'owned';
		$this->userValues['admin/preview_expires_at'] = (string)(time() + 600);
		$preview = $this->preview();

		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn('admin');
		$session = $this->createMock(originalClassName: IUserSession::class);
		$session->method('getUser')->willReturn($user);

		$this->assertSame(expected: 'nextcloud', actual: $preview->resolveEffectiveTokenSet($session, 'nextcloud')['tokenSet']);

		$this->confirmSet(tokenSetId: 'owned');
		$this->assertSame(expected: 'owned', actual: $preview->resolveEffectiveTokenSet($session, 'nextcloud')['tokenSet']);
	}//end testAPreviewOfAnUnconfirmedSetIsNotDrawn()

	/**
	 * A planned switch refuses when planned and when it starts.
	 *
	 * @return void
	 */
	public function testAPlannedSwitchRefusesWhenPlannedAndWhenItStarts(): void {
		$switches = $this->scheduledSwitches();

		try {
			$switches->create('owned', '2026-10-13T09:00:00+00:00', null, 'admin');
			$this->fail(message: 'a switch to the restricted set was planned without a confirmation');
		} catch (RestrictedSetNotConfirmedException $e) {
			$this->assertSame(expected: 'owned', actual: $e->getTokenSetId());
			$this->assertSame(expected: [], actual: (new ScheduledSwitchStore(config: $this->config))->all());
		}

		// Planned while confirmed, confirmation gone by the time it starts.
		$this->confirmSet(tokenSetId: 'owned');
		$switches->create('owned', '2026-10-13T09:00:00+00:00', null, 'admin');
		unset($this->appValues[RestrictedSetGate::CONFIG_KEY]);
		$this->now = strtotime('2026-10-13T09:05:00+00:00');
		$switches->runDue();

		$stored = (new ScheduledSwitchStore(config: $this->config))->all();
		$this->assertSame(expected: 'failed', actual: $stored[0]['status']);
		$this->assertStringContainsString(needle: 'Nobody confirmed', haystack: $stored[0]['failureReason']);
		$this->assertArrayNotHasKey(key: 'token_set', array: $this->appValues);
	}//end testAPlannedSwitchRefusesWhenPlannedAndWhenItStarts()

	/**
	 * An app brand refuses and then accepts.
	 *
	 * @return void
	 */
	public function testAnAppBrandRefusesAndThenAccepts(): void {
		$brands = $this->appBrands();

		try {
			$brands->setBrand('collectives', 'owned');
			$this->fail(message: 'an app got the restricted set as its brand without a confirmation');
		} catch (AppBrandException $e) {
			$this->assertSame(expected: AppBrandException::RESTRICTED_SET, actual: $e->reason);
			$this->assertSame(expected: 403, actual: $e->getCode());
			$this->assertArrayNotHasKey(key: AppBrandService::CONFIG_KEY, array: $this->appValues);
		}

		$this->confirmSet(tokenSetId: 'owned');
		$this->assertSame(expected: 'owned', actual: $brands->setBrand('collectives', 'owned')['tokenSet']);
	}//end testAnAppBrandRefusesAndThenAccepts()

	/**
	 * A configuration bundle refuses every place it names the set.
	 *
	 * @return void
	 */
	public function testAConfigurationBundleRefusesEveryPlaceItNamesTheSet(): void {
		$bundles = $this->bundles();
		$places = [
			'the active set' => ['config' => ['tokenSet' => 'owned']],
			'a planned switch' => ['config' => ['tokenSet' => 'open', 'scheduledSwitches' => [['tokenSet' => 'owned']]]],
			'an app brand' => ['config' => ['tokenSet' => 'open'], 'appBrands' => ['collectives' => ['tokenSet' => 'owned']]],
		];

		foreach ($places as $place => $bundle) {
			$result = $bundles->import($bundle, true);
			$this->assertFalse(condition: $result['valid'], message: $place);
			$this->assertStringContainsString(
				needle: '"owned" is restricted to its owner',
				haystack: implode("\n", array_column($result['errors'], 'message')),
				message: $place
			);
		}

		$this->confirmSet(tokenSetId: 'owned');
		foreach ($places as $place => $bundle) {
			$this->assertStringNotContainsString(
				needle: 'restricted to its owner',
				haystack: (string)json_encode($bundles->import($bundle, true)),
				message: $place
			);
		}
	}//end testAConfigurationBundleRefusesEveryPlaceItNamesTheSet()

	/**
	 * A bundle may keep the set that is active here.
	 *
	 * @return void
	 */
	public function testABundleMayKeepTheSetThatIsActiveHere(): void {
		$this->appValues['token_set'] = 'owned';

		$result = $this->bundles()->import(['config' => ['tokenSet' => 'owned']], true);

		$this->assertStringNotContainsString(needle: 'restricted to its owner', haystack: (string)json_encode($result));
	}//end testABundleMayKeepTheSetThatIsActiveHere()

	/**
	 * The set list carries the licence and the confirmation.
	 *
	 * @return void
	 */
	public function testTheSetListCarriesTheLicenceAndTheConfirmation(): void {
		$this->confirmSet(tokenSetId: 'owned');
		$byId = array_column($this->tokenSets->getAvailableTokenSets(), null, 'id');

		$this->assertTrue(condition: $byId['owned']['licence']['restricted']);
		$this->assertTrue(condition: $byId['owned']['licence']['confirmed']);
		$this->assertFalse(condition: $byId['owned-two']['licence']['confirmed']);
		$this->assertFalse(condition: $byId['open']['licence']['restricted']);
		$this->assertArrayNotHasKey(key: 'licence', array: $byId['nextcloud']);
	}//end testTheSetListCarriesTheLicenceAndTheConfirmation()

	/**
	 * The real group theming service; every group exists.
	 *
	 * @return GroupThemingService The service.
	 */
	private function groupTheming(): GroupThemingService {
		$groupManager = $this->createMock(originalClassName: IGroupManager::class);
		$groupManager->method('groupExists')->willReturn(true);
		$cacheFactory = $this->createMock(originalClassName: ICacheFactory::class);
		$cacheFactory->method('createDistributed')->willReturn($this->createMock(originalClassName: ICache::class));

		return new GroupThemingService(
			config: $this->config,
			groupManager: $groupManager,
			userSession: $this->createMock(originalClassName: IUserSession::class),
			tokenSetService: $this->tokenSets,
			previewService: $this->preview(),
			cacheFactory: $cacheFactory
		);
	}//end groupTheming()

	/**
	 * The real preview service; `admin` is an administrator with a session.
	 *
	 * @return ThemePreviewService The service.
	 */
	private function preview(): ThemePreviewService {
		$groupManager = $this->createMock(originalClassName: IGroupManager::class);
		$groupManager->method('isAdmin')->willReturn(true);

		return new ThemePreviewService(
			config: $this->config,
			groupManager: $groupManager,
			tokenSetService: $this->tokenSets,
			logger: $this->createMock(originalClassName: LoggerInterface::class)
		);
	}//end preview()

	/**
	 * The real planned switch service on the test clock.
	 *
	 * @return ScheduledSwitchService The service.
	 */
	private function scheduledSwitches(): ScheduledSwitchService {
		$audit = $this->createMock(originalClassName: ThemingAuditService::class);
		$time = $this->createMock(originalClassName: ITimeFactory::class);
		$time->method('getTime')->willReturnCallback(fn () => $this->now);
		$l10n = $this->createMock(originalClassName: IL10N::class);
		$l10n->method('t')->willReturnArgument(0);

		return new ScheduledSwitchService(
			store: new ScheduledSwitchStore(config: $this->config),
			targets: new ScheduledSwitchTargets(
				activeTokenSet: new ActiveTokenSetService(config: $this->config, tokenSets: $this->tokenSets, auditService: $audit),
				tokenSets: $this->tokenSets,
				coreSync: $this->createMock(originalClassName: ScheduledCoreThemingSync::class)
			),
			runtime: new ScheduledSwitchRuntime(
				auditService: $audit,
				config: $this->config,
				time: $time,
				logger: $this->createMock(originalClassName: LoggerInterface::class)
			),
			l10n: $l10n
		);
	}//end scheduledSwitches()

	/**
	 * The real brand per app service; every app is installed and themed.
	 *
	 * @return AppBrandService The service.
	 */
	private function appBrands(): AppBrandService {
		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('isInstalled')->willReturn(true);

		return new AppBrandService(
			config: $this->config,
			appManager: $appManager,
			appTheming: $this->createMock(originalClassName: AppThemingService::class),
			tokenSets: $this->tokenSets,
			logos: $this->createMock(originalClassName: AppBrandLogoStore::class),
			urls: $this->createMock(originalClassName: IURLGenerator::class)
		);
	}//end appBrands()

	/**
	 * The real bundle service; everything but the token sets and the config is a stand-in.
	 *
	 * @return ConfigBundleService The service.
	 */
	private function bundles(): ConfigBundleService {
		return new ConfigBundleService(
			appSettings: new ConfigBundleAppSettings(
				config: $this->config,
				appManager: $this->createMock(originalClassName: IAppManager::class),
				appThemingService: $this->createMock(originalClassName: AppThemingService::class),
				freshnessService: $this->createMock(originalClassName: UpstreamFreshnessService::class)
			),
			tokenSources: new ConfigBundleTokenSources(
				tokenSetService: $this->tokenSets,
				overridesService: $this->createMock(originalClassName: CustomOverridesService::class),
				scheduledSwitches: new ScheduledSwitchStore(config: $this->config),
				cssParser: $this->createMock(originalClassName: CssParserService::class)
			),
			sections: new ConfigBundleSections(
				customTokenSets: new CustomTokenSetsBundleSection(
					customSets: $this->createMock(originalClassName: CustomTokenSetService::class),
					validator: $this->createMock(originalClassName: CustomTokenSetValidator::class),
					cssParser: $this->createMock(originalClassName: CssParserService::class),
					appManager: $this->createMock(originalClassName: IAppManager::class)
				),
				extraSections: $this->createMock(originalClassName: BundleExtraSections::class)
			),
			branding: new ConfigBundleBranding(
				emailThemingService: $this->createMock(originalClassName: EmailThemingService::class),
				fontService: $this->createMock(originalClassName: FontService::class),
				logger: $this->createMock(originalClassName: LoggerInterface::class)
			)
		);
	}//end bundles()
}//end class
