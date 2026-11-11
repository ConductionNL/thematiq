<?php

/**
 * Unit tests for EmailThemingSettings.
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
 * @spec openspec/specs/email-theming/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\EmailThemingService;
use OCA\Thematiq\Service\EmailThemingSettings;
use OCA\Thematiq\Service\Exception\ConfigReadOnlyException;
use OCA\Thematiq\Service\Exception\FooterValidationException;
use OCA\Thematiq\Service\Exception\ForeignMailTemplateClassException;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * Golden of every answer the email settings save gives, and the calls it makes.
 */
class EmailThemingSettingsTest extends TestCase {

	/**
	 * Each outcome, with the ordered calls to the email theming service.
	 *
	 * @return void
	 */
	public function testEverySaveGolden(): void {
		$footer = ['orgName' => 'Org', 'accessibilityUrl' => '', 'privacyUrl' => ''];
		$cases = [
			'enabled' => [true, null, null, [200, ['status' => 'ok', 'state' => ['state' => 'enabled'], 'footer' => ['orgName' => 'F']]], ['setFooterConfig', 'enable', 'getState', 'getFooterConfig']],
			'disabled' => [false, null, null, [200, ['status' => 'ok', 'state' => ['state' => 'enabled'], 'footer' => ['orgName' => 'F']]], ['setFooterConfig', 'disable', 'getState', 'getFooterConfig']],
			'invalid footer' => [true, new FooterValidationException(field: 'privacyUrl', message: 'Bad URL.'), null, [422, ['error' => 'invalid_footer', 'field' => 'privacyUrl', 'message' => 'Bad URL.']], ['setFooterConfig']],
			'read-only' => [true, null, new ConfigReadOnlyException('occ on', 'occ off'), [409, ['error' => 'config_read_only', 'occEnable' => 'occ on', 'occDisable' => 'occ off', 'footer' => ['orgName' => 'F']]], ['setFooterConfig', 'enable', 'getFooterConfig']],
			'foreign' => [false, null, new ForeignMailTemplateClassException('OCA\\X\\Mail'), [409, ['error' => 'foreign_mail_template_class', 'class' => 'OCA\\X\\Mail', 'footer' => ['orgName' => 'F']]], ['setFooterConfig', 'disable', 'getFooterConfig']],
		];
		foreach ($cases as $name => [$enabled, $footerError, $toggleError, $answer, $expectedCalls]) {
			$calls = [];
			$service = $this->createMock(originalClassName: EmailThemingService::class);
			$service->method('setFooterConfig')->willReturnCallback(
				static function () use (&$calls, $footerError): array {
					$calls[] = 'setFooterConfig';
					if ($footerError instanceof Throwable) {
						throw $footerError;
					}

					return [];
				}
			);
			foreach (['enable', 'disable'] as $method) {
				$service->method($method)->willReturnCallback(
					static function () use (&$calls, $method, $toggleError): void {
						$calls[] = $method;
						if ($toggleError instanceof Throwable) {
							throw $toggleError;
						}
					}
				);
			}

			$service->method('getState')->willReturnCallback(
				static function () use (&$calls): array {
					$calls[] = 'getState';

					return ['state' => 'enabled'];
				}
			);
			$service->method('getFooterConfig')->willReturnCallback(
				static function () use (&$calls): array {
					$calls[] = 'getFooterConfig';

					return ['orgName' => 'F'];
				}
			);
			$result = (new EmailThemingSettings(emailThemingService: $service))->save(enabled: $enabled, footer: $footer);
			$this->assertSame(expected: [$answer, $expectedCalls], actual: [[$result['status'], $result['body']], $calls], message: $name);
		}
	}//end testEverySaveGolden()
}//end class
