<?php

/**
 * Tests for AppMenuOrder.
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
 * @spec openspec/specs/apps-menu-order/spec.md#requirement-the-order-is-stored-for-the-person-on-the-server
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\AppMenuOrder;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;

/**
 * Over an in-memory user config.
 *
 * @spec openspec/specs/apps-menu-order/spec.md#requirement-the-order-is-stored-for-the-person-on-the-server
 */
final class AppMenuOrderTest extends TestCase {

	/**
	 * The stored values, by user and key.
	 *
	 * @var array<string, string>
	 */
	private array $stored = [];

	/**
	 * The service over the in-memory config.
	 *
	 * @return AppMenuOrder
	 */
	private function order(): AppMenuOrder {
		$config = $this->createMock(originalClassName: IConfig::class);
		$config->method('getUserValue')->willReturnCallback(
			fn (string $user, string $app, string $key, $default = '') => ($this->stored[$user . '/' . $app . '/' . $key] ?? $default)
		);
		$config->method('setUserValue')->willReturnCallback(function (string $user, string $app, string $key, $value): void {
			$this->stored[$user . '/' . $app . '/' . $key] = (string)$value;
		});
		$config->method('deleteUserValue')->willReturnCallback(function (string $user, string $app, string $key): void {
			unset($this->stored[$user . '/' . $app . '/' . $key]);
		});

		return new AppMenuOrder(config: $config);
	}//end order()

	/**
	 * Scenario: what may not be stored is dropped.
	 *
	 * @return void
	 */
	public function testWhatMayNotBeStoredIsDropped(): void {
		$this->assertSame(
			expected: ['files', 'external-mail'],
			actual: $this->order()->save(userId: 'anna', keys: ['files', 'Files', 'files', '<b>', 'external-mail', 3, ['x'], str_repeat('a', 81)])
		);
		$this->assertSame(expected: '["files","external-mail"]', actual: $this->stored['anna/thematiq/' . AppMenuOrder::KEY]);
		$this->assertSame(expected: ['files', 'external-mail'], actual: $this->order()->get(userId: 'anna'));
	}//end testWhatMayNotBeStoredIsDropped()

	/**
	 * At most MAX keys are kept.
	 *
	 * @return void
	 */
	public function testAtMostTwoHundredKeysAreKept(): void {
		$keys = array_map(static fn (int $i): string => 'app' . $i, range(1, 250));
		$this->assertCount(expectedCount: AppMenuOrder::MAX, haystack: $this->order()->save(userId: 'anna', keys: $keys));
	}//end testAtMostTwoHundredKeysAreKept()

	/**
	 * An empty list removes the value, and each user has their own.
	 *
	 * @return void
	 */
	public function testAnEmptyListRemovesTheValueOfThatUserOnly(): void {
		$this->order()->save(userId: 'anna', keys: ['files']);
		$this->order()->save(userId: 'bram', keys: ['deck']);
		$this->assertSame(expected: [], actual: $this->order()->save(userId: 'anna', keys: []));
		$this->assertArrayNotHasKey(key: 'anna/thematiq/' . AppMenuOrder::KEY, array: $this->stored);
		$this->assertSame(expected: [], actual: $this->order()->get(userId: 'anna'));
		$this->assertSame(expected: ['deck'], actual: $this->order()->get(userId: 'bram'));
	}//end testAnEmptyListRemovesTheValueOfThatUserOnly()

	/**
	 * A stored value that is not a list of keys reads as no order.
	 *
	 * @return void
	 */
	public function testABrokenValueReadsAsNoOrder(): void {
		$this->stored['anna/thematiq/' . AppMenuOrder::KEY] = '{not json';
		$this->assertSame(expected: [], actual: $this->order()->get(userId: 'anna'));
		$this->stored['anna/thematiq/' . AppMenuOrder::KEY] = '"files"';
		$this->assertSame(expected: [], actual: $this->order()->get(userId: 'anna'));
	}//end testABrokenValueReadsAsNoOrder()
}//end class
