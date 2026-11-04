<?php

/**
 * OwnTokenService: the name rule, duplicates, the value check by type, and names that
 * try to reach the CSS writer.
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
 * @spec openspec/specs/own-tokens/spec.md#requirement-an-administrator-adds-an-own-token-from-the-token-editor
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use InvalidArgumentException;
use OCA\Thematiq\Service\DeprecationRecords;
use OCA\Thematiq\Service\OwnTokenService;
use OCA\Thematiq\Service\TokenValueValidator;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;

/**
 * Over an in-memory app config.
 *
 * @spec openspec/specs/own-tokens/spec.md#requirement-an-administrator-adds-an-own-token-from-the-token-editor
 */
final class OwnTokenServiceTest extends TestCase {

	/**
	 * The stored app values.
	 *
	 * @var array<string, string>
	 */
	private array $stored = [];

	/**
	 * The service under test.
	 *
	 * @var OwnTokenService
	 */
	private OwnTokenService $service;

	/**
	 * Set up.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->stored = [];
		$config = $this->createMock(originalClassName: IConfig::class);
		$config->method('getAppValue')->willReturnCallback(fn (string $app, string $key, $default = '') => ($this->stored[$key] ?? $default));
		$config->method('setAppValue')->willReturnCallback(function (string $app, string $key, $value): void {
			$this->stored[$key] = (string)$value;
		});
		$this->service = new OwnTokenService(config: $config, values: new TokenValueValidator(), records: new DeprecationRecords(config: $config));
	}//end setUp()

	/**
	 * Assert a create is refused with this field and status.
	 *
	 * @param array<string, mixed> $input The fields.
	 * @param string $field The field the refusal names.
	 * @param int $status The status.
	 *
	 * @return void
	 */
	private function assertRefused(array $input, string $field, int $status = 400): void {
		try {
			$this->service->create(input: $input);
			$this->fail(message: 'expected a refusal for ' . $field);
		} catch (InvalidArgumentException $e) {
			$this->assertSame(expected: $field, actual: $e->getMessage());
			$this->assertSame(expected: $status, actual: $e->getCode());
		}
	}//end assertRefused()

	/**
	 * Scenario: an administrator adds a brand accent colour; it is stored under the prefix.
	 *
	 * @return void
	 */
	public function testBrandAccentIsStored(): void {
		$token = $this->service->create(input: ['slug' => 'brand-accent', 'label' => 'Brand accent', 'type' => 'color', 'value' => '#e17000', 'darkValue' => '#ff9a3c']);

		$this->assertSame(expected: '--nldesign-org-brand-accent', actual: $token['name']);
		$this->assertSame(expected: '#ff9a3c', actual: $this->service->list()['--nldesign-org-brand-accent']['darkValue']);
	}//end testBrandAccentIsStored()

	/**
	 * Scenario: a name outside the rule is refused and nothing is stored.
	 *
	 * @return void
	 */
	public function testNameRule(): void {
		foreach (['Brand_Accent', 'brand--accent', '-brand', 'brand-', '', str_repeat('a', 49)] as $slug) {
			$this->assertRefused(input: ['slug' => $slug, 'label' => 'x', 'type' => 'text', 'value' => 'x'], field: 'name');
		}

		$this->assertSame(expected: [], actual: $this->service->list());
		$this->service->create(input: ['slug' => str_repeat('a', 48), 'label' => 'x', 'type' => 'text', 'value' => 'x']);
		$this->assertCount(expectedCount: 1, haystack: $this->service->list());
	}//end testNameRule()

	/**
	 * Scenario: a duplicate name is refused with 400.
	 *
	 * @return void
	 */
	public function testDuplicateRefused(): void {
		$input = ['slug' => 'brand-accent', 'label' => 'Brand accent', 'type' => 'color', 'value' => '#e17000'];
		$this->service->create(input: $input);

		$this->assertRefused(input: $input, field: 'duplicate');
	}//end testDuplicateRefused()

	/**
	 * The value is checked against its type, and only a colour has a dark value.
	 *
	 * @return void
	 */
	public function testValueCheckedByType(): void {
		$this->assertRefused(input: ['slug' => 'a', 'label' => 'x', 'type' => 'color', 'value' => 'dark blue'], field: 'value');
		$this->assertRefused(input: ['slug' => 'a', 'label' => 'x', 'type' => 'duration', 'value' => '150'], field: 'value');
		$this->assertRefused(input: ['slug' => 'a', 'label' => 'x', 'type' => 'easing', 'value' => 'cubic-bezier(2, 0, 0, 1)'], field: 'value');
		$this->assertRefused(input: ['slug' => 'a', 'label' => 'x', 'type' => 'color', 'value' => '#000', 'darkValue' => 'nope'], field: 'darkValue');
		$this->assertRefused(input: ['slug' => 'a', 'label' => 'x', 'type' => 'text', 'value' => 'x', 'darkValue' => '#000'], field: 'darkValue');
		$this->assertRefused(input: ['slug' => 'a', 'label' => 'x', 'type' => 'shadow', 'value' => 'x'], field: 'type');
		$this->assertRefused(input: ['slug' => 'a', 'label' => '', 'type' => 'text', 'value' => 'x'], field: 'label');

		$this->service->create(input: ['slug' => 'speed', 'label' => 'Speed', 'type' => 'duration', 'value' => '150ms']);
		$this->assertSame(expected: '150ms', actual: $this->service->list()['--nldesign-org-speed']['value']);
	}//end testValueCheckedByType()

	/**
	 * Task 6.7: a name or value that tries to break out of the block never reaches the store.
	 *
	 * @return void
	 */
	public function testNameCannotInjectCss(): void {
		$this->assertRefused(input: ['slug' => 'brand;}body{display:none', 'label' => 'x', 'type' => 'text', 'value' => 'x'], field: 'name');
		$this->assertRefused(input: ['slug' => 'brand', 'label' => 'x', 'type' => 'text', 'value' => 'red; } body { display: none'], field: 'value');
		$this->assertFalse(condition: OwnTokenService::isOwnName(name: '--nldesign-org-brand;}body{display:none'));
		$this->assertSame(expected: [], actual: $this->service->list());
	}//end testNameCannotInjectCss()

	/**
	 * Edit keeps the name and the creation time; remove deletes; both 404 on an unknown name.
	 *
	 * @return void
	 */
	public function testUpdateAndRemove(): void {
		$created = $this->service->create(input: ['slug' => 'accent', 'label' => 'Accent', 'type' => 'color', 'value' => '#e17000']);
		$updated = $this->service->update(name: '--nldesign-org-accent', input: ['label' => 'Accent 2', 'type' => 'color', 'value' => '#000000']);

		$this->assertSame(expected: $created['createdAt'], actual: $updated['createdAt']);
		$this->assertSame(expected: '#000000', actual: $this->service->list()['--nldesign-org-accent']['value']);
		$this->service->remove(name: '--nldesign-org-accent');
		$this->assertSame(expected: [], actual: $this->service->list());

		$this->expectExceptionCode(code: 404);
		$this->service->remove(name: '--nldesign-org-accent');
	}//end testUpdateAndRemove()

	/**
	 * A bundle's tokens are all checked before any is stored.
	 *
	 * @return void
	 */
	public function testReplaceAllChecksEveryToken(): void {
		$this->service->create(input: ['slug' => 'keep', 'label' => 'Keep', 'type' => 'text', 'value' => 'x']);
		try {
			$this->service->replaceAll(tokens: ['--nldesign-org-a' => ['label' => 'A', 'type' => 'text', 'value' => 'a'], '--color-primary' => ['label' => 'B', 'type' => 'text', 'value' => 'b']]);
			$this->fail(message: 'expected a refusal');
		} catch (InvalidArgumentException) {
			$this->assertArrayHasKey(key: '--nldesign-org-keep', array: $this->service->list());
		}
	}//end testReplaceAllChecksEveryToken()
}//end class
