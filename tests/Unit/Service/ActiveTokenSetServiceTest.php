<?php

/**
 * Unit tests for ActiveTokenSetService, the one token set write path the
 * dropdown and the scheduled switch job share.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/apply-scheduled-theme-switch/tasks.md#task-1.1
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\ActiveTokenSetService;
use OCA\Thematiq\Service\ThemingAuditService;
use OCA\Thematiq\Service\TokenSetService;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;

/**
 * The write validates, stores and audits; an invalid set writes and audits nothing.
 */
class ActiveTokenSetServiceTest extends TestCase {

	/**
	 * In-memory app config.
	 *
	 * @var array<string, string>
	 */
	private array $store = ['token_set' => 'rijkshuisstijl'];

	/**
	 * Build the service with a config double over $store.
	 *
	 * @param bool $valid What isValidTokenSet() answers.
	 * @param ThemingAuditService $audit The audit double.
	 *
	 * @return ActiveTokenSetService
	 */
	private function make(bool $valid, ThemingAuditService $audit): ActiveTokenSetService {
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			fn (string $app, string $key, $default = '') => ($this->store[$key] ?? $default)
		);
		$config->method('setAppValue')->willReturnCallback(
			function (string $app, string $key, $value): void {
				$this->store[$key] = $value;
			}
		);

		$tokenSets = $this->createMock(TokenSetService::class);
		$tokenSets->method('isValidTokenSet')->willReturn($valid);

		return new ActiveTokenSetService($config, $tokenSets, $audit);
	}//end make()

	/**
	 * A valid switch stores the set, returns the previous one and audits it
	 * with the caller's action and context.
	 */
	public function testSwitchStoresAuditsAndReturnsThePreviousSet(): void {
		$audit = $this->createMock(ThemingAuditService::class);
		$audit->expects($this->once())->method('log')->with(
			'scheduled_switch_applied',
			['old' => 'rijkshuisstijl', 'new' => 'amsterdam', 'actor' => 'system']
		);

		$service = $this->make(valid: true, audit: $audit);
		$previous = $service->switchTo(tokenSet: 'amsterdam', auditAction: 'scheduled_switch_applied', auditContext: ['actor' => 'system']);

		$this->assertSame('rijkshuisstijl', $previous);
		$this->assertSame('amsterdam', $this->store['token_set']);
		$this->assertSame('amsterdam', $service->getActive());
	}//end testSwitchStoresAuditsAndReturnsThePreviousSet()

	/**
	 * An invalid set throws and writes nothing.
	 */
	public function testInvalidSetWritesNothing(): void {
		$audit = $this->createMock(ThemingAuditService::class);
		$audit->expects($this->never())->method('log');

		$service = $this->make(valid: false, audit: $audit);

		try {
			$service->switchTo(tokenSet: 'bestaat-niet');
			$this->fail('An invalid set was accepted.');
		} catch (\InvalidArgumentException $e) {
			$this->assertSame('rijkshuisstijl', $this->store['token_set']);
		}
	}//end testInvalidSetWritesNothing()
}//end class
