<?php

/**
 * Golden test for the email theming settings endpoints.
 *
 * @category Test
 * @package  OCA\Thematiq\Tests\Unit\Controller
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://github.com/ConductionNL/thematiq
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/email-theming/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Controller;

use OCA\Thematiq\Controller\SettingsController;
use OCA\Thematiq\Service\ActiveTokenSetService;
use OCA\Thematiq\Service\AppThemingService;
use OCA\Thematiq\Service\ComplianceReportService;
use OCA\Thematiq\Service\EmailThemingService;
use OCA\Thematiq\Service\EmailThemingSettings;
use OCA\Thematiq\Service\Exception\ConfigReadOnlyException;
use OCA\Thematiq\Service\Exception\FooterValidationException;
use OCA\Thematiq\Service\Exception\ForeignMailTemplateClassException;
use OCA\Thematiq\Service\GroupThemingService;
use OCA\Thematiq\Service\SettingsConfigServices;
use OCA\Thematiq\Service\SettingsReportServices;
use OCA\Thematiq\Service\SettingsTokenSetServices;
use OCA\Thematiq\Service\ThemingAuditService;
use OCA\Thematiq\Service\ThemingService;
use OCA\Thematiq\Service\TokenSetPreviewService;
use OCA\Thematiq\Service\TokenSetService;
use OCA\Thematiq\Service\UpstreamFreshnessService;
use OCP\IConfig;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Reads and saves the email theming settings with seeded inputs: footers the footer rules
 * accept or refuse, enabling and disabling, a read-only config.php and a foreign template
 * class on either toggle. Records the status, the body and every call to the email theming
 * service (only the arguments passed), case by case.
 * Set THEMATIQ_GOLDEN_RECORD=1 to rewrite it.
 *
 * @covers \OCA\Thematiq\Controller\SettingsController
 * @uses   \OCA\Thematiq\Service\SettingsConfigServices
 * @uses   \OCA\Thematiq\Service\SettingsReportServices
 * @uses   \OCA\Thematiq\Service\SettingsTokenSetServices
 * @covers \OCA\Thematiq\Service\EmailThemingSettings
 * @uses   \OCA\Thematiq\Service\Exception\ConfigReadOnlyException
 * @uses   \OCA\Thematiq\Service\Exception\FooterValidationException
 * @uses   \OCA\Thematiq\Service\Exception\ForeignMailTemplateClassException
 */
class EmailThemingSettingsGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/../fixtures/golden/email-theming-settings.golden.json';

	/**
	 * Every seeded read and save answers as recorded.
	 *
	 * @return void
	 */
	public function testEmailThemingMatchesTheGoldenRecording(): void {
		$actual = [];
		for ($seed = 1; $seed <= 200; $seed++) {
			$actual['seed:' . $seed] = $this->runCase(random: new Randomizer(new Mt19937($seed)));
		}

		if (getenv('THEMATIQ_GOLDEN_RECORD') === '1') {
			file_put_contents(self::FIXTURE, json_encode($actual, (JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . "\n");
		}

		$expected = json_decode((string)file_get_contents(self::FIXTURE), true);
		self::assertSame(expected: array_keys($expected), actual: array_keys($actual));
		foreach ($expected as $name => $json) {
			self::assertSame(expected: $json, actual: $actual[$name], message: $name);
		}
	}//end testEmailThemingMatchesTheGoldenRecording()

	/**
	 * One seeded read or save, as JSON text.
	 *
	 * @param Randomizer $random The case's randomizer.
	 *
	 * @return string
	 */
	private function runCase(Randomizer $random): string {
		$calls = [];
		$footerFault = $random->getInt(0, 3);
		$toggleFault = $random->getInt(0, 4);
		$footer = ['orgName' => 'Org ' . $random->getInt(1, 9), 'accessibilityUrl' => 'https://a.example', 'privacyUrl' => ''];

		$email = $this->createMock(originalClassName: EmailThemingService::class);
		$email->method('getState')->willReturnCallback(
			static function () use (&$calls, $random): array {
				$calls[] = ['getState'];
				return ['enabled' => true, 'source' => 'n' . $random->getInt(1, 9)];
			}
		);
		$email->method('getFooterConfig')->willReturnCallback(
			static function () use (&$calls, $footer): array {
				$calls[] = ['getFooterConfig'];
				return $footer;
			}
		);
		$email->method('setFooterConfig')->willReturnCallback(
			static function (string $orgName, string $accessibilityUrl, string $privacyUrl) use (&$calls, $footerFault): array {
				$calls[] = ['setFooterConfig', $orgName, $accessibilityUrl, $privacyUrl];
				if ($footerFault === 0) {
					throw new FooterValidationException(field: ['orgName', 'privacyUrl'][strlen($orgName) % 2], message: 'Footer refused: ' . $orgName);
				}

				return [];
			}
		);
		foreach (['enable', 'disable'] as $toggle) {
			$email->method($toggle)->willReturnCallback(
				static function () use (&$calls, $toggle, $toggleFault): void {
					$calls[] = [$toggle];
					if ($toggleFault === 0) {
						throw new ConfigReadOnlyException(occEnableCommand: 'occ on ' . $toggle, occDisableCommand: 'occ off ' . $toggle);
					}

					if ($toggleFault === 1) {
						throw new ForeignMailTemplateClassException(foreignClass: 'OCA\\Other\\Template\\' . ucfirst($toggle));
					}
				}
			);
		}

		$controller = $this->controller(email: $email);
		if ($random->getInt(0, 4) === 0) {
			$response = $controller->getEmailTheming();
		} else {
			$args = [
				'enabled' => ($random->getInt(0, 1) === 1),
				'orgName' => ['', 'Gemeente', 'Gemeente Zuid'][$random->getInt(0, 2)],
				'accessibilityUrl' => ['', 'https://a.example'][$random->getInt(0, 1)],
				'privacyUrl' => ['', 'https://p.example'][$random->getInt(0, 1)],
			];
			$calls[] = ['args', $args];
			$response = $controller->setEmailTheming(...$args);
		}

		return (string)json_encode(['status' => $response->getStatus(), 'data' => $response->getData(), 'calls' => $calls], JSON_UNESCAPED_SLASHES);
	}//end runCase()

	/**
	 * The controller under test; everything but the email theming service is a stand-in.
	 *
	 * @param EmailThemingService $email The email theming service.
	 *
	 * @return SettingsController
	 */
	private function controller(EmailThemingService $email): SettingsController {
		return new SettingsController(
			appName: 'thematiq',
			request: $this->createMock(originalClassName: IRequest::class),
			settings: new SettingsConfigServices(
				config: $this->createMock(originalClassName: IConfig::class),
				auditService: $this->createMock(originalClassName: ThemingAuditService::class),
				appThemingService: $this->createMock(originalClassName: AppThemingService::class),
				groupThemingService: $this->createMock(originalClassName: GroupThemingService::class)
			),
			tokenSets: new SettingsTokenSetServices(
				tokenSetService: $this->createMock(originalClassName: TokenSetService::class),
				previewService: $this->createMock(originalClassName: TokenSetPreviewService::class),
				activeTokenSet: $this->createMock(originalClassName: ActiveTokenSetService::class),
				themingService: $this->createMock(originalClassName: ThemingService::class)
			),
			reports: new SettingsReportServices(
				complianceService: $this->createMock(originalClassName: ComplianceReportService::class),
				emailTheming: new EmailThemingSettings(emailThemingService: $email),
				freshnessService: $this->createMock(originalClassName: UpstreamFreshnessService::class)
			)
		);
	}//end controller()
}//end class
