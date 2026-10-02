<?php

/**
 * Unit tests for AssistantMarkService and AssistantMarkController.
 *
 * @category Test
 * @package  OCA\Thematiq\Tests\Unit\Service
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/specs/assistant-approved-mark/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Capabilities;
use OCA\Thematiq\Controller\AssistantMarkController;
use OCA\Thematiq\Service\AssistantMarkService;
use OCA\Thematiq\Service\EmailThemingService;
use OCA\Thematiq\Service\Exception\AssistantMarkException;
use OCA\Thematiq\Settings\Admin;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Tests for the approved mark.
 */
class AssistantMarkServiceTest extends TestCase {

	/**
	 * In-memory app config.
	 *
	 * @var array<string, string>
	 */
	private array $app = [];

	/**
	 * The email footer organisation name.
	 *
	 * @var string
	 */
	private string $footerName = 'Gemeente Voorbeeld';

	/**
	 * The service under test.
	 *
	 * @return AssistantMarkService The service.
	 */
	private function service(): AssistantMarkService {
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(fn (string $app, string $key, $default = '') => ($this->app[$key] ?? $default));
		$config->method('setAppValue')->willReturnCallback(
			function (string $app, string $key, $value): void {
				$this->app[$key] = (string)$value;
			}
		);
		$email = $this->createMock(EmailThemingService::class);
		$email->method('getFooterConfig')->willReturnCallback(
			fn (): array => ['orgName' => $this->footerName, 'accessibilityUrl' => '', 'privacyUrl' => '']
		);
		$capabilities = $this->createMock(Capabilities::class);
		$capabilities->method('getCapabilities')->willReturn(['nldesign' => ['logos' => ['default' => '/apps/thematiq/img/logos/voorbeeld.svg']]]);

		return new AssistantMarkService($config, $email, $capabilities);
	}//end service()

	/**
	 * A Dutch translator.
	 *
	 * @return IL10N The translator.
	 */
	private function dutch(): IL10N {
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(
			fn (string $text, array $params = []): string => vsprintf(
				['Approved by %s' => 'Goedgekeurd door %s', '%s logo' => 'Logo van %s'][$text] ?? $text,
				$params
			)
		);

		return $l10n;
	}//end dutch()

	/**
	 * Off by default: the user reads only that it is off.
	 *
	 * @return void
	 */
	public function testOffByDefault(): void {
		$this->assertSame(['enabled' => false], $this->service()->forUser(l10n: $this->dutch()));
		$this->assertFalse($this->service()->getSettings()['enabled']);
	}//end testOffByDefault()

	/**
	 * On with the defaults: the footer name and the house style logo, the label translated.
	 *
	 * @return void
	 */
	public function testOnWithDefaults(): void {
		$this->service()->save(enabled: true, organisation: '', logo: '');

		$mark = $this->service()->forUser(l10n: $this->dutch());
		$this->assertTrue($mark['enabled']);
		$this->assertSame('Goedgekeurd door Gemeente Voorbeeld', $mark['label']);
		$this->assertSame('Gemeente Voorbeeld', $mark['organisation']);
		$this->assertSame(['url' => '/apps/thematiq/img/logos/voorbeeld.svg', 'alt' => 'Logo van Gemeente Voorbeeld'], $mark['logo']);
	}//end testOnWithDefaults()

	/**
	 * On with an own name and logo.
	 *
	 * @return void
	 */
	public function testOnWithOwnNameAndLogo(): void {
		$settings = $this->service()->save(enabled: true, organisation: 'Provincie Utrecht', logo: 'https://example.org/utrecht.svg');

		$this->assertSame('Provincie Utrecht', $settings['organisation']);
		$this->assertSame('Gemeente Voorbeeld', $settings['organisationDefault']);
		$mark = $this->service()->forUser(l10n: $this->dutch());
		$this->assertSame('Goedgekeurd door Provincie Utrecht', $mark['label']);
		$this->assertSame('https://example.org/utrecht.svg', $mark['logo']['url']);
	}//end testOnWithOwnNameAndLogo()

	/**
	 * No name anywhere: turning on is refused and the mark stays off.
	 *
	 * @return void
	 */
	public function testEmptyNameKeepsTheMarkOff(): void {
		$this->footerName = '';
		try {
			$this->service()->save(enabled: true, organisation: '  ', logo: '');
			$this->fail('Refused without a name.');
		} catch (AssistantMarkException $e) {
			$this->assertSame(AssistantMarkService::ERROR_NAME, $e->getCode());
		}

		$this->assertSame(['enabled' => false], $this->service()->forUser(l10n: $this->dutch()));
		$this->assertArrayNotHasKey('assistant_mark_enabled', $this->app);
	}//end testEmptyNameKeepsTheMarkOff()

	/**
	 * A logo that is not https or local is refused.
	 *
	 * @return void
	 */
	public function testUnsafeLogoIsRefused(): void {
		foreach (['javascript:alert(1)', 'http://example.org/x.svg', '//evil.example/x.svg'] as $logo) {
			try {
				$this->service()->save(enabled: true, organisation: 'X', logo: $logo);
				$this->fail('Refused: ' . $logo);
			} catch (AssistantMarkException $e) {
				$this->assertSame(AssistantMarkService::ERROR_LOGO, $e->getCode());
			}
		}
	}//end testUnsafeLogoIsRefused()

	/**
	 * The read is for signed-in users only; the writes are admin settings.
	 *
	 * @return void
	 */
	public function testEndpointAttributes(): void {
		$show = new ReflectionMethod(AssistantMarkController::class, 'show');
		$this->assertCount(1, $show->getAttributes(NoAdminRequired::class));
		$this->assertCount(0, $show->getAttributes(PublicPage::class), 'No session, no answer.');
		foreach (['settings', 'save'] as $method) {
			$attributes = (new ReflectionMethod(AssistantMarkController::class, $method))->getAttributes(AuthorizedAdminSetting::class);
			$this->assertCount(1, $attributes);
			$this->assertSame(Admin::class, $attributes[0]->getArguments()[0]);
		}
	}//end testEndpointAttributes()

	/**
	 * The controller answers a Dutch label, and 422 with the reason on an empty name.
	 *
	 * @return void
	 */
	public function testControllerAnswers(): void {
		$controller = new AssistantMarkController('thematiq', $this->createMock(IRequest::class), $this->service(), $this->dutch());
		$this->assertSame(['enabled' => false], $controller->show()->getData());

		$saved = $controller->save(enabled: true);
		$this->assertSame(200, $saved->getStatus());
		$this->assertSame('Goedgekeurd door Gemeente Voorbeeld', $saved->getData()['preview']['label']);
		$this->assertSame('Goedgekeurd door Gemeente Voorbeeld', $controller->show()->getData()['label']);

		$this->footerName = '';
		$refused = (new AssistantMarkController('thematiq', $this->createMock(IRequest::class), $this->service(), $this->dutch()))
			->save(enabled: true, organisation: '');
		$this->assertSame(422, $refused->getStatus());
		$this->assertStringContainsString('organisation name', $refused->getData()['error']);
	}//end testControllerAnswers()
}//end class
