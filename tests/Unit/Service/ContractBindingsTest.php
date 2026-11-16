<?php

/**
 * Unit tests for ContractBindings: every Kernel contract resolves to a service that implements it.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
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
 * @spec exclude Composition root wiring, no functional requirement.
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Kernel\Contracts\ComplianceReportServiceInterface;
use OCA\Thematiq\Kernel\Contracts\ConfigBundleServiceInterface;
use OCA\Thematiq\Kernel\Contracts\ContrastServiceInterface;
use OCA\Thematiq\Kernel\Contracts\DarkPaletteServiceInterface;
use OCA\Thematiq\Kernel\Contracts\ShippedTokenSetAuditServiceInterface;
use OCA\Thematiq\Kernel\Contracts\ThemingAuditServiceInterface;
use OCA\Thematiq\Kernel\Contracts\TokenReferenceServiceInterface;
use OCA\Thematiq\Kernel\Contracts\UpstreamFreshnessServiceInterface;
use OCA\Thematiq\Service\ComplianceReportService;
use OCA\Thematiq\Service\ConfigBundleService;
use OCA\Thematiq\Service\ContractBindings;
use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\DarkPaletteService;
use OCA\Thematiq\Service\ShippedTokenSetAuditService;
use OCA\Thematiq\Service\ThemingAuditService;
use OCA\Thematiq\Service\TokenReferenceService;
use OCA\Thematiq\Service\UpstreamFreshnessService;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Every contract in lib/Kernel/Contracts is aliased once, to the service implementing it.
 */
#[CoversClass(ContractBindings::class)]
final class ContractBindingsTest extends TestCase {

	/**
	 * The aliases ContractBindings registers, contract => implementation.
	 *
	 * @return array<string, string>
	 */
	private function registeredAliases(): array {
		$aliases = [];
		$context = $this->createMock(IRegistrationContext::class);
		$context->expects($this->atLeastOnce())
			->method('registerServiceAlias')
			->willReturnCallback(
				function (string $alias, string $target) use (&$aliases): void {
					$this->assertArrayNotHasKey(key: $alias, array: $aliases, message: "$alias is aliased twice");
					$aliases[$alias] = $target;
				}
			);

		(new ContractBindings())->register(context: $context);

		return $aliases;
	}//end registeredAliases()

	/**
	 * Each contract resolves to the service that the other components used before.
	 *
	 * @return void
	 */
	public function testEachContractResolvesToItsService(): void {
		$this->assertSame(
			expected: [
				ComplianceReportServiceInterface::class     => ComplianceReportService::class,
				ConfigBundleServiceInterface::class         => ConfigBundleService::class,
				ContrastServiceInterface::class             => ContrastService::class,
				DarkPaletteServiceInterface::class          => DarkPaletteService::class,
				ShippedTokenSetAuditServiceInterface::class => ShippedTokenSetAuditService::class,
				ThemingAuditServiceInterface::class         => ThemingAuditService::class,
				TokenReferenceServiceInterface::class       => TokenReferenceService::class,
				UpstreamFreshnessServiceInterface::class    => UpstreamFreshnessService::class,
			],
			actual: $this->registeredAliases()
		);
	}//end testEachContractResolvesToItsService()

	/**
	 * Every contract file is bound, and each binding's target implements its contract.
	 *
	 * @return void
	 */
	public function testEveryContractIsBoundToAnImplementation(): void {
		$aliases = $this->registeredAliases();

		$files = glob(__DIR__ . '/../../../lib/Kernel/Contracts/*Interface.php');
		$this->assertNotEmpty($files);
		foreach ($files as $file) {
			$contract = 'OCA\\Thematiq\\Kernel\\Contracts\\' . basename($file, '.php');
			$this->assertArrayHasKey(key: $contract, array: $aliases, message: "$contract has no binding");
			$this->assertTrue(
				condition: is_subclass_of($aliases[$contract], $contract),
				message: $aliases[$contract] . " does not implement $contract"
			);
		}
	}//end testEveryContractIsBoundToAnImplementation()
}//end class
