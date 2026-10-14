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
use OCA\Thematiq\Service\ConfigBundleService;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\CustomOverridesService;
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
use OCA\Thematiq\Service\ScheduledSwitchService;
use OCA\Thematiq\Service\ScheduledSwitchStore;
use OCA\Thematiq\Service\ThemePreviewService;
use OCA\Thematiq\Service\ThemingAuditService;
use OCA\Thematiq\Service\TokenSetFontAuditService;
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
#[CoversClass(AppBrandService::class)]
#[CoversClass(ConfigBundleService::class)]
#[CoversClass(ThemeSet::class)]
#[CoversClass(TokenSetService::class)]
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

	protected function setUp(): void {
		parent::setUp();
		$this->makeAppDir();
		$this->config = $this->memoryConfig();
		$this->tokenSets = $this->fixtureTokenSets($this->config);
		$this->now = strtotime('2026-10-12T12:00:00+00:00');
	}//end setUp()

	protected function tearDown(): void {
		$this->removeAppDir();
		parent::tearDown();
	}//end tearDown()

	public function testTheInstanceWideSwitchRefusesAndThenAccepts(): void {
		$active = new ActiveTokenSetService($this->config, $this->tokenSets, $this->createMock(ThemingAuditService::class));

		try {
			$active->switchTo('owned');
			$this->fail('the restricted set became the active set without a confirmation');
		} catch (RestrictedSetNotConfirmedException $e) {
			$this->assertArrayNotHasKey('token_set', $this->appValues, 'nothing is written on a refusal');
		}

		$this->assertSame('nextcloud', $active->switchTo('open'), 'an open-licensed set needs no confirmation');

		$this->confirmSet('owned');
		$active->switchTo('owned');
		$this->assertSame('owned', $this->appValues['token_set']);
	}//end testTheInstanceWideSwitchRefusesAndThenAccepts()

	public function testTheActiveSetChosenAgainIsNotANewUse(): void {
		$this->appValues['token_set'] = 'owned';
		$active = new ActiveTokenSetService($this->config, $this->tokenSets, $this->createMock(ThemingAuditService::class));

		$this->assertSame('owned', $active->switchTo('owned'));
	}//end testTheActiveSetChosenAgainIsNotANewUse()

	public function testTheCommandLineSwitchRefuses(): void {
		$active = new ActiveTokenSetService($this->config, $this->tokenSets, $this->createMock(ThemingAuditService::class));
		$tester = new CommandTester(new ThemeSet($active, $this->tokenSets, $this->createMock(ScheduledCoreThemingSync::class)));

		$this->assertSame(Command::FAILURE, $tester->execute(['token-set' => 'owned']));
		$this->assertStringContainsString('Nothing was changed', $tester->getDisplay());
		$this->assertArrayNotHasKey('token_set', $this->appValues);

		$this->confirmSet('owned');
		$this->assertSame(Command::SUCCESS, $tester->execute(['token-set' => 'owned']));
		$this->assertSame('owned', $this->appValues['token_set']);
	}//end testTheCommandLineSwitchRefuses()

	public function testAGroupMappingRefusesAndThenAccepts(): void {
		$groups = $this->groupTheming();

		try {
			$groups->setMapping([['group' => 'sociaal', 'tokenSet' => 'open'], ['group' => 'ruimte', 'tokenSet' => 'owned']]);
			$this->fail('a group got the restricted set without a confirmation');
		} catch (GroupThemingValidationException $e) {
			$this->assertSame('owned', $e->getRestrictedSet());
			$this->assertArrayNotHasKey('group_token_sets', $this->appValues, 'the whole save is refused');
		}

		$this->confirmSet('owned');
		$groups->setMapping([['group' => 'ruimte', 'tokenSet' => 'owned']]);
		$this->assertSame([['group' => 'ruimte', 'tokenSet' => 'owned']], $groups->getMapping());
	}//end testAGroupMappingRefusesAndThenAccepts()

	public function testAGroupThatKeepsItsSetIsSavedAgainButAnotherGroupIsNot(): void {
		$this->appValues['group_token_sets'] = (string)json_encode([['group' => 'ruimte', 'tokenSet' => 'owned']]);
		$groups = $this->groupTheming();

		$groups->setMapping([['group' => 'ruimte', 'tokenSet' => 'owned'], ['group' => 'sociaal', 'tokenSet' => 'open']]);
		$this->assertCount(2, $groups->getMapping());

		$this->expectException(GroupThemingValidationException::class);
		$groups->setMapping([['group' => 'ruimte', 'tokenSet' => 'owned'], ['group' => 'sociaal', 'tokenSet' => 'owned']]);
	}//end testAGroupThatKeepsItsSetIsSavedAgainButAnotherGroupIsNot()

	public function testADelegatedChoiceRefuses(): void {
		$this->appValues['group_token_sets'] = (string)json_encode(
			[['group' => 'ruimte', 'tokenSet' => 'open', 'delegated' => true, 'allowedTokenSets' => ['open', 'owned']]]
		);
		$user = $this->createMock(IUser::class);
		$userManager = $this->createMock(IUserManager::class);
		$userManager->method('get')->willReturn($user);
		$groupManager = $this->createMock(IGroupManager::class);
		$groupManager->method('get')->willReturn($this->createMock(IGroup::class));
		$groupManager->method('groupExists')->willReturn(true);
		$subAdmin = $this->createMock(ISubAdmin::class);
		$subAdmin->method('isSubAdminOfGroup')->willReturn(true);
		$delegated = new DelegatedGroupThemingService(
			$this->groupTheming(),
			$this->tokenSets,
			$groupManager,
			$userManager,
			$subAdmin,
			$this->createMock(ThemingAuditService::class)
		);

		try {
			$delegated->setDelegatedTokenSet('sub', 'ruimte', 'owned');
			$this->fail('a subadmin chose the restricted set without a confirmation');
		} catch (GroupThemingValidationException $e) {
			$this->assertSame('owned', $e->getRestrictedSet());
		}

		$this->confirmSet('owned');
		$this->assertSame('owned', $delegated->setDelegatedTokenSet('sub', 'ruimte', 'owned')['tokenSet']);
	}//end testADelegatedChoiceRefuses()

	public function testAPreviewRefusesToStartAndToPublish(): void {
		$preview = $this->preview();

		try {
			$preview->startPreview('admin', 'owned');
			$this->fail('a preview of the restricted set started without a confirmation');
		} catch (RestrictedSetNotConfirmedException $e) {
			$this->assertSame([], $this->userValues);
		}

		// A preview that was started while the set was confirmed, and outlived the confirmation.
		$this->userValues['admin/preview_token_set'] = 'owned';
		$this->userValues['admin/preview_expires_at'] = (string)(time() + 600);
		try {
			$preview->publishPreview('admin');
			$this->fail('the restricted set was published without a confirmation');
		} catch (RestrictedSetNotConfirmedException $e) {
			$this->assertArrayNotHasKey('token_set', $this->appValues);
		}

		$this->confirmSet('owned');
		$this->assertSame('owned', $preview->startPreview('admin', 'owned')['tokenSet']);
		$this->assertSame('owned', $preview->publishPreview('admin'));
		$this->assertSame('owned', $this->appValues['token_set']);
	}//end testAPreviewRefusesToStartAndToPublish()

	public function testAPreviewOfAnUnconfirmedSetIsNotDrawn(): void {
		$this->userValues['admin/preview_token_set'] = 'owned';
		$this->userValues['admin/preview_expires_at'] = (string)(time() + 600);
		$preview = $this->preview();

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('admin');
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($user);

		$this->assertSame('nextcloud', $preview->resolveEffectiveTokenSet($session, 'nextcloud')['tokenSet']);

		$this->confirmSet('owned');
		$this->assertSame('owned', $preview->resolveEffectiveTokenSet($session, 'nextcloud')['tokenSet']);
	}//end testAPreviewOfAnUnconfirmedSetIsNotDrawn()

	public function testAPlannedSwitchRefusesWhenPlannedAndWhenItStarts(): void {
		$switches = $this->scheduledSwitches();

		try {
			$switches->create('owned', '2026-10-13T09:00:00+00:00', null, 'admin');
			$this->fail('a switch to the restricted set was planned without a confirmation');
		} catch (RestrictedSetNotConfirmedException $e) {
			$this->assertSame('owned', $e->getTokenSetId());
			$this->assertSame([], (new ScheduledSwitchStore($this->config))->all());
		}

		// Planned while confirmed, confirmation gone by the time it starts.
		$this->confirmSet('owned');
		$switches->create('owned', '2026-10-13T09:00:00+00:00', null, 'admin');
		unset($this->appValues[RestrictedSetGate::CONFIG_KEY]);
		$this->now = strtotime('2026-10-13T09:05:00+00:00');
		$switches->runDue();

		$stored = (new ScheduledSwitchStore($this->config))->all();
		$this->assertSame('failed', $stored[0]['status']);
		$this->assertStringContainsString('Nobody confirmed', $stored[0]['failureReason']);
		$this->assertArrayNotHasKey('token_set', $this->appValues);
	}//end testAPlannedSwitchRefusesWhenPlannedAndWhenItStarts()

	public function testAnAppBrandRefusesAndThenAccepts(): void {
		$brands = $this->appBrands();

		try {
			$brands->setBrand('collectives', 'owned');
			$this->fail('an app got the restricted set as its brand without a confirmation');
		} catch (AppBrandException $e) {
			$this->assertSame(AppBrandException::RESTRICTED_SET, $e->reason);
			$this->assertSame(403, $e->getCode());
			$this->assertArrayNotHasKey(AppBrandService::CONFIG_KEY, $this->appValues);
		}

		$this->confirmSet('owned');
		$this->assertSame('owned', $brands->setBrand('collectives', 'owned')['tokenSet']);
	}//end testAnAppBrandRefusesAndThenAccepts()

	public function testAConfigurationBundleRefusesEveryPlaceItNamesTheSet(): void {
		$bundles = $this->bundles();
		$places = [
			'the active set' => ['config' => ['tokenSet' => 'owned']],
			'a planned switch' => ['config' => ['tokenSet' => 'open', 'scheduledSwitches' => [['tokenSet' => 'owned']]]],
			'an app brand' => ['config' => ['tokenSet' => 'open'], 'appBrands' => ['collectives' => ['tokenSet' => 'owned']]],
		];

		foreach ($places as $place => $bundle) {
			$result = $bundles->import($bundle, true);
			$this->assertFalse($result['valid'], $place);
			$this->assertStringContainsString('"owned" is restricted to its owner', implode("\n", array_column($result['errors'], 'message')), $place);
		}

		$this->confirmSet('owned');
		foreach ($places as $place => $bundle) {
			$this->assertStringNotContainsString('restricted to its owner', (string)json_encode($bundles->import($bundle, true)), $place);
		}
	}//end testAConfigurationBundleRefusesEveryPlaceItNamesTheSet()

	public function testABundleMayKeepTheSetThatIsActiveHere(): void {
		$this->appValues['token_set'] = 'owned';

		$result = $this->bundles()->import(['config' => ['tokenSet' => 'owned']], true);

		$this->assertStringNotContainsString('restricted to its owner', (string)json_encode($result));
	}//end testABundleMayKeepTheSetThatIsActiveHere()

	public function testTheSetListCarriesTheLicenceAndTheConfirmation(): void {
		$this->confirmSet('owned');
		$byId = array_column($this->tokenSets->getAvailableTokenSets(), null, 'id');

		$this->assertTrue($byId['owned']['licence']['restricted']);
		$this->assertTrue($byId['owned']['licence']['confirmed']);
		$this->assertFalse($byId['owned-two']['licence']['confirmed']);
		$this->assertFalse($byId['open']['licence']['restricted']);
		$this->assertArrayNotHasKey('licence', $byId['nextcloud']);
	}//end testTheSetListCarriesTheLicenceAndTheConfirmation()

	/**
	 * The real group theming service; every group exists.
	 *
	 * @return GroupThemingService The service.
	 */
	private function groupTheming(): GroupThemingService {
		$groupManager = $this->createMock(IGroupManager::class);
		$groupManager->method('groupExists')->willReturn(true);
		$cacheFactory = $this->createMock(ICacheFactory::class);
		$cacheFactory->method('createDistributed')->willReturn($this->createMock(ICache::class));

		return new GroupThemingService(
			$this->config,
			$groupManager,
			$this->createMock(IUserSession::class),
			$this->tokenSets,
			$this->preview(),
			$cacheFactory
		);
	}//end groupTheming()

	/**
	 * The real preview service; `admin` is an administrator with a session.
	 *
	 * @return ThemePreviewService The service.
	 */
	private function preview(): ThemePreviewService {
		$groupManager = $this->createMock(IGroupManager::class);
		$groupManager->method('isAdmin')->willReturn(true);

		return new ThemePreviewService($this->config, $groupManager, $this->tokenSets, $this->createMock(LoggerInterface::class));
	}//end preview()

	/**
	 * The real planned switch service on the test clock.
	 *
	 * @return ScheduledSwitchService The service.
	 */
	private function scheduledSwitches(): ScheduledSwitchService {
		$audit = $this->createMock(ThemingAuditService::class);
		$time = $this->createMock(ITimeFactory::class);
		$time->method('getTime')->willReturnCallback(fn () => $this->now);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);

		return new ScheduledSwitchService(
			new ScheduledSwitchStore($this->config),
			new ActiveTokenSetService($this->config, $this->tokenSets, $audit),
			$this->tokenSets,
			$this->createMock(ScheduledCoreThemingSync::class),
			$audit,
			$this->config,
			$time,
			$l10n,
			$this->createMock(LoggerInterface::class)
		);
	}//end scheduledSwitches()

	/**
	 * The real brand per app service; every app is installed and themed.
	 *
	 * @return AppBrandService The service.
	 */
	private function appBrands(): AppBrandService {
		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('isInstalled')->willReturn(true);

		return new AppBrandService(
			$this->config,
			$appManager,
			$this->createMock(AppThemingService::class),
			$this->tokenSets,
			$this->createMock(AppBrandLogoStore::class),
			$this->createMock(IURLGenerator::class)
		);
	}//end appBrands()

	/**
	 * The real bundle service; everything but the token sets and the config is a stand-in.
	 *
	 * @return ConfigBundleService The service.
	 */
	private function bundles(): ConfigBundleService {
		return new ConfigBundleService(
			$this->config,
			$this->createMock(IAppManager::class),
			$this->tokenSets,
			$this->createMock(CustomOverridesService::class),
			$this->createMock(CustomTokenSetService::class),
			$this->createMock(CustomTokenSetValidator::class),
			$this->createMock(AppThemingService::class),
			$this->createMock(CssParserService::class),
			$this->createMock(EmailThemingService::class),
			$this->createMock(FontService::class),
			$this->createMock(UpstreamFreshnessService::class),
			new ScheduledSwitchStore($this->config),
			$this->createMock(LoggerInterface::class),
			$this->createMock(BundleExtraSections::class)
		);
	}//end bundles()
}//end class
