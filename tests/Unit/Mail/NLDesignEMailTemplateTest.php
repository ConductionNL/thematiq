<?php

/**
 * Unit tests for NLDesignEMailTemplate.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/email-theming/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Mail;

use OC\Mail\EMailTemplate;
use OCA\Thematiq\Mail\NLDesignEMailTemplate;
use OCA\Thematiq\Service\EmailThemingService;
use OCP\Defaults;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use PHPUnit\Framework\TestCase;

/**
 * Test seam: overrides the private-service resolver so a stubbed
 * EmailThemingService (or null, for the fallback-proof scenario) can be
 * injected without touching the live \OCP\Server container the real
 * `Mailer::makeTemplate()` resolves through.
 */
class TestableNLDesignEMailTemplate extends NLDesignEMailTemplate {

	/**
	 * The stubbed email theming service, or null.
	 *
	 * @var EmailThemingService|null
	 */
	private ?EmailThemingService $stubService = null;

	/**
	 * Inject a stubbed service (or null, to simulate total resolution failure).
	 *
	 * @param EmailThemingService|null $service The stubbed service.
	 *
	 * @return void
	 */
	public function setStubService(?EmailThemingService $service): void {
		$this->stubService = $service;
	}//end setStubService()

	/**
	 * Test seam override — returns the injected stub instead of resolving
	 * \OCP\Server::get().
	 *
	 * @return EmailThemingService|null The stubbed service.
	 */
	protected function getEmailThemingService(): ?EmailThemingService {
		return $this->stubService;
	}//end getEmailThemingService()
}//end class

/**
 * Unit tests for NLDesignEMailTemplate.
 */
class NLDesignEMailTemplateTest extends TestCase {

	/**
	 * The theming defaults mock.
	 *
	 * @var Defaults
	 */
	private Defaults $themingDefaults;

	/**
	 * The URL generator mock.
	 *
	 * @var IURLGenerator
	 */
	private IURLGenerator $urlGenerator;

	/**
	 * The l10n factory mock.
	 *
	 * @var IFactory
	 */
	private IFactory $l10nFactory;

	/**
	 * Set up shared constructor-argument mocks before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->themingDefaults = $this->createMock(originalClassName: Defaults::class);
		$this->themingDefaults->method('getDefaultColorPrimary')->willReturn('#0082c9');
		$this->themingDefaults->method('getDefaultTextColorPrimary')->willReturn('#ffffff');
		$this->themingDefaults->method('getLogo')->willReturn('/core/img/logo/logo.svg');
		$this->themingDefaults->method('getName')->willReturn('Nextcloud');
		$this->themingDefaults->method('getSlogan')->willReturn('');

		$this->urlGenerator = $this->createMock(originalClassName: IURLGenerator::class);
		$this->urlGenerator->method('getAbsoluteURL')->willReturnCallback(
			fn (string $url) => 'https://cloud.example.com' . $url
		);

		$l10n = $this->createMock(originalClassName: IL10N::class);
		$l10n->method('t')->willReturnCallback(fn (string $text, $params = []) => $text);

		$this->l10nFactory = $this->createMock(originalClassName: IFactory::class);
		$this->l10nFactory->method('get')->willReturn($l10n);
	}//end setUp()

	/**
	 * Build a testable branded template, stubbed with the given service.
	 *
	 * @param EmailThemingService|null $service The stubbed service, or null.
	 *
	 * @return TestableNLDesignEMailTemplate The template under test.
	 */
	private function makeTemplate(?EmailThemingService $service): TestableNLDesignEMailTemplate {
		$template = new TestableNLDesignEMailTemplate(
			$this->themingDefaults,
			$this->urlGenerator,
			$this->l10nFactory,
			32,
			32,
			'test',
			[]
		);
		$template->setStubService($service);

		return $template;
	}//end makeTemplate()

	/**
	 * Build a stock EMailTemplate with the same constructor arguments, for
	 * byte-identical fallback comparison.
	 *
	 * @return EMailTemplate The stock template.
	 */
	private function makeStockTemplate(): EMailTemplate {
		return new EMailTemplate(
			$this->themingDefaults,
			$this->urlGenerator,
			$this->l10nFactory,
			32,
			32,
			'test',
			[]
		);
	}//end makeStockTemplate()

	/**
	 * (a) Header and body button markup use the active token set's color
	 * and the absolute logo URL.
	 *
	 * @return void
	 */
	public function testHeaderAndButtonUseActiveTokenSetTheme(): void {
		$service = $this->createMock(originalClassName: EmailThemingService::class);
		$service->method('getActiveEmailTheme')->willReturn(
			[
				'primaryColor' => '#154273',
				'primaryTextColor' => '#ffffff',
				'logoUrl' => 'https://cloud.example.com/apps/thematiq/img/logos/test.svg',
			]
		);
		$service->method('getFooterConfig')->willReturn(['orgName' => '', 'accessibilityUrl' => '', 'privacyUrl' => '']);

		$template = $this->makeTemplate(service: $service);
		$template->setSubject('Test');
		$template->addHeader();
		$template->addHeading('Heading');
		$template->addBodyText('Body text');
		$template->addBodyButton('Click', 'https://example.org/action');
		$template->addFooter();

		$html = $template->renderHtml();

		$this->assertStringContainsString(needle: '#154273', haystack: $html);
		$this->assertStringNotContainsString(needle: '#0082c9', haystack: $html);
		$this->assertStringContainsString(needle: 'https://cloud.example.com/apps/thematiq/img/logos/test.svg', haystack: $html);
	}//end testHeaderAndButtonUseActiveTokenSetTheme()

	/**
	 * A body button takes the active token set's colours on its own, without a header.
	 *
	 * @return void
	 */
	public function testBodyButtonUsesActiveTokenSetColours(): void {
		$service = $this->createMock(originalClassName: EmailThemingService::class);
		$service->method('getActiveEmailTheme')->willReturn(['primaryColor' => '#154273', 'primaryTextColor' => '#fefefe', 'logoUrl' => null]);
		$service->method('getFooterConfig')->willReturn(['orgName' => '', 'accessibilityUrl' => '', 'privacyUrl' => '']);

		$template = $this->makeTemplate(service: $service);
		$template->addBodyButton('Click', 'https://example.org/action');
		$html = $template->renderHtml();

		$this->assertStringContainsString(needle: '#154273', haystack: $html);
		$this->assertStringContainsString(needle: '#fefefe', haystack: $html);
		$this->assertStringNotContainsString(needle: '#0082c9', haystack: $html);
	}//end testBodyButtonUsesActiveTokenSetColours()

	/**
	 * (b) Configured footer org name and both URLs appear in the HTML part
	 * AND as plain lines in renderText().
	 *
	 * @return void
	 */
	public function testFooterConfigRendersInBothParts(): void {
		$service = $this->createMock(originalClassName: EmailThemingService::class);
		$service->method('getActiveEmailTheme')->willReturn(null);
		$service->method('getFooterConfig')->willReturn(
			[
				'orgName' => 'Gemeente Voorbeeld',
				'accessibilityUrl' => 'https://voorbeeld.nl/toegankelijkheid',
				'privacyUrl' => 'https://voorbeeld.nl/privacy',
			]
		);

		$template = $this->makeTemplate(service: $service);
		$template->addHeader();
		$template->addFooter();

		$html = $template->renderHtml();
		$text = $template->renderText();

		$this->assertStringContainsString(needle: 'Gemeente Voorbeeld', haystack: $html);
		$this->assertStringContainsString(needle: 'https://voorbeeld.nl/toegankelijkheid', haystack: $html);
		$this->assertStringContainsString(needle: 'https://voorbeeld.nl/privacy', haystack: $html);

		$this->assertStringContainsString(needle: 'Gemeente Voorbeeld', haystack: $text);
		$this->assertStringContainsString(needle: 'https://voorbeeld.nl/toegankelijkheid', haystack: $text);
		$this->assertStringContainsString(needle: 'https://voorbeeld.nl/privacy', haystack: $text);
	}//end testFooterConfigRendersInBothParts()

	/**
	 * (c) With the service resolver returning null, HTML and plain output
	 * are byte-identical to a stock EMailTemplate render — the fallback proof.
	 *
	 * @return void
	 */
	public function testNullServiceFallsBackToStockRendering(): void {
		$branded = $this->makeTemplate(service: null);
		$stock = $this->makeStockTemplate();

		foreach ([$branded, $stock] as $tpl) {
			$tpl->setSubject('Test');
			$tpl->addHeader();
			$tpl->addHeading('Heading');
			$tpl->addBodyText('Body text');
			$tpl->addBodyButton('Click', 'https://example.org/action');
			$tpl->addFooter();
		}

		$this->assertSame(expected: $stock->renderHtml(), actual: $branded->renderHtml());
		$this->assertSame(expected: $stock->renderText(), actual: $branded->renderText());
	}//end testNullServiceFallsBackToStockRendering()

	/**
	 * (d) The plain-text part of a full email (header/heading/body/button/
	 * footer) still contains the button URL and the standard footer text —
	 * plain part stays intact, no HTML leakage.
	 *
	 * @return void
	 */
	public function testPlainTextPartStaysIntact(): void {
		$service = $this->createMock(originalClassName: EmailThemingService::class);
		$service->method('getActiveEmailTheme')->willReturn(
			[
				'primaryColor' => '#154273',
				'primaryTextColor' => '#ffffff',
				'logoUrl' => null,
			]
		);
		$service->method('getFooterConfig')->willReturn(['orgName' => '', 'accessibilityUrl' => '', 'privacyUrl' => '']);

		$template = $this->makeTemplate(service: $service);
		$template->addHeader();
		$template->addHeading('Heading');
		$template->addBodyText('Body text');
		$template->addBodyButton('Click here', 'https://example.org/action');
		$template->addFooter();

		$text = $template->renderText();

		$this->assertStringContainsString(needle: 'https://example.org/action', haystack: $text);
		$this->assertStringContainsString(needle: 'Nextcloud', haystack: $text);
		$this->assertStringNotContainsString(needle: '<', haystack: $text);
	}//end testPlainTextPartStaysIntact()

	/**
	 * (e) Footer values containing markup/quotes are HTML-escaped in the
	 * HTML part, never rendered as live markup.
	 *
	 * @return void
	 */
	public function testFooterValuesAreOutputEncoded(): void {
		$service = $this->createMock(originalClassName: EmailThemingService::class);
		$service->method('getActiveEmailTheme')->willReturn(null);
		$service->method('getFooterConfig')->willReturn(
			[
				'orgName' => '<script>alert(1)</script>',
				'accessibilityUrl' => 'https://voorbeeld.nl/a"onclick="x',
				'privacyUrl' => 'https://voorbeeld.nl/p"onmouseover="y',
			]
		);

		$template = $this->makeTemplate(service: $service);
		$template->addHeader();
		$template->addFooter();

		$html = $template->renderHtml();

		$this->assertStringNotContainsString(needle: '<script>', haystack: $html);
		$this->assertStringContainsString(needle: '&lt;script&gt;', haystack: $html);
		$this->assertStringNotContainsString(needle: '"onclick="x"', haystack: $html);
		$this->assertStringNotContainsString(needle: '"onmouseover="y"', haystack: $html);
	}//end testFooterValuesAreOutputEncoded()
}//end class
