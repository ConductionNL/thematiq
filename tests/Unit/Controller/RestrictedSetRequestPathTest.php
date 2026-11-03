<?php

/**
 * The refusal of a restricted token set as the browser receives it.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/token-set-licence-gate/specs/token-set-licences/spec.md#requirement-a-restricted-set-is-refused-until-an-administrator-confirms-it
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Controller;

use OC\AppFramework\Http\Dispatcher;
use OCA\Thematiq\AppInfo\Application;
use OCA\Thematiq\Capabilities;
use OCA\Thematiq\Controller\AppBrandController;
use OCA\Thematiq\Controller\PreviewController;
use OCA\Thematiq\Controller\ScheduledSwitchController;
use OCA\Thematiq\Controller\SettingsController;
use OCA\Thematiq\Controller\TokenSetLicenceController;
use OCA\Thematiq\Middleware\ConfigSourceLockMiddleware;
use OCA\Thematiq\Service\ActiveTokenSetService;
use OCA\Thematiq\Service\AppBrandLogoStore;
use OCA\Thematiq\Service\AppBrandService;
use OCA\Thematiq\Service\AppBrandSources;
use OCA\Thematiq\Service\AppThemingService;
use OCA\Thematiq\Service\AssistantMarkService;
use OCA\Thematiq\Service\BrandingPackageService;
use OCA\Thematiq\Service\BundleExtraSections;
use OCA\Thematiq\Service\ColourFunctionEvaluator;
use OCA\Thematiq\Service\ComplianceReportService;
use OCA\Thematiq\Service\ConfigBundleService;
use OCA\Thematiq\Service\ConfigSourceClock;
use OCA\Thematiq\Service\ConfigSourceLock;
use OCA\Thematiq\Service\ConfigSourceService;
use OCA\Thematiq\Service\ConfigSourceSteps;
use OCA\Thematiq\Service\CustomOverridesService;
use OCA\Thematiq\Service\CustomTokenSetsBundleSection;
use OCA\Thematiq\Service\CustomTokenSetService;
use OCA\Thematiq\Service\DarkPaletteService;
use OCA\Thematiq\Service\DenhaagContrastPairs;
use OCA\Thematiq\Service\DeprecationRecords;
use OCA\Thematiq\Service\DesignSystemService;
use OCA\Thematiq\Service\DesignTokenRef;
use OCA\Thematiq\Service\DesignTokensMapper;
use OCA\Thematiq\Service\DesignTokensMapping;
use OCA\Thematiq\Service\DocumentAssetService;
use OCA\Thematiq\Service\DtcgValueChecker;
use OCA\Thematiq\Service\EmailThemingService;
use OCA\Thematiq\Service\EmailThemingSettings;
use OCA\Thematiq\Service\Exception\AppBrandException;
use OCA\Thematiq\Service\Exception\GroupThemingValidationException;
use OCA\Thematiq\Service\Exception\RestrictedSetInUseException;
use OCA\Thematiq\Service\Exception\RestrictedSetNotConfirmedException;
use OCA\Thematiq\Service\FontService;
use OCA\Thematiq\Service\GroupDelegationRules;
use OCA\Thematiq\Service\GroupThemingService;
use OCA\Thematiq\Service\OverridesCssBuilder;
use OCA\Thematiq\Service\OverridesSetContext;
use OCA\Thematiq\Service\OverridesStorage;
use OCA\Thematiq\Service\OwnTokenService;
use OCA\Thematiq\Service\PackageTokenSourceConverter;
use OCA\Thematiq\Service\RestrictedSetGate;
use OCA\Thematiq\Service\RuntimeFile\AppDataRuntimeFileStore;
use OCA\Thematiq\Service\RuntimeFile\RuntimeFileLocator;
use OCA\Thematiq\Service\RuntimeFile\SetFileReader;
use OCA\Thematiq\Service\ScheduledCoreThemingSync;
use OCA\Thematiq\Service\ScheduledSwitchRuntime;
use OCA\Thematiq\Service\ScheduledSwitchService;
use OCA\Thematiq\Service\ScheduledSwitchStore;
use OCA\Thematiq\Service\ScheduledSwitchTargets;
use OCA\Thematiq\Service\SettableContrastPairs;
use OCA\Thematiq\Service\ShippedTokenSetAuditService;
use OCA\Thematiq\Service\ThemePreviewService;
use OCA\Thematiq\Service\ThemeVersionService;
use OCA\Thematiq\Service\ThemingAuditService;
use OCA\Thematiq\Service\ThemingService;
use OCA\Thematiq\Service\TokenDeprecationService;
use OCA\Thematiq\Service\TokenLifecycleBundleSection;
use OCA\Thematiq\Service\TokenSetConverterService;
use OCA\Thematiq\Service\TokenSetFontAuditService;
use OCA\Thematiq\Service\TokenSetLicenceService;
use OCA\Thematiq\Service\TokenSetPreviewService;
use OCA\Thematiq\Service\TokenSetService;
use OCA\Thematiq\Service\TokenSetVocabularyAuditService;
use OCA\Thematiq\Service\UpstreamFreshnessService;
use OCA\Thematiq\Tests\Unit\Support\RestrictedSetFixture;
use OCP\App\IAppManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Server;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionProperty;

/**
 * A controller that returns a 403 is not yet a browser that receives one:
 * Nextcloud's OCSMiddleware answers a 403 from an OCSController with HTTP 200.
 * So these tests send each request through Nextcloud's own Dispatcher, with
 * its real middleware chain and a signed-in administrator, and read the status
 * line that goes out. The controllers are the app's own, over the real
 * services and a throwaway set directory.
 *
 * The first test is the control: the same Dispatcher turns an OCS 403 into
 * 200, so a 403 read in the other tests is a status the chain let through.
 */
#[CoversClass(SettingsController::class)]
#[CoversClass(PreviewController::class)]
#[CoversClass(ScheduledSwitchController::class)]
#[CoversClass(AppBrandController::class)]
#[CoversClass(TokenSetLicenceController::class)]
#[UsesClass(ActiveTokenSetService::class)]
#[UsesClass(AppBrandService::class)]
#[UsesClass(GroupThemingService::class)]
#[UsesClass(GroupDelegationRules::class)]
#[UsesClass(ThemePreviewService::class)]
#[UsesClass(ScheduledSwitchService::class)]
#[UsesClass(ScheduledSwitchRuntime::class)]
#[UsesClass(ScheduledSwitchTargets::class)]
#[UsesClass(ScheduledSwitchStore::class)]
#[UsesClass(TokenSetLicenceService::class)]
#[UsesClass(TokenSetService::class)]
#[UsesClass(AppBrandSources::class)]
#[UsesClass(RestrictedSetGate::class)]
#[UsesClass(SetFileReader::class)]
#[UsesClass(TokenSetFontAuditService::class)]
#[UsesClass(RestrictedSetNotConfirmedException::class)]
#[UsesClass(RestrictedSetInUseException::class)]
#[UsesClass(GroupThemingValidationException::class)]
#[UsesClass(AppBrandException::class)]
#[UsesClass(Application::class)]
#[UsesClass(Capabilities::class)]
#[UsesClass(ConfigSourceLockMiddleware::class)]
#[UsesClass(AppBrandLogoStore::class)]
#[UsesClass(AppThemingService::class)]
#[UsesClass(AssistantMarkService::class)]
#[UsesClass(BrandingPackageService::class)]
#[UsesClass(BundleExtraSections::class)]
#[UsesClass(ColourFunctionEvaluator::class)]
#[UsesClass(ConfigBundleService::class)]
#[UsesClass(ConfigSourceService::class)]
#[UsesClass(ConfigSourceClock::class)]
#[UsesClass(ConfigSourceLock::class)]
#[UsesClass(ConfigSourceSteps::class)]
#[UsesClass(CustomOverridesService::class)]
#[UsesClass(OverridesSetContext::class)]
#[UsesClass(OverridesStorage::class)]
#[UsesClass(CustomTokenSetService::class)]
#[UsesClass(CustomTokenSetsBundleSection::class)]
#[UsesClass(DarkPaletteService::class)]
#[UsesClass(DenhaagContrastPairs::class)]
#[UsesClass(DeprecationRecords::class)]
#[UsesClass(DesignSystemService::class)]
#[UsesClass(DesignTokensMapper::class)]
#[UsesClass(DesignTokensMapping::class)]
#[UsesClass(DesignTokenRef::class)]
#[UsesClass(DocumentAssetService::class)]
#[UsesClass(DtcgValueChecker::class)]
#[UsesClass(EmailThemingService::class)]
#[UsesClass(EmailThemingSettings::class)]
#[UsesClass(FontService::class)]
#[UsesClass(OverridesCssBuilder::class)]
#[UsesClass(OwnTokenService::class)]
#[UsesClass(PackageTokenSourceConverter::class)]
#[UsesClass(AppDataRuntimeFileStore::class)]
#[UsesClass(RuntimeFileLocator::class)]
#[UsesClass(SettableContrastPairs::class)]
#[UsesClass(ShippedTokenSetAuditService::class)]
#[UsesClass(ThemeVersionService::class)]
#[UsesClass(ThemingAuditService::class)]
#[UsesClass(TokenDeprecationService::class)]
#[UsesClass(TokenLifecycleBundleSection::class)]
#[UsesClass(TokenSetConverterService::class)]
#[UsesClass(TokenSetPreviewService::class)]
#[UsesClass(TokenSetVocabularyAuditService::class)]
#[UsesClass(UpstreamFreshnessService::class)]
class RestrictedSetRequestPathTest extends TestCase {

	use RestrictedSetFixture;

	/**
	 * The uid of the administrator this test signs in.
	 *
	 * @var string
	 */
	private const ADMIN_UID = 'thematiq-licence-gate-admin';

	/**
	 * Nextcloud's request object.
	 *
	 * @var IRequest
	 */
	private IRequest $request;

	/**
	 * The request's items before the test changed them.
	 *
	 * @var array<string, mixed>
	 */
	private array $originalItems = [];

	/**
	 * Nextcloud's dispatcher for this app.
	 *
	 * @var Dispatcher
	 */
	private Dispatcher $dispatcher;

	/**
	 * The in-memory app config the services write to.
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
	 * The administrator, created for this test.
	 *
	 * @var IUser|null
	 */
	private ?IUser $admin = null;

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

		$users = Server::get(IUserManager::class);
		$this->admin = ($users->get(self::ADMIN_UID) ?? $users->createUser(self::ADMIN_UID, bin2hex(random_bytes(16))));
		$this->assertInstanceOf(expected: IUser::class, actual: $this->admin, message: 'the test administrator could not be created');
		Server::get(IGroupManager::class)->get('admin')?->addUser($this->admin);
		Server::get(IUserSession::class)->setUser($this->admin);

		$this->request = Server::get(IRequest::class);
		$items = new ReflectionProperty($this->request, 'items');
		$this->originalItems = $items->getValue($this->request);
		$changed = $this->originalItems;
		$changed['server']['HTTP_OCS_APIREQUEST'] = 'true';
		$changed['server']['REQUEST_METHOD'] = 'POST';
		$changed['method'] = 'POST';
		$items->setValue($this->request, $changed);

		$this->dispatcher = (new Application())->getContainer()->get(Dispatcher::class);
	}//end setUp()

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		(new ReflectionProperty($this->request, 'items'))->setValue($this->request, $this->originalItems);
		$this->request->setUrlParameters([]);
		Server::get(IUserSession::class)->setUser(null);
		$this->admin?->delete();
		$this->removeAppDir();
		parent::tearDown();
	}//end tearDown()

	/**
	 * Send one request through the dispatcher.
	 *
	 * @param Controller $controller The controller.
	 * @param string $method The controller method.
	 * @param array<string, mixed> $params The request parameters.
	 *
	 * @return array{status: int, body: array<string, mixed>} The status that goes out and the decoded body.
	 */
	private function send(Controller $controller, string $method, array $params): array {
		$this->request->setUrlParameters($params);
		$result = $this->dispatcher->dispatch($controller, $method);
		$this->assertSame(
			expected: 1,
			actual: preg_match('/^HTTP\/1\.\d (\d{3})/', (string)$result[0], $match),
			message: 'no status line: ' . (string)$result[0]
		);

		return ['status' => (int)$match[1], 'body' => (array)json_decode((string)$result[3], true)];
	}//end send()

	/**
	 * Control the same chain turns an ocs 403 into 200.
	 *
	 * @return void
	 */
	public function testControlTheSameChainTurnsAnOcs403Into200(): void {
		$ocs = new class('thematiq', $this->request) extends OCSController {

			#[NoAdminRequired]
			public function refuse(): DataResponse {
				return new DataResponse(['error' => 'refused'], 403);
			}//end refuse()
		};

		$this->assertSame(expected: 200, actual: $this->send(controller: $ocs, method: 'refuse', params: [])['status']);
	}//end testControlTheSameChainTurnsAnOcs403Into200()

	/**
	 * Applying the set instance wide is delivered as 403 then as 200.
	 *
	 * @return void
	 */
	public function testApplyingTheSetInstanceWideIsDeliveredAs403ThenAs200(): void {
		$sent = $this->send(controller: $this->settingsController(), method: 'setTokenSet', params: ['tokenSet' => 'owned']);
		$this->assertSame(expected: 403, actual: $sent['status']);
		$this->assertSame(expected: ['error' => 'restricted_set_not_confirmed', 'tokenSet' => 'owned'], actual: $sent['body']);
		$this->assertArrayNotHasKey(key: 'token_set', array: $this->appValues);

		$this->assertSame(
			expected: 400,
			actual: $this->send(controller: $this->licenceController(), method: 'confirm', params: ['id' => 'owned'])['status'],
			message: 'no statement, no confirmation'
		);
		$confirmed = $this->send(controller: $this->licenceController(), method: 'confirm', params: ['id' => 'owned', 'confirmed' => true]);
		$this->assertSame(expected: 200, actual: $confirmed['status']);
		$this->assertSame(expected: self::ADMIN_UID, actual: $confirmed['body']['confirmation']['uid']);

		$this->assertSame(
			expected: 200,
			actual: $this->send(controller: $this->settingsController(), method: 'setTokenSet', params: ['tokenSet' => 'owned'])['status']
		);
		$this->assertSame(expected: 'owned', actual: $this->appValues['token_set']);

		$withdrawn = $this->send(controller: $this->licenceController(), method: 'withdraw', params: ['id' => 'owned']);
		$this->assertSame(expected: 409, actual: $withdrawn['status']);
		$this->assertSame(expected: [['kind' => 'instance', 'id' => '']], actual: $withdrawn['body']['appliedIn']);
	}//end testApplyingTheSetInstanceWideIsDeliveredAs403ThenAs200()

	/**
	 * A group mapping is delivered as 403.
	 *
	 * @return void
	 */
	public function testAGroupMappingIsDeliveredAs403(): void {
		$mapping = ['mapping' => [['group' => 'ruimte', 'tokenSet' => 'owned']]];

		$sent = $this->send(controller: $this->settingsController(), method: 'setGroupTheming', params: $mapping);
		$this->assertSame(expected: 403, actual: $sent['status']);
		$this->assertSame(expected: 'restricted_set_not_confirmed', actual: $sent['body']['error']);
		$this->assertSame(expected: 'owned', actual: $sent['body']['tokenSet']);
		$this->assertArrayNotHasKey(key: 'group_token_sets', array: $this->appValues);

		$this->confirmSet(tokenSetId: 'owned');
		$this->assertSame(
			expected: 200,
			actual: $this->send(controller: $this->settingsController(), method: 'setGroupTheming', params: $mapping)['status']
		);
	}//end testAGroupMappingIsDeliveredAs403()

	/**
	 * A preview is delivered as 403.
	 *
	 * @return void
	 */
	public function testAPreviewIsDeliveredAs403(): void {
		$controller = new PreviewController(
			appName: 'thematiq',
			request: $this->request,
			previewService: $this->previewService(),
			userSession: Server::get(IUserSession::class)
		);

		$sent = $this->send(controller: $controller, method: 'start', params: ['tokenSet' => 'owned']);
		$this->assertSame(expected: 403, actual: $sent['status']);
		$this->assertSame(expected: 'restricted_set_not_confirmed', actual: $sent['body']['error']);

		$this->userValues[self::ADMIN_UID . '/preview_token_set'] = 'owned';
		$this->userValues[self::ADMIN_UID . '/preview_expires_at'] = (string)(time() + 600);
		$this->assertSame(expected: 403, actual: $this->send(controller: $controller, method: 'publish', params: [])['status']);

		$this->confirmSet(tokenSetId: 'owned');
		$this->assertSame(expected: 200, actual: $this->send(controller: $controller, method: 'start', params: ['tokenSet' => 'owned'])['status']);
		$this->assertSame(expected: 200, actual: $this->send(controller: $controller, method: 'publish', params: [])['status']);
	}//end testAPreviewIsDeliveredAs403()

	/**
	 * A planned switch is delivered as 403.
	 *
	 * @return void
	 */
	public function testAPlannedSwitchIsDeliveredAs403(): void {
		$audit = $this->createMock(originalClassName: ThemingAuditService::class);
		$time = $this->createMock(originalClassName: ITimeFactory::class);
		$time->method('getTime')->willReturn(strtotime('2026-10-12T12:00:00+00:00'));
		$l10n = $this->createMock(originalClassName: IL10N::class);
		$l10n->method('t')->willReturnArgument(0);
		$service = new ScheduledSwitchService(
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
		$controller = new ScheduledSwitchController(
			appName: 'thematiq',
			request: $this->request,
			service: $service,
			userSession: Server::get(IUserSession::class)
		);
		$plan = ['tokenSet' => 'owned', 'startAt' => '2026-10-13T09:00:00+00:00'];

		$sent = $this->send(controller: $controller, method: 'create', params: $plan);
		$this->assertSame(expected: 403, actual: $sent['status']);
		$this->assertSame(expected: 'restricted_set_not_confirmed', actual: $sent['body']['error']);

		$this->confirmSet(tokenSetId: 'owned');
		$this->assertSame(expected: 201, actual: $this->send(controller: $controller, method: 'create', params: $plan)['status']);
	}//end testAPlannedSwitchIsDeliveredAs403()

	/**
	 * An app brand is delivered as 403.
	 *
	 * @return void
	 */
	public function testAnAppBrandIsDeliveredAs403(): void {
		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('isInstalled')->willReturn(true);
		$appTheming = $this->createMock(originalClassName: AppThemingService::class);
		$brands = new AppBrandService(
			config: $this->config,
			appManager: $appManager,
			appTheming: $appTheming,
			tokenSets: $this->tokenSets,
			logos: $this->createMock(originalClassName: AppBrandLogoStore::class),
			urls: $this->createMock(originalClassName: IURLGenerator::class)
		);
		$controller = new AppBrandController(
			appName: 'thematiq',
			request: $this->request,
			sources: new AppBrandSources(
				brands: $brands,
				appTheming: $appTheming,
				tokenSets: $this->tokenSets
			),
			l10n: $this->createMock(originalClassName: IL10N::class)
		);

		$sent = $this->send(controller: $controller, method: 'save', params: ['appId' => 'collectives', 'tokenSet' => 'owned']);
		$this->assertSame(expected: 403, actual: $sent['status']);
		$this->assertSame(expected: 'restricted_set_not_confirmed', actual: $sent['body']['reason']);

		$this->confirmSet(tokenSetId: 'owned');
		$this->assertSame(
			expected: 200,
			actual: $this->send(controller: $controller, method: 'save', params: ['appId' => 'collectives', 'tokenSet' => 'owned'])['status']
		);
	}//end testAnAppBrandIsDeliveredAs403()

	/**
	 * The preview service; the signed-in user is an administrator.
	 *
	 * @return ThemePreviewService The service.
	 */
	private function previewService(): ThemePreviewService {
		$groupManager = $this->createMock(originalClassName: IGroupManager::class);
		$groupManager->method('isAdmin')->willReturn(true);

		return new ThemePreviewService(
			config: $this->config,
			groupManager: $groupManager,
			tokenSetService: $this->tokenSets,
			logger: $this->createMock(originalClassName: LoggerInterface::class)
		);
	}//end previewService()

	/**
	 * The licence controller over the real licence service.
	 *
	 * @return TokenSetLicenceController The controller.
	 */
	private function licenceController(): TokenSetLicenceController {
		$time = $this->createMock(originalClassName: ITimeFactory::class);
		$time->method('getDateTime')->willReturn(new \DateTime('2026-10-12T20:15:00+00:00'));

		return new TokenSetLicenceController(
			appName: 'thematiq',
			request: $this->request,
			licences: new TokenSetLicenceService(
				tokenSets: $this->tokenSets,
				config: $this->config,
				switches: new ScheduledSwitchStore(config: $this->config),
				audit: $this->createMock(originalClassName: ThemingAuditService::class),
				time: $time,
				logger: $this->createMock(originalClassName: LoggerInterface::class)
			),
			userSession: Server::get(IUserSession::class)
		);
	}//end licenceController()

	/**
	 * The settings controller over the real set and group services.
	 *
	 * @return SettingsController The controller.
	 */
	private function settingsController(): SettingsController {
		$groupManager = $this->createMock(originalClassName: IGroupManager::class);
		$groupManager->method('groupExists')->willReturn(true);
		$cacheFactory = $this->createMock(originalClassName: ICacheFactory::class);
		$cacheFactory->method('createDistributed')->willReturn($this->createMock(originalClassName: ICache::class));
		$audit = $this->createMock(originalClassName: ThemingAuditService::class);

		return new SettingsController(
			appName: 'thematiq',
			request: $this->request,
			config: $this->config,
			tokenSetService: $this->tokenSets,
			themingService: $this->createMock(originalClassName: ThemingService::class),
			previewService: $this->createMock(originalClassName: TokenSetPreviewService::class),
			appThemingService: $this->createMock(originalClassName: AppThemingService::class),
			complianceService: $this->createMock(originalClassName: ComplianceReportService::class),
			auditService: $audit,
			emailTheming: $this->createMock(originalClassName: EmailThemingSettings::class),
			freshnessService: $this->createMock(originalClassName: UpstreamFreshnessService::class),
			groupThemingService: new GroupThemingService(
				config: $this->config,
				groupManager: $groupManager,
				userSession: $this->createMock(originalClassName: IUserSession::class),
				tokenSetService: $this->tokenSets,
				previewService: $this->previewService(),
				cacheFactory: $cacheFactory
			),
			activeTokenSet: new ActiveTokenSetService(config: $this->config, tokenSets: $this->tokenSets, auditService: $audit)
		);
	}//end settingsController()
}//end class
