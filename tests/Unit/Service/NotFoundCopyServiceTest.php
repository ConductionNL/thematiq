<?php

/**
 * Unit tests for the words and links on Nextcloud's "page not found" page.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/not-found-page-board/specs/workplace-layout/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\DesignSystemService;
use OCA\Thematiq\Service\GroupThemingService;
use OCA\Thematiq\Service\LayoutOptionsService;
use OCA\Thematiq\Service\NotFoundCopyService;
use OCA\Thematiq\Service\PageCopySources;
use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;
use OCP\IL10N;
use OCP\L10N\IFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * The service resolves a set's `not_found` block and the override, drops
 * links it may not show, and loads the script on the light workplace only.
 */
class NotFoundCopyServiceTest extends TestCase {

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
	 * Give the set a `not_found` block and the administrator an override.
	 *
	 * @param array<string, mixed>|null $block The set's block.
	 * @param string $override The stored override.
	 *
	 * @return void
	 */
	private function configure(?array $block, string $override = ''): void {
		$meta = ['design_system' => 'nldesign'];
		if ($block !== null) {
			$meta['not_found'] = $block;
		}

		$this->designSystemService->method('getTokenSetMeta')->willReturn($meta);
		$this->config->method('getAppValue')->willReturnCallback(
			fn (string $app, string $key, string $default) => ($key === NotFoundCopyService::CONFIG_KEY ? $override : $default)
		);
	}//end configure()

	/**
	 * The service for a page on this layout, or with a failing resolver.
	 *
	 * @param string $layout `light` or `default`.
	 * @param bool $failing Whether resolving the set throws.
	 *
	 * @return NotFoundCopyService
	 */
	private function service(string $layout = 'light', bool $failing = false): NotFoundCopyService {
		$groups = $this->createMock(originalClassName: GroupThemingService::class);
		if ($failing === true) {
			$groups->method('resolveTokenSetForRequest')->willThrowException(new RuntimeException('boom'));
		} else {
			$groups->method('resolveTokenSetForRequest')->with(null)->willReturn('zuiddrecht');
		}

		$options = $this->createMock(originalClassName: LayoutOptionsService::class);
		$options->method('workplaceLayout')->willReturn($layout);
		$l10n = $this->createMock(originalClassName: IL10N::class);
		$l10n->method('getLanguageCode')->willReturn('nl');
		$factory = $this->createMock(originalClassName: IFactory::class);
		$factory->method('get')->willReturn($l10n);

		$service = $this->getMockBuilder(className: NotFoundCopyService::class)
			->setConstructorArgs(
				[
					new PageCopySources(
						config: $this->config,
						designSystemService: $this->designSystemService,
						initialState: $this->initialState,
						l10nFactory: $factory
					),
					$this->logger,
					$groups,
					$options,
				]
			)
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
	 * The shipped Zuiddrecht copy, in Dutch, as the NietGevonden board writes it.
	 *
	 * @spec openspec/changes/not-found-page-board/specs/workplace-layout/spec.md#requirement-a-token-set-may-carry-the-not-found-copy
	 *
	 * @return void
	 */
	public function testZuiddrechtShipsTheBoardsCopy(): void {
		$manifest = json_decode((string)file_get_contents(__DIR__ . '/../../../token-sets.json'), true);
		$entry = array_values(array_filter($manifest, fn (array $set) => $set['id'] === 'zuiddrecht'))[0];
		$this->configure(block: $entry['not_found']);

		$copy = $this->service()->resolveCopy(tokenSet: 'zuiddrecht', language: 'nl');

		$this->assertSame(expected: 'Foutcode 404', actual: $copy['eyebrow']);
		$this->assertSame(expected: 'Pagina niet gevonden', actual: $copy['title']);
		$this->assertSame(expected: 'Of ga verder naar', actual: $copy['linksHeading']);
		$this->assertSame(expected: [['label' => 'De homepage', 'url' => '/']], actual: $copy['links']);
	}//end testZuiddrechtShipsTheBoardsCopy()

	/**
	 * Links without a label, with a script address or off this server
	 * without a scheme are dropped; the override's list replaces the set's.
	 *
	 * @spec openspec/changes/not-found-page-board/specs/workplace-layout/spec.md#requirement-a-token-set-may-carry-the-not-found-copy
	 *
	 * @return void
	 */
	public function testLinksTheOverrideMayNotShowAreDropped(): void {
		$this->configure(
			block: ['title' => 'Niet gevonden', 'links' => [['label' => 'Thuis', 'url' => '/']]],
			override: (string)json_encode(
				[
					'links' => [
						['label' => 'Mijn Zuiddrecht', 'url' => '/apps/portaliq/'],
						['label' => 'Contact', 'url' => 'https://zuiddrecht.example/contact'],
						['label' => 'Kwaad', 'url' => 'javascript:alert(1)'],
						['label' => 'Elders', 'url' => '//elders.example/'],
						['url' => '/zonder-label'],
						'geen lijst',
					],
				]
			)
		);

		$this->assertSame(
			expected: [
				'title' => 'Niet gevonden',
				'links' => [
					['label' => 'Mijn Zuiddrecht', 'url' => '/apps/portaliq/'],
					['label' => 'Contact', 'url' => 'https://zuiddrecht.example/contact'],
				],
			],
			actual: $this->service()->resolveCopy(tokenSet: 'zuiddrecht', language: 'nl')
		);
	}//end testLinksTheOverrideMayNotShowAreDropped()

	/**
	 * The light workplace provides the copy and loads the script.
	 *
	 * @spec openspec/changes/not-found-page-board/specs/workplace-layout/spec.md#requirement-the-light-workplace-draws-the-not-found-page-of-the-board
	 *
	 * @return void
	 */
	public function testTheLightWorkplaceLoadsTheScript(): void {
		$this->configure(block: ['title' => ['nl' => 'Pagina niet gevonden', 'en' => 'Page not found']]);
		$this->initialState->expects($this->once())->method('provideInitialState')->with(NotFoundCopyService::STATE_KEY, ['title' => 'Pagina niet gevonden']);

		$this->service()->inject();

		$this->assertSame(expected: 1, actual: $this->scripts);
	}//end testTheLightWorkplaceLoadsTheScript()

	/**
	 * Every other layout keeps Nextcloud's page; a failure is logged.
	 *
	 * @spec openspec/changes/not-found-page-board/specs/workplace-layout/spec.md#requirement-the-light-workplace-draws-the-not-found-page-of-the-board
	 *
	 * @return void
	 */
	public function testTheDefaultLayoutAndAFailureLoadNothing(): void {
		$this->configure(block: ['title' => 'Niet gevonden']);
		$this->initialState->expects($this->never())->method('provideInitialState');
		$this->logger->expects($this->once())->method('warning');

		$this->service(layout: 'default')->inject();
		$this->service(failing: true)->inject();

		$this->assertSame(expected: 0, actual: $this->scripts);
	}//end testTheDefaultLayoutAndAFailureLoadNothing()
}//end class
