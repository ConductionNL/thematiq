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
use OCA\Thematiq\Controller\AppBrandController;
use OCA\Thematiq\Controller\PreviewController;
use OCA\Thematiq\Controller\ScheduledSwitchController;
use OCA\Thematiq\Controller\SettingsController;
use OCA\Thematiq\Controller\TokenSetLicenceController;
use OCA\Thematiq\Service\ActiveTokenSetService;
use OCA\Thematiq\Service\AppBrandLogoStore;
use OCA\Thematiq\Service\AppBrandService;
use OCA\Thematiq\Service\AppThemingService;
use OCA\Thematiq\Service\ComplianceReportService;
use OCA\Thematiq\Service\EmailThemingService;
use OCA\Thematiq\Service\Exception\AppBrandException;
use OCA\Thematiq\Service\Exception\GroupThemingValidationException;
use OCA\Thematiq\Service\Exception\RestrictedSetInUseException;
use OCA\Thematiq\Service\Exception\RestrictedSetNotConfirmedException;
use OCA\Thematiq\Service\GroupDelegationRules;
use OCA\Thematiq\Service\GroupThemingService;
use OCA\Thematiq\Service\RestrictedSetGate;
use OCA\Thematiq\Service\RuntimeFile\SetFileReader;
use OCA\Thematiq\Service\ScheduledCoreThemingSync;
use OCA\Thematiq\Service\ScheduledSwitchService;
use OCA\Thematiq\Service\ScheduledSwitchStore;
use OCA\Thematiq\Service\ThemePreviewService;
use OCA\Thematiq\Service\ThemingAuditService;
use OCA\Thematiq\Service\ThemingService;
use OCA\Thematiq\Service\TokenSetFontAuditService;
use OCA\Thematiq\Service\TokenSetLicenceService;
use OCA\Thematiq\Service\TokenSetPreviewService;
use OCA\Thematiq\Service\TokenSetService;
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
#[UsesClass(ScheduledSwitchStore::class)]
#[UsesClass(TokenSetLicenceService::class)]
#[UsesClass(TokenSetService::class)]
#[UsesClass(RestrictedSetGate::class)]
#[UsesClass(SetFileReader::class)]
#[UsesClass(TokenSetFontAuditService::class)]
#[UsesClass(RestrictedSetNotConfirmedException::class)]
#[UsesClass(RestrictedSetInUseException::class)]
#[UsesClass(GroupThemingValidationException::class)]
#[UsesClass(AppBrandException::class)]
#[UsesClass(Application::class)]
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

	protected function setUp(): void {
		parent::setUp();
		$this->makeAppDir();
		$this->config = $this->memoryConfig();
		$this->tokenSets = $this->fixtureTokenSets($this->config);

		$users = Server::get(IUserManager::class);
		$this->admin = ($users->get(self::ADMIN_UID) ?? $users->createUser(self::ADMIN_UID, bin2hex(random_bytes(16))));
		$this->assertInstanceOf(IUser::class, $this->admin, 'the test administrator could not be created');
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
		$this->assertSame(1, preg_match('/^HTTP\/1\.\d (\d{3})/', (string)$result[0], $match), 'no status line: ' . (string)$result[0]);

		return ['status' => (int)$match[1], 'body' => (array)json_decode((string)$result[3], true)];
	}//end send()

	public function testControlTheSameChainTurnsAnOcs403Into200(): void {
		$ocs = new class('thematiq', $this->request) extends OCSController {

			#[NoAdminRequired]
			public function refuse(): DataResponse {
				return new DataResponse(['error' => 'refused'], 403);
			}//end refuse()
		};

		$this->assertSame(200, $this->send($ocs, 'refuse', [])['status']);
	}//end testControlTheSameChainTurnsAnOcs403Into200()

	public function testApplyingTheSetInstanceWideIsDeliveredAs403ThenAs200(): void {
		$sent = $this->send($this->settingsController(), 'setTokenSet', ['tokenSet' => 'owned']);
		$this->assertSame(403, $sent['status']);
		$this->assertSame(['error' => 'restricted_set_not_confirmed', 'tokenSet' => 'owned'], $sent['body']);
		$this->assertArrayNotHasKey('token_set', $this->appValues);

		$this->assertSame(400, $this->send($this->licenceController(), 'confirm', ['id' => 'owned'])['status'], 'no statement, no confirmation');
		$confirmed = $this->send($this->licenceController(), 'confirm', ['id' => 'owned', 'confirmed' => true]);
		$this->assertSame(200, $confirmed['status']);
		$this->assertSame(self::ADMIN_UID, $confirmed['body']['confirmation']['uid']);

		$this->assertSame(200, $this->send($this->settingsController(), 'setTokenSet', ['tokenSet' => 'owned'])['status']);
		$this->assertSame('owned', $this->appValues['token_set']);

		$withdrawn = $this->send($this->licenceController(), 'withdraw', ['id' => 'owned']);
		$this->assertSame(409, $withdrawn['status']);
		$this->assertSame([['kind' => 'instance', 'id' => '']], $withdrawn['body']['appliedIn']);
	}//end testApplyingTheSetInstanceWideIsDeliveredAs403ThenAs200()

	public function testAGroupMappingIsDeliveredAs403(): void {
		$mapping = ['mapping' => [['group' => 'ruimte', 'tokenSet' => 'owned']]];

		$sent = $this->send($this->settingsController(), 'setGroupTheming', $mapping);
		$this->assertSame(403, $sent['status']);
		$this->assertSame('restricted_set_not_confirmed', $sent['body']['error']);
		$this->assertSame('owned', $sent['body']['tokenSet']);
		$this->assertArrayNotHasKey('group_token_sets', $this->appValues);

		$this->confirmSet('owned');
		$this->assertSame(200, $this->send($this->settingsController(), 'setGroupTheming', $mapping)['status']);
	}//end testAGroupMappingIsDeliveredAs403()

	public function testAPreviewIsDeliveredAs403(): void {
		$controller = new PreviewController('thematiq', $this->request, $this->previewService(), Server::get(IUserSession::class));

		$sent = $this->send($controller, 'start', ['tokenSet' => 'owned']);
		$this->assertSame(403, $sent['status']);
		$this->assertSame('restricted_set_not_confirmed', $sent['body']['error']);

		$this->userValues[self::ADMIN_UID . '/preview_token_set'] = 'owned';
		$this->userValues[self::ADMIN_UID . '/preview_expires_at'] = (string)(time() + 600);
		$this->assertSame(403, $this->send($controller, 'publish', [])['status']);

		$this->confirmSet('owned');
		$this->assertSame(200, $this->send($controller, 'start', ['tokenSet' => 'owned'])['status']);
		$this->assertSame(200, $this->send($controller, 'publish', [])['status']);
	}//end testAPreviewIsDeliveredAs403()

	public function testAPlannedSwitchIsDeliveredAs403(): void {
		$audit = $this->createMock(ThemingAuditService::class);
		$time = $this->createMock(ITimeFactory::class);
		$time->method('getTime')->willReturn(strtotime('2026-10-12T12:00:00+00:00'));
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);
		$service = new ScheduledSwitchService(
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
		$controller = new ScheduledSwitchController('thematiq', $this->request, $service, Server::get(IUserSession::class));
		$plan = ['tokenSet' => 'owned', 'startAt' => '2026-10-13T09:00:00+00:00'];

		$sent = $this->send($controller, 'create', $plan);
		$this->assertSame(403, $sent['status']);
		$this->assertSame('restricted_set_not_confirmed', $sent['body']['error']);

		$this->confirmSet('owned');
		$this->assertSame(201, $this->send($controller, 'create', $plan)['status']);
	}//end testAPlannedSwitchIsDeliveredAs403()

	public function testAnAppBrandIsDeliveredAs403(): void {
		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('isInstalled')->willReturn(true);
		$appTheming = $this->createMock(AppThemingService::class);
		$brands = new AppBrandService(
			$this->config,
			$appManager,
			$appTheming,
			$this->tokenSets,
			$this->createMock(AppBrandLogoStore::class),
			$this->createMock(IURLGenerator::class)
		);
		$controller = new AppBrandController('thematiq', $this->request, $brands, $appTheming, $this->tokenSets, $this->createMock(IL10N::class));

		$sent = $this->send($controller, 'save', ['appId' => 'collectives', 'tokenSet' => 'owned']);
		$this->assertSame(403, $sent['status']);
		$this->assertSame('restricted_set_not_confirmed', $sent['body']['reason']);

		$this->confirmSet('owned');
		$this->assertSame(200, $this->send($controller, 'save', ['appId' => 'collectives', 'tokenSet' => 'owned'])['status']);
	}//end testAnAppBrandIsDeliveredAs403()

	/**
	 * The preview service; the signed-in user is an administrator.
	 *
	 * @return ThemePreviewService The service.
	 */
	private function previewService(): ThemePreviewService {
		$groupManager = $this->createMock(IGroupManager::class);
		$groupManager->method('isAdmin')->willReturn(true);

		return new ThemePreviewService($this->config, $groupManager, $this->tokenSets, $this->createMock(LoggerInterface::class));
	}//end previewService()

	/**
	 * The licence controller over the real licence service.
	 *
	 * @return TokenSetLicenceController The controller.
	 */
	private function licenceController(): TokenSetLicenceController {
		$time = $this->createMock(ITimeFactory::class);
		$time->method('getDateTime')->willReturn(new \DateTime('2026-10-12T20:15:00+00:00'));

		return new TokenSetLicenceController(
			'thematiq',
			$this->request,
			new TokenSetLicenceService(
				$this->tokenSets,
				$this->config,
				new ScheduledSwitchStore($this->config),
				$this->createMock(ThemingAuditService::class),
				$time,
				$this->createMock(LoggerInterface::class)
			),
			Server::get(IUserSession::class)
		);
	}//end licenceController()

	/**
	 * The settings controller over the real set and group services.
	 *
	 * @return SettingsController The controller.
	 */
	private function settingsController(): SettingsController {
		$groupManager = $this->createMock(IGroupManager::class);
		$groupManager->method('groupExists')->willReturn(true);
		$cacheFactory = $this->createMock(ICacheFactory::class);
		$cacheFactory->method('createDistributed')->willReturn($this->createMock(ICache::class));
		$audit = $this->createMock(ThemingAuditService::class);

		return new SettingsController(
			'thematiq',
			$this->request,
			$this->config,
			$this->tokenSets,
			$this->createMock(ThemingService::class),
			$this->createMock(TokenSetPreviewService::class),
			$this->createMock(AppThemingService::class),
			$this->createMock(ComplianceReportService::class),
			$audit,
			$this->createMock(EmailThemingService::class),
			$this->createMock(UpstreamFreshnessService::class),
			new GroupThemingService(
				$this->config,
				$groupManager,
				$this->createMock(IUserSession::class),
				$this->tokenSets,
				$this->previewService(),
				$cacheFactory
			),
			new ActiveTokenSetService($this->config, $this->tokenSets, $audit)
		);
	}//end settingsController()
}//end class
