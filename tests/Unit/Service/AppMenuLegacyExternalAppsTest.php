<?php

/**
 * Tests for AppMenuLegacyExternalApps and the MigrateMenuExternalApps repair step.
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
 * @spec openspec/changes/apps-menu-create-pins-settings/specs/apps-menu-settings/spec.md#requirement-the-external-apps-of-the-admin-page-move-into-the-menu-settings
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Repair\MigrateMenuExternalApps;
use OCA\Thematiq\Service\AppMenuLegacyExternalApps;
use OCA\Thematiq\Service\AppMenuSettings;
use OCA\Thematiq\Service\ExternalApps;
use OCA\Thematiq\Service\OpenProjectModules;
use OCP\IConfig;
use OCP\IL10N;
use OCP\L10N\IFactory;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;

/**
 * Over an in-memory app config.
 *
 * @spec openspec/changes/apps-menu-create-pins-settings/specs/apps-menu-settings/spec.md#requirement-the-external-apps-of-the-admin-page-move-into-the-menu-settings
 */
final class AppMenuLegacyExternalAppsTest extends TestCase {

	/**
	 * The app values.
	 *
	 * @var array<string, string>
	 */
	private array $app = [];

	/**
	 * The move over $app.
	 *
	 * @return AppMenuLegacyExternalApps
	 */
	private function legacy(): AppMenuLegacyExternalApps {
		$config = $this->createMock(originalClassName: IConfig::class);
		$config->method('getAppValue')->willReturnCallback(fn (string $a, string $key, $default = '') => ($this->app[$key] ?? $default));
		$config->method('setAppValue')->willReturnCallback(function (string $a, string $key, $value): void {
			$this->app[$key] = (string)$value;
		});
		$config->method('deleteAppValue')->willReturnCallback(function (string $a, string $key): void {
			unset($this->app[$key]);
		});

		return new AppMenuLegacyExternalApps(
			config: $config,
			externalApps: new ExternalApps(config: $config, openProject: new OpenProjectModules(config: $config)),
			settings: new AppMenuSettings(config: $config)
		);
	}//end legacy()

	/**
	 * A language that returns the English text.
	 *
	 * @return IL10N
	 */
	private function english(): IL10N {
		$l10n = $this->createMock(originalClassName: IL10N::class);
		$l10n->method('t')->willReturnArgument(0);

		return $l10n;
	}//end english()

	/**
	 * Scenario: the demo's OpenProject modules and XWiki move with their title, payoff, address,
	 * icon and menu key, after an app already in the settings; the old values go; it runs once.
	 *
	 * @return void
	 */
	public function testTheAdminPageAppsMoveOnceAndKeepTheirKeys(): void {
		$this->app = [
			'openproject_url' => 'http://localhost:8077',
			'external_apps' => '{"wiki":"http://localhost:8078"}',
			'menu.externalApps' => '[{"id":"intra","title":"Intranet","payoff":"","url":"https://intra.example.org","icon":{"library":"news"},"visible":true,"order":0}]',
		];

		$this->assertSame(expected: 5, actual: $this->legacy()->migrateOnce(l: $this->english()));
		$apps = json_decode($this->app['menu.externalApps'], true);
		$this->assertSame(
			expected: ['intra', 'openproject-work_packages', 'openproject-gantt', 'openproject-boards', 'openproject-meetings', 'wiki'],
			actual: array_column($apps, 'id')
		);
		$this->assertSame(
			expected: ['id' => 'wiki', 'title' => 'XWiki', 'payoff' => 'Knowledge base', 'url' => 'http://localhost:8078', 'icon' => ['library' => 'app-wiki'], 'visible' => true, 'order' => 5],
			actual: $apps[5]
		);
		$this->assertSame(expected: ['library' => 'openproject-work-packages'], actual: $apps[1]['icon']);
		$this->assertSame(expected: ['menu.externalApps', AppMenuLegacyExternalApps::MARKER], actual: array_keys($this->app));

		$this->app['external_apps'] = '{"code":"https://code.example.org"}';
		$this->assertSame(expected: 0, actual: $this->legacy()->migrateOnce(l: $this->english()));
	}//end testTheAdminPageAppsMoveOnceAndKeepTheirKeys()

	/**
	 * Without any admin page app the marker is still set, and the repair step reports the count.
	 *
	 * @return void
	 */
	public function testTheRepairStepRunsTheMove(): void {
		$factory = $this->createMock(originalClassName: IFactory::class);
		$factory->method('get')->willReturn($this->english());
		$output = $this->createMock(originalClassName: IOutput::class);
		$output->expects($this->once())->method('info')->with('MigrateMenuExternalApps: 0 external app(s) moved into the menu settings.');
		$step = new MigrateMenuExternalApps(legacy: $this->legacy(), l10nFactory: $factory);

		$this->assertNotSame(expected: '', actual: $step->getName());
		$step->run(output: $output);
		$this->assertSame(expected: [AppMenuLegacyExternalApps::MARKER => '1'], actual: $this->app);
	}//end testTheRepairStepRunsTheMove()
}//end class
