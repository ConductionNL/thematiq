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

use OCA\Thematiq\Service\AppMenuService;
use OCA\Thematiq\Service\DesignSystemService;
use OCA\Thematiq\Service\LayoutOptionsService;
use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;
use OCP\IL10N;
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
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->config = $this->createMock(IConfig::class);
		$this->designSystemService = $this->createMock(DesignSystemService::class);
		$this->initialState = $this->createMock(IInitialState::class);
		$this->logger = $this->createMock(LoggerInterface::class);
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
			fn (string $app, string $key, string $default) => ($key === AppMenuService::CONFIG_KEY ? $override : $default)
		);
	}//end configure()

	/**
	 * The service, with the script seam counted instead of emitted.
	 *
	 * @return AppMenuService
	 */
	private function service(): AppMenuService {
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('getLanguageCode')->willReturn('nl');
		$factory = $this->createMock(IFactory::class);
		$factory->method('get')->willReturn($l10n);

		$service = $this->getMockBuilder(AppMenuService::class)
			->setConstructorArgs([$this->config, $this->designSystemService, $this->initialState, $factory, $this->logger])
			->onlyMethods(['emitScript'])
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
		$options = $this->createMock(LayoutOptionsService::class);
		$options->method('stylesheets')->willReturn($workplace === true ? ['workplace-layout', LayoutOptionsService::HEADER_WORKPLACE_STYLESHEET] : ['workplace-layout']);

		return $options;
	}//end layout()

	/**
	 * The shipped Zuiddrecht menu, in Dutch, as the AppMenu board draws it.
	 *
	 * @spec openspec/changes/apps-menu-board/specs/workplace-layout/spec.md#requirement-a-token-set-may-group-the-apps-menu
	 */
	public function testZuiddrechtShipsTheBoardsSections(): void {
		$manifest = json_decode((string)file_get_contents(__DIR__ . '/../../../token-sets.json'), true);
		$entry = array_values(array_filter($manifest, fn (array $set) => $set['id'] === 'zuiddrecht'))[0];
		$this->configure(block: $entry['app_menu']);

		$menu = $this->service()->resolveMenu(tokenSet: 'zuiddrecht', language: 'nl');

		$this->assertSame('Werk', $menu['groups'][0]['title']);
		$this->assertSame(['label' => 'Mijn werkdag in launchpad', 'url' => '/apps/launchpad/'], $menu['groups'][0]['link']);
		$this->assertSame(['id' => 'dossiq', 'description' => 'Zaken en Woo'], $menu['groups'][0]['apps'][1]);
		$this->assertCount(8, $menu['groups'][0]['apps']);
		$this->assertSame('Nextcloud', $menu['othersTitle']);
		$this->assertSame('Apps beheren', $menu['manageLabel']);
	}//end testZuiddrechtShipsTheBoardsSections()

	/**
	 * Invalid app ids, untitled or empty sections and unsafe links are
	 * dropped; the override's sections replace the set's.
	 *
	 * @spec openspec/changes/apps-menu-board/specs/workplace-layout/spec.md#requirement-a-token-set-may-group-the-apps-menu
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
			[
				'hint' => 'Hint',
				'groups' => [
					['title' => 'Werk', 'apps' => [['id' => 'dossiq'], ['id' => 'pipelinq', 'description' => 'Klantcontact']]],
				],
			],
			$this->service()->resolveMenu(tokenSet: 'x', language: 'nl')
		);
	}//end testWhatMayNotBeShownIsDropped()

	/**
	 * With the workplace header the menu is provided and the script loaded;
	 * without it nothing; a failure is logged.
	 *
	 * @spec openspec/changes/apps-menu-board/specs/workplace-layout/spec.md#requirement-the-workplace-header-draws-the-apps-menu-of-the-board
	 */
	public function testTheWorkplaceHeaderLoadsTheScriptAndNothingElseDoes(): void {
		$this->configure(block: ['hint' => 'Hint']);
		$this->initialState->expects($this->once())->method('provideInitialState')->with(AppMenuService::STATE_KEY, ['hint' => 'Hint']);
		$this->logger->expects($this->once())->method('warning');

		$service = $this->service();
		$service->inject(tokenSet: 'zuiddrecht', layoutOptions: $this->layout(workplace: true));
		$service->inject(tokenSet: 'zuiddrecht', layoutOptions: $this->layout(workplace: false));
		$broken = $this->createMock(LayoutOptionsService::class);
		$broken->method('stylesheets')->willThrowException(new RuntimeException('boom'));
		$service->inject(tokenSet: 'zuiddrecht', layoutOptions: $broken);

		$this->assertSame(1, $this->scripts);
	}//end testTheWorkplaceHeaderLoadsTheScriptAndNothingElseDoes()
}//end class
