<?php

/**
 * Unit tests for ScheduledSwitchStore: a bundle's planned switches as they
 * are validated, merged with the stored running state, and checked for overlap.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/config-portability/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\ScheduledSwitchStore;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;

/**
 * Pins the import validation, the merge and the overlap check.
 *
 * @spec openspec/specs/config-portability/spec.md
 */
final class ScheduledSwitchStoreTest extends TestCase {

	/**
	 * The stored app values.
	 *
	 * @var array<string, string>
	 */
	private array $stored = [];

	/**
	 * The store under test.
	 *
	 * @var ScheduledSwitchStore
	 */
	private ScheduledSwitchStore $store;

	/**
	 * Build the store over an in-memory app config.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(fn (string $app, string $key, $default = '') => ($this->stored[$key] ?? $default));
		$config->method('setAppValue')->willReturnCallback(
			function (string $app, string $key, $value): void {
				$this->stored[$key] = (string)$value;
			}
		);
		$this->store = new ScheduledSwitchStore(config: $config);
	}//end setUp()

	/**
	 * A value that is not a list is refused as a whole.
	 *
	 * @return void
	 */
	public function testAValueThatIsNotAListIsRefused(): void {
		$expected = ['entries' => [], 'errors' => ['"config.scheduledSwitches" must be a list.']];

		$this->assertSame($expected, $this->store->validateImport('nope', fn (): bool => true));
		$this->assertSame($expected, $this->store->validateImport(['a' => []], fn (): bool => true));
	}//end testAValueThatIsNotAListIsRefused()

	/**
	 * Each wrong entry is named with its position and reason; the right ones are normalised to UTC.
	 *
	 * @return void
	 */
	public function testEachEntryIsValidatedAndNormalised(): void {
		$asked = [];
		$setExists = function (string $id) use (&$asked): bool {
			$asked[] = $id;
			return $id !== 'gone';
		};
		$raw = [
			['id' => 'a1', 'tokenSet' => 'oranje', 'startAt' => '2027-04-26T18:00:00+02:00', 'endAt' => '2027-04-28T08:00:00+02:00', 'createdBy' => 'admin'],
			'not an entry',
			['id' => 7, 'tokenSet' => 'oranje'],
			['id' => 'b1', 'tokenSet' => 'oranje', 'startAt' => 'soon'],
			['id' => 'b2', 'tokenSet' => 'oranje', 'startAt' => '2027-05-01T00:00:00Z', 'endAt' => 'later'],
			['id' => 'b3', 'tokenSet' => 'oranje', 'startAt' => '2027-05-02T00:00:00Z', 'endAt' => '2027-05-01T00:00:00Z'],
			['id' => 'b4', 'tokenSet' => 'gone', 'startAt' => '2027-05-01T00:00:00Z'],
			['id' => 'b5', 'tokenSet' => 'oranje', 'startAt' => '2027-04-27T00:00:00Z', 'endAt' => '2027-04-27T12:00:00Z'],
			['id' => 'c1', 'tokenSet' => 'blauw', 'startAt' => '2027-06-01T00:00:00Z', 'endAt' => null, 'createdAt' => '2027-01-01T00:00:00Z'],
		];

		$result = $this->store->validateImport($raw, $setExists);

		$this->assertSame(
			[
				[
					'id' => 'a1',
					'tokenSet' => 'oranje',
					'startAt' => '2027-04-26T16:00:00Z',
					'endAt' => '2027-04-28T06:00:00Z',
					'createdBy' => 'admin',
					'createdAt' => '',
					'status' => 'planned',
				],
				[
					'id' => 'c1',
					'tokenSet' => 'blauw',
					'startAt' => '2027-06-01T00:00:00Z',
					'endAt' => null,
					'createdBy' => '',
					'createdAt' => '2027-01-01T00:00:00Z',
					'status' => 'planned',
				],
			],
			$result['entries']
		);
		$this->assertSame(
			[
				'Planned switch 1: needs a string "id" and "tokenSet".',
				'Planned switch 2: needs a string "id" and "tokenSet".',
				'Planned switch 3: a time does not parse.',
				'Planned switch 4: a time does not parse.',
				'Planned switch 5: the end is not after the start.',
				'Planned switch 6: token set "gone" is neither installed nor in this bundle.',
				'Planned switch 7 overlaps planned switch a1.',
			],
			$result['errors']
		);
		$this->assertSame(['oranje', 'gone', 'oranje', 'blauw'], $asked);
	}//end testEachEntryIsValidatedAndNormalised()

	/**
	 * A stored running entry keeps its runtime fields; an ended one is dropped; the rest is kept as imported.
	 *
	 * @return void
	 */
	public function testAnImportKeepsTheRunningStateAndDropsEndedWindows(): void {
		$this->store->save(
			[
				['id' => 'run', 'status' => 'running', 'revertTo' => 'stock', 'coreSnapshot' => true, 'tokenSet' => 'old'],
				['id' => 'plan', 'status' => 'planned', 'revertTo' => 'ignored'],
				['id' => 7, 'status' => 'running'],
			]
		);
		$now = (int)strtotime('2027-05-01T00:00:00Z');
		$imported = [
			['id' => 'run', 'tokenSet' => 'new', 'startAt' => '2027-04-01T00:00:00Z', 'endAt' => '2027-04-02T00:00:00Z', 'status' => 'planned'],
			['id' => 'plan', 'tokenSet' => 'x', 'startAt' => '2027-06-01T00:00:00Z', 'endAt' => null, 'status' => 'planned'],
			['id' => 'old', 'tokenSet' => 'y', 'startAt' => '2027-04-01T00:00:00Z', 'endAt' => '2027-05-01T00:00:00Z', 'status' => 'planned'],
			['id' => 'soon', 'tokenSet' => 'z', 'startAt' => '2027-04-01T00:00:00Z', 'endAt' => '2027-05-01T00:00:01Z', 'status' => 'planned'],
		];

		$this->assertSame(
			[
				['id' => 'run', 'tokenSet' => 'new', 'startAt' => '2027-04-01T00:00:00Z', 'endAt' => '2027-04-02T00:00:00Z', 'status' => 'running', 'revertTo' => 'stock', 'coreSnapshot' => true],
				$imported[1],
				$imported[3],
			],
			$this->store->mergeImported($imported, $now)
		);
	}//end testAnImportKeepsTheRunningStateAndDropsEndedWindows()

	/**
	 * Failed entries and the candidate itself never overlap; an open end runs forever.
	 *
	 * @return void
	 */
	public function testOverlapIgnoresFailedEntriesAndTheCandidate(): void {
		$candidate = ['id' => 'c', 'startAt' => '2027-05-02T00:00:00Z', 'endAt' => '2027-05-03T00:00:00Z'];
		$failed = ['id' => 'f', 'status' => 'failed', 'startAt' => '2027-05-01T00:00:00Z', 'endAt' => null];
		$self = ['id' => 'c', 'startAt' => '2027-05-01T00:00:00Z', 'endAt' => null];
		$before = ['id' => 'b', 'startAt' => '2027-04-01T00:00:00Z', 'endAt' => '2027-05-02T00:00:00Z'];
		$open = ['id' => 'o', 'startAt' => '2027-04-01T00:00:00Z', 'endAt' => null];

		$this->assertNull($this->store->findOverlap($candidate, [$failed, $self, $before]));
		$this->assertSame($open, $this->store->findOverlap($candidate, [$failed, $before, $open, $self]));
	}//end testOverlapIgnoresFailedEntriesAndTheCandidate()
}//end class
