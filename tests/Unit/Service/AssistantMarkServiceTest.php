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
		$config = $this->createMock(originalClassName: IConfig::class);
		$config->method('getAppValue')->willReturnCallback(fn (string $app, string $key, $default = '') => ($this->app[$key] ?? $default));
		$config->method('setAppValue')->willReturnCallback(
			function (string $app, string $key, $value): void {
				$this->app[$key] = (string)$value;
			}
		);
		$email = $this->createMock(originalClassName: EmailThemingService::class);
		$email->method('getFooterConfig')->willReturnCallback(
			fn (): array => ['orgName' => $this->footerName, 'accessibilityUrl' => '', 'privacyUrl' => '']
		);
		$capabilities = $this->createMock(originalClassName: Capabilities::class);
		$capabilities->method('getCapabilities')->willReturn(['nldesign' => ['logos' => ['default' => '/apps/thematiq/img/logos/voorbeeld.svg']]]);

		return new AssistantMarkService(config: $config, emailTheming: $email, capabilities: $capabilities);
	}//end service()

	/**
	 * A Dutch translator.
	 *
	 * @return IL10N The translator.
	 */
	private function dutch(): IL10N {
		$l10n = $this->createMock(originalClassName: IL10N::class);
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
		$this->assertSame(expected: ['enabled' => false], actual: $this->service()->forUser(l10n: $this->dutch()));
		$this->assertFalse(condition: $this->service()->getSettings()['enabled']);
	}//end testOffByDefault()

	/**
	 * On with the defaults: the footer name and the house style logo, the label translated.
	 *
	 * @return void
	 */
	public function testOnWithDefaults(): void {
		$this->service()->save(enabled: true, organisation: '', logo: '');

		$mark = $this->service()->forUser(l10n: $this->dutch());
		$this->assertTrue(condition: $mark['enabled']);
		$this->assertSame(expected: 'Goedgekeurd door Gemeente Voorbeeld', actual: $mark['label']);
		$this->assertSame(expected: 'Gemeente Voorbeeld', actual: $mark['organisation']);
		$this->assertSame(
			expected: ['url' => '/apps/thematiq/img/logos/voorbeeld.svg', 'alt' => 'Logo van Gemeente Voorbeeld'],
			actual: $mark['logo']
		);
	}//end testOnWithDefaults()

	/**
	 * On with an own name and logo.
	 *
	 * @return void
	 */
	public function testOnWithOwnNameAndLogo(): void {
		$settings = $this->service()->save(enabled: true, organisation: 'Provincie Utrecht', logo: 'https://example.org/utrecht.svg');

		$this->assertSame(expected: 'Provincie Utrecht', actual: $settings['organisation']);
		$this->assertSame(expected: 'Gemeente Voorbeeld', actual: $settings['organisationDefault']);
		$mark = $this->service()->forUser(l10n: $this->dutch());
		$this->assertSame(expected: 'Goedgekeurd door Provincie Utrecht', actual: $mark['label']);
		$this->assertSame(expected: 'https://example.org/utrecht.svg', actual: $mark['logo']['url']);
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
			$this->fail(message: 'Refused without a name.');
		} catch (AssistantMarkException $e) {
			$this->assertSame(expected: AssistantMarkService::ERROR_NAME, actual: $e->getCode());
		}

		$this->assertSame(expected: ['enabled' => false], actual: $this->service()->forUser(l10n: $this->dutch()));
		$this->assertArrayNotHasKey(key: 'assistant_mark_enabled', array: $this->app);
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
				$this->fail(message: 'Refused: ' . $logo);
			} catch (AssistantMarkException $e) {
				$this->assertSame(expected: AssistantMarkService::ERROR_LOGO, actual: $e->getCode());
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
		$this->assertCount(expectedCount: 1, haystack: $show->getAttributes(NoAdminRequired::class));
		$this->assertCount(expectedCount: 0, haystack: $show->getAttributes(PublicPage::class), message: 'No session, no answer.');
		foreach (['settings', 'save'] as $method) {
			$attributes = (new ReflectionMethod(AssistantMarkController::class, $method))->getAttributes(AuthorizedAdminSetting::class);
			$this->assertCount(expectedCount: 1, haystack: $attributes);
			$this->assertSame(expected: Admin::class, actual: $attributes[0]->getArguments()[0]);
		}
	}//end testEndpointAttributes()

	/**
	 * The controller answers a Dutch label, and 422 with the reason on an empty name.
	 *
	 * @return void
	 */
	public function testControllerAnswers(): void {
		$controller = new AssistantMarkController(
			appName: 'thematiq',
			request: $this->createMock(originalClassName: IRequest::class),
			mark: $this->service(),
			l10n: $this->dutch()
		);
		$this->assertSame(expected: ['enabled' => false], actual: $controller->show()->getData());

		$saved = $controller->save(enabled: true);
		$this->assertSame(expected: 200, actual: $saved->getStatus());
		$this->assertSame(expected: 'Goedgekeurd door Gemeente Voorbeeld', actual: $saved->getData()['preview']['label']);
		$this->assertSame(expected: 'Goedgekeurd door Gemeente Voorbeeld', actual: $controller->show()->getData()['label']);

		$this->footerName = '';
		$refused = (new AssistantMarkController(
			appName: 'thematiq',
			request: $this->createMock(originalClassName: IRequest::class),
			mark: $this->service(),
			l10n: $this->dutch()
		))
			->save(enabled: true, organisation: '');
		$this->assertSame(expected: 422, actual: $refused->getStatus());
		$this->assertStringContainsString(needle: 'organisation name', haystack: $refused->getData()['error']);
	}//end testControllerAnswers()

	/**
	 * The bundle section: absent, malformed in each field, an unusable logo, and a valid one trimmed.
	 *
	 * @return void
	 */
	public function testTheBundleSectionIsValidated(): void {
		$shape = ['errors' => ['"assistantMark" must be an object with enabled, organisation and logo.'], 'value' => null];
		$cases = [
			[null, ['errors' => [], 'value' => null]],
			['on', $shape],
			[['enabled' => 'yes'], $shape],
			[['organisation' => 7], $shape],
			[['logo' => ['x']], $shape],
			[['enabled' => true, 'logo' => 'javascript:alert(1)'], ['errors' => ['"assistantMark.logo" must be an https address or a path on this server.'], 'value' => null]],
			[['enabled' => null, 'organisation' => null, 'logo' => null], ['errors' => [], 'value' => ['enabled' => false, 'organisation' => '', 'logo' => '']]],
			[[], ['errors' => [], 'value' => ['enabled' => false, 'organisation' => '', 'logo' => '']]],
			[['enabled' => true, 'organisation' => ' Utrecht ', 'logo' => ' /apps/x.svg '], ['errors' => [], 'value' => ['enabled' => true, 'organisation' => 'Utrecht', 'logo' => '/apps/x.svg']]],
		];
		foreach ($cases as [$section, $expected]) {
			$this->assertSame(expected: $expected, actual: $this->service()->validateBundle(section: $section), message: json_encode($section));
		}
	}//end testTheBundleSectionIsValidated()

	/**
	 * Without a logo of its own or of the house style, the mark carries none.
	 *
	 * @return void
	 */
	public function testNoLogoAnywhereCarriesNone(): void {
		$this->service()->save(enabled: true, organisation: 'X', logo: '');
		$service = new AssistantMarkService(
			config: $this->createConfiguredMock(IConfig::class, []),
			emailTheming: $this->createMock(originalClassName: EmailThemingService::class),
			capabilities: $this->createMock(originalClassName: Capabilities::class)
		);

		$this->assertSame(expected: ['enabled' => false], actual: $service->forUser(l10n: $this->dutch()));
		$this->app['assistant_mark_enabled'] = '1';
	}//end testNoLogoAnywhereCarriesNone()
}//end class
