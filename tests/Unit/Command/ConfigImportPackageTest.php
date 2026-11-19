<?php

/**
 * Unit tests for the package path of the ConfigImport occ command.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/theme-as-code/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Command;

use OCA\Thematiq\Command\ConfigImport;
use OCA\Thematiq\Service\BrandingPackageService;
use OCA\Thematiq\Service\ConfigBundleService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * A package directory or ZIP goes to the package service, never to the bare bundle import.
 */
class ConfigImportPackageTest extends TestCase {

	/**
	 * A package is imported with the dry-run flag passed through and its sections printed.
	 *
	 * @return void
	 */
	public function testAPackageIsImportedThroughThePackageService(): void {
		$service = $this->createMock(originalClassName: ConfigBundleService::class);
		$service->expects($this->never())->method('import');
		$packages = $this->createMock(originalClassName: BrandingPackageService::class);
		$packages->method('isPackage')->with('/tmp/pkg')->willReturn(true);
		$packages->expects($this->once())->method('import')->with('/tmp/pkg', true)->willReturn(
			['valid' => true, 'dryRun' => true, 'sections' => ['fonts' => ['count' => 1]]]
		);

		$tester = new CommandTester(new ConfigImport(service: $service, packages: $packages));

		$this->assertSame(expected: Command::SUCCESS, actual: $tester->execute(['file' => '/tmp/pkg', '--dry-run' => true]));
		$this->assertSame(
			expected: "Dry run — the following sections would be applied:\n  - fonts: {\"count\":1}\n",
			actual: $tester->getDisplay()
		);
	}//end testAPackageIsImportedThroughThePackageService()

	/**
	 * An invalid package lists its errors, with fallbacks for a missing section or message.
	 *
	 * @return void
	 */
	public function testAnInvalidPackageListsItsErrors(): void {
		$packages = $this->createMock(originalClassName: BrandingPackageService::class);
		$packages->method('isPackage')->willReturn(true);
		$packages->method('import')->with('/tmp/pkg', false)->willReturn(['valid' => false, 'errors' => [[]]]);

		$tester = new CommandTester(
			new ConfigImport(service: $this->createMock(originalClassName: ConfigBundleService::class), packages: $packages)
		);

		$this->assertSame(expected: Command::FAILURE, actual: $tester->execute(['file' => '/tmp/pkg']));
		$this->assertSame(
			expected: "Configuration bundle validation failed — nothing was applied:\n  - [unknown] Unknown error.\n",
			actual: $tester->getDisplay()
		);
	}//end testAnInvalidPackageListsItsErrors()
}//end class
