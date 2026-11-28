<?php

/**
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/menu-labels/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Repair;

use OCA\Thematiq\Repair\MigrateMenuLabelsToTopBarTitles;
use OCA\Thematiq\Service\AppMenuTopBarTitles;
use OCP\IConfig;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * The older menu labels option moves once into Show titles in the top bar, over an in-memory app
 * config; a stored value wins; a failure is logged, never thrown.
 *
 * @spec openspec/specs/menu-labels/spec.md
 */
final class MigrateMenuLabelsToTopBarTitlesTest extends TestCase {

	/**
	 * The app values, by key.
	 *
	 * @var array<string, string>
	 */
	private array $app = [];

	/**
	 * The config over the in-memory values.
	 *
	 * @return IConfig
	 */
	private function config(): IConfig {
		$config = $this->createMock(originalClassName: IConfig::class);
		$config->method('getAppValue')->willReturnCallback(fn (string $app, string $key, $default = '') => ($this->app[$key] ?? $default));
		$config->method('setAppValue')->willReturnCallback(function (string $app, string $key, $value): void {
			$this->app[$key] = (string)$value;
		});
		$config->method('deleteAppValue')->willReturnCallback(function (string $app, string $key): void {
			unset($this->app[$key]);
		});

		return $config;
	}//end config()

	/**
	 * Run the step once.
	 *
	 * @param IConfig|null $config The config, or the in-memory one.
	 * @param LoggerInterface|null $logger The logger, or a mock.
	 *
	 * @return void
	 */
	private function runStep(?IConfig $config = null, ?LoggerInterface $logger = null): void {
		$step = new MigrateMenuLabelsToTopBarTitles(
			config: ($config ?? $this->config()),
			logger: ($logger ?? $this->createMock(originalClassName: LoggerInterface::class))
		);
		$step->run($this->createMock(originalClassName: IOutput::class));
	}//end runStep()

	/**
	 * '1' becomes true and '0' becomes false, and the older key goes.
	 *
	 * @return void
	 */
	public function testTheOlderValueMovesOnceAndGoes(): void {
		$this->app['show_menu_labels'] = '1';
		$this->runStep();
		$this->assertSame(expected: ['menu.topBarTitles' => 'true'], actual: $this->app);

		$this->app = ['show_menu_labels' => '0'];
		$this->runStep();
		$this->assertSame(expected: ['menu.topBarTitles' => 'false'], actual: $this->app);

		$this->runStep();
		$this->assertSame(expected: ['menu.topBarTitles' => 'false'], actual: $this->app);
	}//end testTheOlderValueMovesOnceAndGoes()

	/**
	 * A value the administrator stored wins; the older key still goes.
	 *
	 * @return void
	 */
	public function testAStoredValueWins(): void {
		$this->app = ['show_menu_labels' => '1', 'menu.topBarTitles' => 'false'];
		$this->runStep();
		$this->assertSame(expected: ['menu.topBarTitles' => 'false'], actual: $this->app);
	}//end testAStoredValueWins()

	/**
	 * Nothing stored: nothing written.
	 *
	 * @return void
	 */
	public function testNothingToMove(): void {
		$this->runStep();
		$this->assertSame(expected: [], actual: $this->app);
		$step = new MigrateMenuLabelsToTopBarTitles(config: $this->config(), logger: $this->createMock(originalClassName: LoggerInterface::class));
		$this->assertSame(expected: 'Move the menu labels option into Show titles in the top bar', actual: $step->getName());
	}//end testNothingToMove()

	/**
	 * A failing store is logged and does not stop the upgrade.
	 *
	 * @return void
	 */
	public function testAFailureIsLoggedNotThrown(): void {
		$config = $this->createMock(originalClassName: IConfig::class);
		$config->method('getAppValue')->willThrowException(new RuntimeException('down'));
		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		$logger->expects($this->once())->method('warning');

		$this->runStep(config: $config, logger: $logger);
	}//end testAFailureIsLoggedNotThrown()

	/**
	 * The reader: the stored key first, then the older one, and the workplace menu check.
	 *
	 * @spec openspec/specs/apps-menu-settings/spec.md#requirement-show-titles-in-the-top-bar-is-a-theming-choice-of-the-administrator-req-tqmt-005
	 *
	 * @return void
	 */
	public function testTheReader(): void {
		$titles = new AppMenuTopBarTitles(config: $this->config());
		$this->assertFalse(condition: $titles->isOn());
		$this->app['show_menu_labels'] = '1';
		$this->assertTrue(condition: $titles->isOn());
		$this->app['menu.topBarTitles'] = 'false';
		$this->assertFalse(condition: $titles->isOn());
		$this->app['menu.topBarTitles'] = 'nonsense';
		$this->assertNull(actual: $titles->stored());
		$this->assertTrue(condition: $titles->isOn());

		$this->assertTrue(condition: $titles->workplaceMenu(sheets: ['workplace-layout', 'header-workplace']));
		$this->assertFalse(condition: $titles->workplaceMenu(sheets: ['workplace-layout']));
		$this->app['menu.style'] = '"classic"';
		$this->assertFalse(condition: $titles->workplaceMenu(sheets: ['workplace-layout', 'header-workplace']));
	}//end testTheReader()
}//end class
