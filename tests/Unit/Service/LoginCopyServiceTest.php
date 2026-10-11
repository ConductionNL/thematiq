<?php

/**
 * Unit tests for the words on the login page of the light workplace.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/login-page-board-parity/specs/workplace-layout/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\DesignSystemService;
use OCA\Thematiq\Service\GroupThemingService;
use OCA\Thematiq\Service\LayoutOptionsService;
use OCA\Thematiq\Service\LoginCopyService;
use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;
use OCP\IL10N;
use OCP\L10N\IFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * The service resolves a set's `login` block and the administrator's
 * override per language, drops what may not be shown, and hands the result
 * to js/login-copy.js on the light workplace only.
 */
class LoginCopyServiceTest extends TestCase {

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
	 * The l10n factory mock.
	 *
	 * @var IFactory&MockObject
	 */
	private $l10nFactory;

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
		$this->l10nFactory = $this->createMock(IFactory::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->scripts = 0;
	}//end setUp()

	/**
	 * The service, with the script seam counted instead of emitted.
	 *
	 * @return LoginCopyService
	 */
	private function service(): LoginCopyService {
		$service = $this->getMockBuilder(LoginCopyService::class)
			->setConstructorArgs([$this->config, $this->designSystemService, $this->initialState, $this->l10nFactory, $this->logger])
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
	 * Give the set a `login` block and the administrator an override.
	 *
	 * @param array<string, mixed>|null $login The set's login block.
	 * @param string $override The stored `login_copy` value.
	 *
	 * @return void
	 */
	private function configure(?array $login, string $override = ''): void {
		$meta = ['design_system' => 'nldesign'];
		if ($login !== null) {
			$meta['login'] = $login;
		}

		$this->designSystemService->method('getTokenSetMeta')->willReturn($meta);
		$this->config->method('getAppValue')->willReturnCallback(
			fn (string $app, string $key, string $default) => ($key === LoginCopyService::CONFIG_KEY ? $override : $default)
		);
	}//end configure()

	/**
	 * A layout service that resolves the given workplace layout.
	 *
	 * @param string $layout `light` or `default`.
	 *
	 * @return LayoutOptionsService&MockObject
	 */
	private function layout(string $layout): LayoutOptionsService {
		$options = $this->createMock(LayoutOptionsService::class);
		$options->method('workplaceLayout')->willReturn($layout);

		return $options;
	}//end layout()

	/**
	 * Answer the request's language and translate "or".
	 *
	 * @param string $language The language code.
	 *
	 * @return void
	 */
	private function language(string $language): void {
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('getLanguageCode')->willReturn($language);
		$l10n->method('t')->willReturnCallback(fn (string $text) => ($language === 'nl' && $text === 'or' ? 'of' : $text));
		$this->l10nFactory->method('get')->willReturn($l10n);
	}//end language()

	/**
	 * The shipped Zuiddrecht copy, in Dutch, as the NcLogin board writes it.
	 *
	 * @spec openspec/changes/login-page-board-parity/specs/workplace-layout/spec.md#requirement-a-token-set-may-carry-the-login-copy
	 */
	public function testZuiddrechtShipsTheBoardsCopy(): void {
		$manifest = json_decode((string)file_get_contents(__DIR__ . '/../../../token-sets.json'), true);
		$entry = array_values(array_filter($manifest, fn (array $set) => $set['id'] === 'zuiddrecht'))[0];
		$this->configure(login: $entry['login']);

		$copy = $this->service()->resolveCopy(tokenSet: 'zuiddrecht', language: 'nl');

		$this->assertSame('Inloggen bij de werkplek', $copy['title']);
		$this->assertSame('Voor medewerkers van Gemeente Zuiddrecht.', $copy['subtitle']);
		$this->assertSame('Inloggen met uw gemeenteaccount', $copy['ssoLabel']);
		$this->assertSame('Gebruikersnaam of e-mail', $copy['usernameLabel']);
		$this->assertSame('Gemeente Zuiddrecht · werkplek op Nextcloud', $copy['footer']);
	}//end testZuiddrechtShipsTheBoardsCopy()

	/**
	 * A language map picks the request's language, then its primary subtag;
	 * a language it does not name gets nothing, so Nextcloud's words stay.
	 *
	 * @spec openspec/changes/login-page-board-parity/specs/workplace-layout/spec.md#requirement-a-token-set-may-carry-the-login-copy
	 */
	public function testALanguageMapPicksTheRequestsLanguage(): void {
		$this->configure(login: ['title' => ['nl' => 'Inloggen', 'en' => 'Log in'], 'footer' => 'Overal'], override: '');

		$this->assertSame(['title' => 'Inloggen', 'footer' => 'Overal'], $this->service()->resolveCopy(tokenSet: 'x', language: 'nl_NL'));
		$this->assertSame(['title' => 'Log in', 'footer' => 'Overal'], $this->service()->resolveCopy(tokenSet: 'x', language: 'en'));
		$this->assertSame(['footer' => 'Overal'], $this->service()->resolveCopy(tokenSet: 'x', language: 'de'));
	}//end testALanguageMapPicksTheRequestsLanguage()

	/**
	 * The administrator's override wins key by key; the set's other keys stay.
	 *
	 * @spec openspec/changes/login-page-board-parity/specs/workplace-layout/spec.md#requirement-a-token-set-may-carry-the-login-copy
	 */
	public function testTheOverrideWinsKeyByKey(): void {
		$this->configure(
			login: ['title' => 'Van de set', 'subtitle' => 'Ook van de set'],
			override: (string)json_encode(['title' => 'Van de beheerder', 'footer_hint' => 'Ouder?', 'footer_link_label' => 'Mijn school', 'footer_link_url' => '/apps/portaliq/'])
		);

		$this->assertSame(
			[
				'title' => 'Van de beheerder',
				'subtitle' => 'Ook van de set',
				'footerHint' => 'Ouder?',
				'footerLinkLabel' => 'Mijn school',
				'footerLinkUrl' => '/apps/portaliq/',
			],
			$this->service()->resolveCopy(tokenSet: 'x', language: 'nl')
		);
	}//end testTheOverrideWinsKeyByKey()

	/**
	 * What may not be shown is dropped: not text, empty, too long, an unknown
	 * key, and a link that is not a web address or a path on this server.
	 *
	 * @spec openspec/changes/login-page-board-parity/specs/workplace-layout/spec.md#requirement-a-token-set-may-carry-the-login-copy
	 */
	public function testWhatMayNotBeShownIsDropped(): void {
		$this->configure(
			login: [
				'title' => 42,
				'subtitle' => '   ',
				'footer' => str_repeat('a', LoginCopyService::MAX_LENGTH + 1),
				'script' => 'alert(1)',
				'footer_link_url' => 'javascript:alert(1)',
				'footer_link_label' => 'Mijn school',
			],
			override: '{not json'
		);

		$this->assertSame(['footerLinkLabel' => 'Mijn school'], $this->service()->resolveCopy(tokenSet: 'x', language: 'nl'));
	}//end testWhatMayNotBeShownIsDropped()

	/**
	 * A protocol-relative link points off this server without saying so; it is dropped.
	 *
	 * @spec openspec/changes/login-page-board-parity/specs/workplace-layout/spec.md#requirement-a-token-set-may-carry-the-login-copy
	 */
	public function testAProtocolRelativeLinkIsDropped(): void {
		$this->configure(login: ['footer_link_url' => '//elders.example/'], override: '');

		$this->assertSame([], $this->service()->resolveCopy(tokenSet: 'x', language: 'nl'));
	}//end testAProtocolRelativeLinkIsDropped()

	/**
	 * On the light workplace the copy is provided with a divider, and the
	 * script is loaded; a set without copy still gets the divider in the
	 * request's language.
	 *
	 * @spec openspec/changes/login-page-board-parity/specs/workplace-layout/spec.md#requirement-the-light-workplace-draws-the-login-card-of-the-board
	 */
	public function testTheLightWorkplaceLoadsTheScript(): void {
		$this->configure(login: null);
		$this->language('nl');
		$this->initialState->expects($this->once())->method('provideInitialState')->with(LoginCopyService::STATE_KEY, ['divider' => 'of']);

		$this->service()->inject(tokenSet: 'x', layoutOptions: $this->layout('light'));

		$this->assertSame(1, $this->scripts);
	}//end testTheLightWorkplaceLoadsTheScript()

	/**
	 * Every other layout keeps Nextcloud's login page: no state, no script.
	 *
	 * @spec openspec/changes/login-page-board-parity/specs/workplace-layout/spec.md#requirement-the-light-workplace-draws-the-login-card-of-the-board
	 */
	public function testTheDefaultLayoutLoadsNothing(): void {
		$this->configure(login: ['title' => 'Inloggen']);
		$this->language('nl');
		$this->initialState->expects($this->never())->method('provideInitialState');

		$this->service()->inject(tokenSet: 'x', layoutOptions: $this->layout('default'));

		$this->assertSame(0, $this->scripts);
	}//end testTheDefaultLayoutLoadsNothing()

	/**
	 * A failure is logged and leaves the page alone.
	 *
	 * @spec openspec/changes/login-page-board-parity/specs/workplace-layout/spec.md#requirement-the-light-workplace-draws-the-login-card-of-the-board
	 */
	public function testAFailureIsLoggedAndLeavesThePage(): void {
		$options = $this->createMock(LayoutOptionsService::class);
		$options->method('workplaceLayout')->willThrowException(new RuntimeException('boom'));
		$this->logger->expects($this->once())->method('warning');
		$this->initialState->expects($this->never())->method('provideInitialState');

		$this->service()->inject(tokenSet: 'x', layoutOptions: $options);

		$this->assertSame(0, $this->scripts);
	}//end testAFailureIsLoggedAndLeavesThePage()

	/**
	 * The login page's entry resolves the set without an app brand and
	 * injects for it; a resolver failure is logged and loads nothing.
	 *
	 * @spec openspec/changes/login-page-board-parity/specs/workplace-layout/spec.md#requirement-the-light-workplace-draws-the-login-card-of-the-board
	 */
	public function testTheLoginEntryResolvesTheSetAndFailsOpen(): void {
		$this->configure(login: ['title' => 'Inloggen']);
		$this->language('nl');
		$groups = $this->createMock(GroupThemingService::class);
		$groups->method('resolveTokenSetForRequest')->with(null)->willReturn('zuiddrecht');
		$this->initialState->expects($this->once())->method('provideInitialState')->with(LoginCopyService::STATE_KEY, ['title' => 'Inloggen', 'divider' => 'of']);

		$service = $this->getMockBuilder(LoginCopyService::class)
			->setConstructorArgs([$this->config, $this->designSystemService, $this->initialState, $this->l10nFactory, $this->logger, $groups, $this->layout('light')])
			->onlyMethods(['emitScript'])
			->getMock();
		$service->injectForLogin();

		$broken = $this->createMock(GroupThemingService::class);
		$broken->method('resolveTokenSetForRequest')->willThrowException(new RuntimeException('boom'));
		$this->logger->expects($this->once())->method('warning');
		$failing = new LoginCopyService($this->config, $this->designSystemService, $this->initialState, $this->l10nFactory, $this->logger, $broken, $this->layout('light'));
		$failing->injectForLogin();
	}//end testTheLoginEntryResolvesTheSetAndFailsOpen()
}//end class
