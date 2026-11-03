<?php

/**
 * A branded mail's logo reaches the message as an inline image, on the server's own classes.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/email-theming/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Server\Mail;

use OC\Mail\EMailTemplate;
use OCA\Thematiq\Mail\NLDesignEMailTemplate;
use OCA\Thematiq\Service\EmailThemingService;
use OCP\Defaults;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\Mail\IMailer;
use PHPUnit\Framework\TestCase;

/**
 * Runs the branded header through the real OC\Mail\EMailTemplate and the real message the mailer
 * builds, so the test fails when the server changes how it inlines a logo.
 */
class BrandedLogoInlineTest extends TestCase {

	/**
	 * The set's own logo is attached inline and the HTML part refers to it.
	 *
	 * @return void
	 */
	public function testTheBrandedLogoIsAttachedToTheMessage(): void {
		$this->assertStringContainsString(
			needle: '/lib/private/Mail/EMailTemplate.php',
			haystack: (string)(new \ReflectionClass(objectOrClass: EMailTemplate::class))->getFileName()
		);

		$url = 'https://cloud.example.com/apps/thematiq/img/logos/custom-a.svg';
		$service = $this->createMock(originalClassName: EmailThemingService::class);
		$theme = ['primaryColor' => '#154273', 'primaryTextColor' => '#ffffff', 'logoUrl' => $url];
		$service->method('getActiveEmailTheme')->willReturn($theme);
		$service->method('getActiveEmailLogoImage')->willReturn(['content' => '<svg id="own"/>', 'mimeType' => 'image/svg+xml']);
		$service->method('getFooterConfig')->willReturn(['orgName' => '', 'accessibilityUrl' => '', 'privacyUrl' => '']);

		$container = \OC::$server;
		$template = new class($container->get(Defaults::class), $container->get(IURLGenerator::class), $container->get(IFactory::class), 32, 32, 'test', [], $service) extends NLDesignEMailTemplate {
			/**
			 * The template over a stubbed service.
			 *
			 * @param Defaults $defaults The theming defaults.
			 * @param IURLGenerator $urlGenerator The URL generator.
			 * @param IFactory $l10nFactory The l10n factory.
			 * @param int|null $logoWidth The logo width.
			 * @param int|null $logoHeight The logo height.
			 * @param string $emailId The mail id.
			 * @param array<string, mixed> $data The mail data.
			 * @param EmailThemingService $service The service.
			 */
			public function __construct(
				Defaults $defaults,
				IURLGenerator $urlGenerator,
				IFactory $l10nFactory,
				?int $logoWidth,
				?int $logoHeight,
				string $emailId,
				array $data,
				private EmailThemingService $service,
			) {
				parent::__construct(
					themingDefaults: $defaults,
					urlGenerator: $urlGenerator,
					l10nFactory: $l10nFactory,
					logoWidth: $logoWidth,
					logoHeight: $logoHeight,
					emailId: $emailId,
					data: $data
				);
			}//end __construct()

			/**
			 * The stubbed service.
			 *
			 * @return EmailThemingService
			 */
			protected function getEmailThemingService(): ?EmailThemingService {
				return $this->service;
			}//end getEmailThemingService()
		};
		$template->setSubject('Subject');
		$template->addHeader();
		$template->addBodyText('Body');
		$template->addFooter();

		$message = $container->get(IMailer::class)->createMessage();
		$message->useTemplate($template);
		$email = $message->getSymfonyEmail();

		$this->assertStringContainsString(needle: 'src="cid:logo"', haystack: (string)$email->getHtmlBody());
		$this->assertStringNotContainsString(needle: $url, haystack: (string)$email->getHtmlBody());
		$attachments = $email->getAttachments();
		$this->assertCount(expectedCount: 1, haystack: $attachments);
		$this->assertSame(expected: '<svg id="own"/>', actual: $attachments[0]->getBody());
		$this->assertSame(expected: 'logo', actual: $attachments[0]->getFilename());
	}//end testTheBrandedLogoIsAttachedToTheMessage()
}//end class
