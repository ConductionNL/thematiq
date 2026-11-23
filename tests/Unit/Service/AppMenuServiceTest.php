<?php

/**
 * Unit tests for the sections of the workplace apps menu.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/apps-menu-board/specs/workplace-layout/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\AppMenuListState;
use OCA\Thematiq\Service\AppMenuOrder;
use OCA\Thematiq\Service\AppMenuPins;
use OCA\Thematiq\Service\AppMenuService;
use OCA\Thematiq\Service\AppMenuSettings;
use OCA\Thematiq\Service\DesignSystemService;
use OCA\Thematiq\Service\ExternalApps;
use OCA\Thematiq\Service\LayoutOptionsService;
use OCA\Thematiq\Service\OpenProjectModules;
use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserSession;
use OCP\L10N\IFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * The service resolves a set's `app_menu` block and the override, drops what
 * it may not show, and loads the script with the workplace header only.
 */
class AppMenuServiceTest extends TestCase {

	/**
	 * The config mock.
	 *
	 * @var IConfig&MockObject
	 */
	private $config;

	/**
	 * The design system service mock.
	 *
	 * @var DesignSystemService&MockObject
	 */
	private $designSystemService;

	/**
	 * The initial state mock.
	 *
	 * @var IInitialState&MockObject
	 */
	private $initialState;

	/**
	 * The logger mock.
	 *
	 * @var LoggerInterface&MockObject
	 */
	private $logger;

	/**
	 * How often the script was emitted.
	 *
	 * @var int
	 */
	private int $scripts = 0;

	/**
	 * Set up the mocks.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->config = $this->createMock(originalClassName: IConfig::class);
		$this->designSystemService = $this->createMock(originalClassName: DesignSystemService::class);
		$this->initialState = $this->createMock(originalClassName: IInitialState::class);
		$this->logger = $this->createMock(originalClassName: LoggerInterface::class);
		$this->scripts = 0;
	}//end setUp()

	/**
	 * Give the set an `app_menu` block and the administrator an override.
	 *
	 * @param array<string, mixed>|null $block The set's block.
	 * @param string $override The stored override.
	 *
	 * @return void
	 */
	private function configure(?array $block, string $override = ''): void {
		$meta = ['design_system' => 'nldesign'];
		if ($block !== null) {
			$meta['app_menu'] = $block;
		}

		$this->designSystemService->method('getTokenSetMeta')->willReturn($meta);
		$this->config->method('getAppValue')->willReturnCallback(
			fn (string $app, string $key, string $default) => (match ($key === AppMenuService::CONFIG_KEY) {
				true => $override, default => $default
			})
		);
	}//end configure()

	/**
	 * The service, with the script seam counted instead of emitted.
	 *
	 * @return AppMenuService
	 */
	private function service(): AppMenuService {
		$l10n = $this->createMock(originalClassName: IL10N::class);
		$l10n->method('getLanguageCode')->willReturn('nl');
		$factory = $this->createMock(originalClassName: IFactory::class);
		$factory->method('get')->willReturn($l10n);

		$service = $this->getMockBuilder(className: AppMenuService::class)
			->setConstructorArgs([$this->config, $this->designSystemService, $this->initialState, $factory, $this->logger])
			->onlyMethods(['emitScript', 'emitMenuStyle'])
			->getMock();
		$service->method('emitScript')->willReturnCallback(
			function (): void {
				$this->scripts++;
			}
		);

		return $service;
	}//end service()

	/**
	 * A layout service whose stylesheets do or do not include the workplace header.
	 *
	 * @param bool $workplace Whether the header style resolves to `workplace`.
	 *
	 * @return LayoutOptionsService&MockObject
	 */
	private function layout(bool $workplace): LayoutOptionsService {
		$options = $this->createMock(originalClassName: LayoutOptionsService::class);
		$options->method('stylesheets')->willReturn(match ($workplace === true) {
			true => ['workplace-layout', LayoutOptionsService::HEADER_WORKPLACE_STYLESHEET], default => ['workplace-layout']
		});

		return $options;
	}//end layout()

	/**
	 * The state the ordered list adds without a user or an external app: an empty order and the
	 * labels, in the English source text.
	 *
	 * @return array<string, mixed> `order` and `labels`.
	 */
	private function ordered(): array {
		return [
			'order' => [],
			'pins' => [],
			'settings' => array_diff_key(AppMenuSettings::DEFAULTS, ['order' => 0, 'pinned' => 0, 'externalApps' => 0]),
			'admin' => false,
			'labels' => [
				'title' => 'Apps',
				'allApps' => 'More apps',
				'hideApps' => 'Fewer apps',
				'dragHint' => 'Drag an app to reorder it or pin it to the top bar.',
				'moveHint' => 'Alt with an arrow key moves, Alt+P pins or unpins.',
				'moved' => '{app} moved to place {place} of {total}.',
				'saveFailed' => 'The order could not be saved.',
				'create' => 'Create',
				'newDocument' => 'New document',
				'newSpreadsheet' => 'New spreadsheet',
				'newPresentation' => 'New presentation',
				'newWhiteboard' => 'New whiteboard',
				'more' => 'More',
				'createFailed' => 'The file could not be created.',
				'pinned' => 'Pinned apps',
				'dropToPin' => 'Drop to pin',
				'releaseToRemove' => 'Release to remove',
				'pinnedApp' => '{app} pinned to the top bar.',
				'unpinnedApp' => '{app} removed from the top bar.',
				'pinFull' => 'You can pin up to {max} apps.',
				'pinSaveFailed' => 'The pinned apps could not be saved.',
				'menuSettings' => 'Menu settings',
			],
		];
	}//end ordered()

	/**
	 * The list state over a config, with a signed-in user or none.
	 *
	 * @param IConfig $config The config.
	 * @param string|null $uid The signed-in user's id.
	 *
	 * @return AppMenuListState
	 */
	private function listState(IConfig $config, ?string $uid): AppMenuListState {
		$session = $this->createMock(originalClassName: IUserSession::class);
		$user = null;
		if ($uid !== null) {
			$user = $this->createMock(originalClassName: IUser::class);
			$user->method('getUID')->willReturn($uid);
		}

		$session->method('getUser')->willReturn($user);

		return new AppMenuListState(
			externalApps: new ExternalApps(config: $config, openProject: new OpenProjectModules(config: $config)),
			order: new AppMenuOrder(config: $config),
			userSession: $session,
			pins: new AppMenuPins(config: $config),
			settings: new AppMenuSettings(config: $config)
		);
	}//end listState()

	/**
	 * The signed-in user's order and the external apps join the menu's state.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/apps-menu-ordered-list/specs/apps-menu-order/spec.md#requirement-the-order-is-stored-for-the-person-on-the-server
	 * @spec openspec/changes/apps-menu-ordered-list/specs/external-apps-in-the-app-menu/spec.md#requirement-the-apps-menu-lists-the-external-apps-that-have-an-address
	 */
	public function testTheOrderAndTheExternalAppsJoinTheMenu(): void {
		$this->configure(block: ['hint' => 'Hint']);
		$config = $this->createMock(originalClassName: IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			static fn (string $app, string $key, $default = '') => ([ExternalApps::CONFIG_KEY => '{"code":"https://code.example.org"}'][$key] ?? $default)
		);
		$config->method('getUserValue')->willReturnCallback(
			static fn (string $user, string $app, string $key, $default = '') => (
				['anna' . AppMenuOrder::KEY => '["files","external-code"]', 'anna' . AppMenuPins::KEY => '["deck"]'][$user . $key] ?? $default
			)
		);
		$l10n = $this->createMock(originalClassName: IL10N::class);
		$l10n->method('getLanguageCode')->willReturn('en');
		$l10n->method('t')->willReturnArgument(0);
		$factory = $this->createMock(originalClassName: IFactory::class);
		$factory->method('get')->willReturn($l10n);
		$state = null;
		$this->initialState->method('provideInitialState')->willReturnCallback(
			function (string $key, $value) use (&$state): void {
				$state = $value;
			}
		);
		$service = $this->getMockBuilder(className: AppMenuService::class)
			->setConstructorArgs(
				[$this->config, $this->designSystemService, $this->initialState, $factory, $this->logger, $this->listState(config: $config, uid: 'anna')]
			)
			->onlyMethods(['emitScript', 'emitMenuStyle'])
			->getMock();
		$service->inject(tokenSet: 'zuiddrecht', layoutOptions: $this->layout(workplace: true));

		$this->assertSame(expected: ['files', 'external-code'], actual: $state['order']);
		$this->assertSame(expected: ['deck'], actual: $state['pins']);
		$this->assertSame(expected: 'External apps', actual: $state['external']['title']);
		$this->assertSame(
			expected: [
				[
					'key' => 'external-code',
					'label' => 'Forgejo',
					'line' => 'Code',
					'url' => 'https://code.example.org',
					'icon' => ExternalApps::APPS['code']['icon'],
				],
			],
			actual: $state['external']['apps']
		);
		$this->assertSame(expected: 'More apps', actual: $state['labels']['allApps']);
	}//end testTheOrderAndTheExternalAppsJoinTheMenu()

	/**
	 * The shipped Zuiddrecht menu, in Dutch, as the AppMenu board draws it.
	 *
	 * @spec openspec/changes/apps-menu-board/specs/workplace-layout/spec.md#requirement-a-token-set-may-group-the-apps-menu
	 *
	 * @return void
	 */
	public function testZuiddrechtShipsTheBoardsSections(): void {
		$manifest = json_decode((string)file_get_contents(__DIR__ . '/../../../token-sets.json'), true);
		$entry = array_values(array_filter($manifest, fn (array $set) => $set['id'] === 'zuiddrecht'))[0];
		$this->configure(block: $entry['app_menu']);

		$menu = $this->service()->resolveMenu(tokenSet: 'zuiddrecht', language: 'nl');

		$this->assertSame(expected: 'Werk', actual: $menu['groups'][0]['title']);
		$this->assertSame(expected: ['label' => 'Mijn werkdag in launchpad', 'url' => '/apps/launchpad/'], actual: $menu['groups'][0]['link']);
		$this->assertSame(expected: ['id' => 'dossiq', 'description' => 'Zaken en Woo'], actual: $menu['groups'][0]['apps'][1]);
		$this->assertCount(expectedCount: 8, haystack: $menu['groups'][0]['apps']);
		$this->assertSame(expected: 'Nextcloud', actual: $menu['othersTitle']);
		$this->assertSame(expected: 'Apps beheren', actual: $menu['manageLabel']);
	}//end testZuiddrechtShipsTheBoardsSections()

	/**
	 * Invalid app ids, untitled or empty sections and unsafe links are
	 * dropped; the override's sections replace the set's.
	 *
	 * @spec openspec/changes/apps-menu-board/specs/workplace-layout/spec.md#requirement-a-token-set-may-group-the-apps-menu
	 *
	 * @return void
	 */
	public function testWhatMayNotBeShownIsDropped(): void {
		$this->configure(
			block: ['groups' => [['title' => 'Van de set', 'apps' => ['files']]], 'hint' => 'Hint'],
			override: (string)json_encode(
				[
					'groups' => [
						['title' => 'Werk', 'link' => ['label' => 'Kwaad', 'url' => 'javascript:alert(1)'], 'apps' => ['dossiq', 'Bad-Id', ['id' => '../x'], ['id' => 'pipelinq', 'description' => 'Klantcontact']]],
						['title' => 'Leeg', 'apps' => []],
						['apps' => ['files']],
					],
				]
			)
		);

		$this->assertSame(
			expected: [
				'hint' => 'Hint',
				'groups' => [
					['title' => 'Werk', 'apps' => [['id' => 'dossiq'], ['id' => 'pipelinq', 'description' => 'Klantcontact']]],
				],
			],
			actual: $this->service()->resolveMenu(tokenSet: 'x', language: 'nl')
		);
	}//end testWhatMayNotBeShownIsDropped()

	/**
	 * With the workplace header the menu is provided and the script loaded;
	 * without it nothing; a failure is logged.
	 *
	 * @spec openspec/changes/apps-menu-board/specs/workplace-layout/spec.md#requirement-the-workplace-header-draws-the-apps-menu-of-the-board
	 *
	 * @return void
	 */
	public function testTheWorkplaceHeaderLoadsTheScriptAndNothingElseDoes(): void {
		$this->configure(block: ['hint' => 'Hint']);
		$this->initialState->expects($this->once())->method('provideInitialState')->with(AppMenuService::STATE_KEY, ['hint' => 'Hint']);
		$this->logger->expects($this->once())->method('warning');

		$service = $this->service();
		$service->inject(tokenSet: 'zuiddrecht', layoutOptions: $this->layout(workplace: true));
		$service->inject(tokenSet: 'zuiddrecht', layoutOptions: $this->layout(workplace: false));
		$broken = $this->createMock(originalClassName: LayoutOptionsService::class);
		$broken->method('stylesheets')->willThrowException(new RuntimeException('boom'));
		$service->inject(tokenSet: 'zuiddrecht', layoutOptions: $broken);

		$this->assertSame(expected: 1, actual: $this->scripts);
	}//end testTheWorkplaceHeaderLoadsTheScriptAndNothingElseDoes()

	/**
	 * With OpenProject's address, its modules are external apps.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/apps-menu-ordered-list/specs/external-apps-in-the-app-menu/spec.md#requirement-the-apps-menu-lists-the-external-apps-that-have-an-address
	 */
	public function testOpenProjectsModulesJoinTheMenu(): void {
		$this->configure(block: ['hint' => 'Hint']);
		$config = $this->createMock(originalClassName: IConfig::class);
		$config->method('getAppValue')->willReturnCallback(static fn (string $app, string $key, $default = '') => ['openproject_url' => 'https://op.example.org/', 'openproject_modules' => 'boards, nope, meetings'][$key] ?? $default);
		$l10n = $this->createMock(originalClassName: IL10N::class);
		$l10n->method('getLanguageCode')->willReturn('nl');
		$l10n->method('t')->willReturnArgument(0);
		$factory = $this->createMock(originalClassName: IFactory::class);
		$factory->method('get')->willReturn($l10n);

		$this->initialState->expects($this->once())->method('provideInitialState')->with(
			AppMenuService::STATE_KEY,
			[
				'hint' => 'Hint',
				'external' => [
					'title' => 'External apps',
					'note' => 'opens in a new tab',
					'apps' => [
						[
							'key' => 'external-openproject-boards',
							'label' => 'Boards',
							'line' => 'OpenProject',
							'url' => 'https://op.example.org/boards',
							'icon' => OpenProjectModules::MODULES['boards']['icon'],
						],
						[
							'key' => 'external-openproject-meetings',
							'label' => 'Meetings',
							'line' => 'OpenProject',
							'url' => 'https://op.example.org/meetings',
							'icon' => OpenProjectModules::MODULES['meetings']['icon'],
						],
					],
				],
			] + $this->ordered()
		);

		$service = $this->getMockBuilder(className: AppMenuService::class)
			->setConstructorArgs(
				[$this->config, $this->designSystemService, $this->initialState, $factory, $this->logger, $this->listState(config: $config, uid: null)]
			)
			->onlyMethods(['emitScript', 'emitMenuStyle'])
			->getMock();
		$service->inject(tokenSet: 'zuiddrecht', layoutOptions: $this->layout(workplace: true));
	}//end testOpenProjectsModulesJoinTheMenu()

	/**
	 * Only an http or https address counts, and unknown module keys fall back to the default.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/apps-menu-ordered-list/specs/external-apps-in-the-app-menu/spec.md#requirement-the-apps-menu-lists-the-external-apps-that-have-an-address
	 */
	public function testTheAddressAndTheModulesAreChecked(): void {
		$this->assertSame(
			expected: 'https://op.example.org/sub',
			actual: (new OpenProjectModules(config: $this->createMock(originalClassName: IConfig::class)))->normalize(value: ' https://op.example.org/sub// ')
		);
		foreach (['', 'javascript:alert(1)', 'ftp://op.example.org', 'https://op.example.org/"><script>', '//op.example.org'] as $bad) {
			$this->assertNull(
				actual: (new OpenProjectModules(config: $this->createMock(originalClassName: IConfig::class)))->normalize(value: $bad),
				message: $bad
			);
		}

		$this->assertSame(
			expected: OpenProjectModules::DEFAULT,
			actual: (new OpenProjectModules(config: $this->createMock(originalClassName: IConfig::class)))->wanted(value: '')
		);
		$this->assertSame(
			expected: OpenProjectModules::DEFAULT,
			actual: (new OpenProjectModules(config: $this->createMock(originalClassName: IConfig::class)))->wanted(value: 'nope, also-nope')
		);
		$this->assertSame(
			expected: ['news', 'projects'],
			actual: (new OpenProjectModules(config: $this->createMock(originalClassName: IConfig::class)))->wanted(value: 'News|projects news')
		);
		$this->assertCount(
			expectedCount: OpenProjectModules::MAX,
			haystack: (new OpenProjectModules(config: $this->createMock(originalClassName: IConfig::class)))->wanted(value: implode(
				',',
				array_keys(OpenProjectModules::MODULES)
			))
		);

		$config = $this->createMock(originalClassName: IConfig::class);
		$config->method('getAppValue')->willReturn('');
		$this->assertNull(
			actual: (new OpenProjectModules(config: $config))->section(l: $this->createMock(originalClassName: IL10N::class)),
			message: 'no address, no section'
		);
	}//end testTheAddressAndTheModulesAreChecked()

	/**
	 * With the classic style a person gets Nextcloud's apps menu as it ships: no state and no
	 * script. An administrator keeps the gear, so only the dialog's script and stylesheet load.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/apps-menu-create-pins-settings/specs/apps-menu-settings/spec.md#requirement-the-classic-style-leaves-nextclouds-apps-menu-alone
	 */
	public function testTheClassicStyleLeavesTheMenuAlone(): void {
		$this->configure(block: ['hint' => 'Hint']);
		$config = $this->createMock(originalClassName: IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			static fn (string $app, string $key, $default = '') => (['menu.style' => '"classic"'][$key] ?? $default)
		);
		$l10n = $this->createMock(originalClassName: IL10N::class);
		$l10n->method('getLanguageCode')->willReturn('en');
		$l10n->method('t')->willReturnArgument(0);
		$factory = $this->createMock(originalClassName: IFactory::class);
		$factory->method('get')->willReturn($l10n);

		foreach ([false, true] as $admin) {
			$initialState = $this->createMock(originalClassName: IInitialState::class);
			$calls = $this->never();
			if ($admin === true) {
				$calls = $this->once();
			}

			$initialState->expects($calls)->method('provideInitialState');
			$groups = $this->createMock(originalClassName: IGroupManager::class);
			$groups->method('isAdmin')->willReturn($admin);
			$session = $this->createMock(originalClassName: IUserSession::class);
			$user = $this->createMock(originalClassName: IUser::class);
			$user->method('getUID')->willReturn('anna');
			$session->method('getUser')->willReturn($user);
			$listState = new AppMenuListState(
				externalApps: new ExternalApps(config: $config, openProject: new OpenProjectModules(config: $config)),
				order: new AppMenuOrder(config: $config),
				userSession: $session,
				pins: new AppMenuPins(config: $config),
				settings: new AppMenuSettings(config: $config),
				groups: $groups
			);
			$service = $this->getMockBuilder(className: AppMenuService::class)
				->setConstructorArgs([$this->config, $this->designSystemService, $initialState, $factory, $this->logger, $listState])
				->onlyMethods(['emitScript', 'emitMenuStyle'])
				->getMock();
			$emits = $this->never();
			if ($admin === true) {
				$emits = $this->once();
			}

			$service->expects($emits)->method('emitScript');
			$service->expects($this->never())->method('emitMenuStyle');

			$service->inject(tokenSet: 'zuiddrecht', layoutOptions: $this->layout(workplace: true));
		}
	}//end testTheClassicStyleLeavesTheMenuAlone()
}//end class
