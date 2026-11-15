<?php

/**
 * Thematiq Switch Import Validation.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Service
 * @package   OCA\Thematiq
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 *
 * @spec openspec/specs/config-portability/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * Validates a bundle's planned switches against the rules every stored
 * entry obeys: an id and a token set, times that parse, an end after the
 * start, a set that exists, and no overlap with an entry accepted before
 * it. Built by ScheduledSwitchStore.
 *
 * @spec openspec/specs/config-portability/spec.md
 */
final class SwitchImportValidation {

	/**
	 * Constructor.
	 *
	 * @param ScheduledSwitchStore $store The store, for the UTC times and the overlap check.
	 */
	public function __construct(
		private readonly ScheduledSwitchStore $store,
	) {
	}//end __construct()

	/**
	 * Validate a bundle's planned switches.
	 *
	 * @param mixed $raw The `config.scheduledSwitches` value.
	 * @param callable $setExists Answers whether a token set id exists (installed or bundled).
	 *
	 * @return array{entries: array<int, array<string, mixed>>, errors: array<int, string>}
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 */
	public function validate(mixed $raw, callable $setExists): array {
		if (is_array($raw) === false || array_is_list($raw) === false) {
			return ['entries' => [], 'errors' => ['"config.scheduledSwitches" must be a list.']];
		}

		$run = new SwitchImportRun(setExists: $setExists(...));
		foreach ($raw as $index => $item) {
			$run->index = $index;
			$this->acceptImportEntry(run: $run, item: $item);
		}

		return ['entries' => $run->entries, 'errors' => $run->errors];
	}//end validate()

	/**
	 * Accept one bundle entry unless it is wrong or overlaps an accepted one.
	 *
	 * @param SwitchImportRun $run The validation; gets the entry or the error.
	 * @param mixed $item The entry.
	 *
	 * @return void
	 */
	private function acceptImportEntry(SwitchImportRun $run, mixed $item): void {
		$entry = $this->validateImportEntry(run: $run, item: $item);
		if ($entry === null) {
			return;
		}

		$overlap = $this->store->findOverlap(candidate: $entry, entries: $run->entries);
		if ($overlap !== null) {
			$run->errors[] = 'Planned switch ' . $run->index . ' overlaps planned switch ' . (string)$overlap['id'] . '.';
			return;
		}

		$run->entries[] = $entry;
	}//end acceptImportEntry()

	/**
	 * Validate one bundle entry.
	 *
	 * @param SwitchImportRun $run The validation; gets the error.
	 * @param mixed $item The entry.
	 *
	 * @return array<string, mixed>|null The normalised entry, or null when it failed.
	 */
	private function validateImportEntry(SwitchImportRun $run, mixed $item): ?array {
		$prefix = 'Planned switch ' . $run->index . ': ';
		if ($this->hasImportIdentity(item: $item) === false) {
			$run->errors[] = $prefix . 'needs a string "id" and "tokenSet".';
			return null;
		}

		$entry = $this->importedEntry(item: $item);
		$message = ($this->unparsedTime(item: $item, entry: $entry) ?? $this->endBeforeStart(entry: $entry) ?? $this->unknownSet(run: $run, item: $item));
		if ($message !== null) {
			$run->errors[] = $prefix . $message;
			return null;
		}

		return $entry;
	}//end validateImportEntry()

	/**
	 * Whether a bundle entry is a map with a string id and token set.
	 *
	 * @param mixed $item The entry.
	 *
	 * @return bool True when it has both.
	 */
	private function hasImportIdentity(mixed $item): bool {
		return is_array($item) === true && is_string($item['id'] ?? null) === true && is_string($item['tokenSet'] ?? null) === true;
	}//end hasImportIdentity()

	/**
	 * A bundle entry normalised to UTC, as it is stored; a time that does not parse is null.
	 *
	 * @param array<string, mixed> $item The entry, its id and token set strings.
	 *
	 * @return array<string, mixed> The entry.
	 */
	private function importedEntry(array $item): array {
		$end = null;
		if (($item['endAt'] ?? null) !== null) {
			$end = $this->store->toUtc(value: $item['endAt']);
		}

		return [
			'id' => $item['id'],
			'tokenSet' => $item['tokenSet'],
			'startAt' => $this->store->toUtc(value: ($item['startAt'] ?? null)),
			'endAt' => $end,
			'createdBy' => (string)($item['createdBy'] ?? ''),
			'createdAt' => (string)($item['createdAt'] ?? ''),
			'status' => 'planned',
		];
	}//end importedEntry()

	/**
	 * The problem when the start, or an end the entry gives, does not parse.
	 *
	 * @param array<string, mixed> $item The entry as the bundle carries it.
	 * @param array<string, mixed> $entry The entry in UTC.
	 *
	 * @return string|null The problem, or null.
	 */
	private function unparsedTime(array $item, array $entry): ?string {
		if ($entry['startAt'] === null || (($item['endAt'] ?? null) !== null && $entry['endAt'] === null)) {
			return 'a time does not parse.';
		}

		return null;
	}//end unparsedTime()

	/**
	 * The problem when the end is not after the start.
	 *
	 * @param array<string, mixed> $entry The entry in UTC, its start parsed.
	 *
	 * @return string|null The problem, or null.
	 */
	private function endBeforeStart(array $entry): ?string {
		if ($entry['endAt'] !== null && strtotime($entry['endAt']) <= strtotime($entry['startAt'])) {
			return 'the end is not after the start.';
		}

		return null;
	}//end endBeforeStart()

	/**
	 * The problem when the entry's token set is neither installed nor bundled.
	 *
	 * @param SwitchImportRun $run The validation, with the set check.
	 * @param array<string, mixed> $item The entry.
	 *
	 * @return string|null The problem, or null.
	 */
	private function unknownSet(SwitchImportRun $run, array $item): ?string {
		if (($run->setExists)($item['tokenSet']) !== true) {
			return 'token set "' . $item['tokenSet'] . '" is neither installed nor in this bundle.';
		}

		return null;
	}//end unknownSet()
}//end class
