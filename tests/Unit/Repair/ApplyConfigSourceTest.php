<?php

/**
 * Tests for ApplyConfigSource.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Tests
 * @package   OCA\Thematiq
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 *
 * @spec openspec/specs/theme-as-code/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Repair;

use OCA\Thematiq\Repair\ApplyConfigSource;
use OCA\Thematiq\Service\ConfigSourceService;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * The repair step applies the package after an upgrade and never throws.
 *
 * @spec openspec/specs/theme-as-code/spec.md
 */
final class ApplyConfigSourceTest extends TestCase {

	/**
	 * The step names what it does.
	 *
	 * @return void
	 */
	public function testNamesTheStep(): void {
		$step = new ApplyConfigSource(
			source: $this->createMock(originalClassName: ConfigSourceService::class),
			logger: $this->createMock(originalClassName: LoggerInterface::class)
		);

		$this->assertSame(expected: 'Apply the thematiq branding package from config.php', actual: $step->getName());
	}//end testNamesTheStep()

	/**
	 * An applied or unchanged package is reported as info with its status.
	 *
	 * @return void
	 */
	public function testReportsTheStatusAsInfo(): void {
		$source = $this->createMock(originalClassName: ConfigSourceService::class);
		$source->expects($this->once())->method('applyIfChanged')->willReturn(['status' => 'applied']);
		$output = $this->createMock(originalClassName: IOutput::class);
		$output->expects($this->once())->method('info')->with('thematiq branding package: applied');
		$output->expects($this->never())->method('warning');

		(new ApplyConfigSource(source: $source, logger: $this->createMock(originalClassName: LoggerInterface::class)))->run(output: $output);
	}//end testReportsTheStatusAsInfo()

	/**
	 * A package that failed validation is a warning pointing at the settings.
	 *
	 * @return void
	 */
	public function testWarnsWhenThePackageFailed(): void {
		$source = $this->createMock(originalClassName: ConfigSourceService::class);
		$source->method('applyIfChanged')->willReturn(['status' => 'failed']);
		$output = $this->createMock(originalClassName: IOutput::class);
		$output->expects($this->once())->method('warning')->with(
			'The thematiq branding package was not applied; see Settings > Administration > Theming.'
		);
		$output->expects($this->never())->method('info');

		(new ApplyConfigSource(source: $source, logger: $this->createMock(originalClassName: LoggerInterface::class)))->run(output: $output);
	}//end testWarnsWhenThePackageFailed()

	/**
	 * A throw is logged and warned about, and the upgrade goes on.
	 *
	 * @return void
	 */
	public function testLogsAndWarnsOnAThrowWithoutRethrowing(): void {
		$failure = new RuntimeException('disk full');
		$source = $this->createMock(originalClassName: ConfigSourceService::class);
		$source->method('applyIfChanged')->willThrowException($failure);
		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		$logger->expects($this->once())->method('error')->with(
			'thematiq: applying the branding package failed: disk full',
			['exception' => $failure]
		);
		$output = $this->createMock(originalClassName: IOutput::class);
		$output->expects($this->once())->method('warning')->with('The thematiq branding package could not be applied: disk full');
		$output->expects($this->never())->method('info');

		(new ApplyConfigSource(source: $source, logger: $logger))->run(output: $output);
	}//end testLogsAndWarnsOnAThrowWithoutRethrowing()
}//end class
