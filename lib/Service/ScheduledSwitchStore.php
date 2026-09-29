<?php

/**
 * Thematiq Scheduled Switch Store.
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
 * @spec openspec/changes/apply-scheduled-theme-switch/specs/scheduled-switch/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCA\Thematiq\AppInfo\Application;
use OCP\IConfig;

/**
 * The planned switches, stored as one JSON list in app config, plus the
 * rules every entry obeys wherever it comes from (the settings page or a
 * configuration bundle): times parse, the end is after the start, and no
 * two windows overlap.
 *
 * It depends on app config only, so ConfigBundleService can use it without
 * pulling in the audit trail (which itself keeps bundles of the
 * configuration, see ThemeVersionService).
 *
 * @spec openspec/changes/apply-scheduled-theme-switch/specs/scheduled-switch/spec.md
 * @spec openspec/changes/apply-scheduled-theme-switch/specs/config-portability/spec.md
 */
class ScheduledSwitchStore {

	/**
	 * The app config key holding the list.
	 *
	 * @var string
	 */
	public const CONFIG_KEY = 'scheduled_switches';

	/**
	 * The fields a planned switch carries in a bundle. Runtime state
	 * (`status`, `revertTo`, `failureReason`) stays on the server.
	 *
	 * @var array<int, string>
	 */
	public const PORTABLE_FIELDS = ['id', 'tokenSet', 'startAt', 'endAt', 'syncCoreTheming', 'createdBy', 'createdAt'];

	/**
	 * Constructor.
	 *
	 * @param IConfig $config The config service.
	 */
	public function __construct(
		private readonly IConfig $config,
	) {
	}//end __construct()

	/**
	 * Every stored entry, in stored order.
	 *
	 * @return array<int, array<string, mixed>> The entries.
	 *
	 * @spec openspec/changes/apply-scheduled-theme-switch/specs/scheduled-switch/spec.md
	 */
	public function all(): array {
		$decoded = json_decode($this->config->getAppValue(Application::APP_ID, self::CONFIG_KEY, '[]'), true);
		if (is_array($decoded) === false) {
			return [];
		}

		return array_values(array_filter($decoded, 'is_array'));
	}//end all()

	/**
	 * Replace the stored list.
	 *
	 * @param array<int, array<string, mixed>> $entries The entries.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/apply-scheduled-theme-switch/specs/scheduled-switch/spec.md
	 */
	public function save(array $entries): void {
		$this->config->setAppValue(Application::APP_ID, self::CONFIG_KEY, (string)json_encode(array_values($entries), JSON_UNESCAPED_SLASHES));
	}//end save()

	/**
	 * The entries as a bundle carries them: portable fields only.
	 *
	 * @return array<int, array<string, mixed>> The entries.
	 *
	 * @spec openspec/changes/apply-scheduled-theme-switch/specs/config-portability/spec.md
	 */
	public function exportable(): array {
		$result = [];
		foreach ($this->all() as $entry) {
			$portable = [];
			foreach (self::PORTABLE_FIELDS as $field) {
				$portable[$field] = ($entry[$field] ?? null);
			}

			$result[] = $portable;
		}

		return $result;
	}//end exportable()

	/**
	 * Normalise a time to UTC ISO 8601 (`2027-04-26T16:00:00Z`).
	 *
	 * @param mixed $value The time as entered, with or without an offset.
	 *
	 * @return string|null The UTC time, or null when it does not parse.
	 *
	 * @spec openspec/changes/apply-scheduled-theme-switch/specs/scheduled-switch/spec.md
	 */
	public static function toUtc(mixed $value): ?string {
		if (is_string($value) === false || preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/', $value) !== 1) {
			return null;
		}

		try {
			$time = new \DateTimeImmutable($value, new \DateTimeZone('UTC'));
		} catch (\Exception $e) {
			return null;
		}

		return $time->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
	}//end toUtc()

	/**
	 * The first entry whose window overlaps the candidate's, ignoring failed
	 * entries and the candidate itself. A window without an end runs forever.
	 *
	 * @param array<string, mixed>             $candidate The entry to check (UTC times).
	 * @param array<int, array<string, mixed>> $entries   The entries to check against.
	 *
	 * @return array<string, mixed>|null The overlapping entry, or null.
	 *
	 * @spec openspec/changes/apply-scheduled-theme-switch/specs/scheduled-switch/spec.md
	 */
	public static function findOverlap(array $candidate, array $entries): ?array {
		[$start, $end] = self::window(entry: $candidate);
		foreach ($entries as $entry) {
			if (($entry['status'] ?? 'planned') === 'failed' || ($entry['id'] ?? null) === ($candidate['id'] ?? null)) {
				continue;
			}

			[$otherStart, $otherEnd] = self::window(entry: $entry);
			if ($start < $otherEnd && $otherStart < $end) {
				return $entry;
			}
		}

		return null;
	}//end findOverlap()

	/**
	 * Validate a bundle's planned switches.
	 *
	 * @param mixed    $raw       The `config.scheduledSwitches` value.
	 * @param callable $setExists Answers whether a token set id exists (installed or bundled).
	 *
	 * @return array{entries: array<int, array<string, mixed>>, errors: array<int, string>}
	 *
	 * @spec openspec/changes/apply-scheduled-theme-switch/specs/config-portability/spec.md
	 */
	public function validateImport(mixed $raw, callable $setExists): array {
		if (is_array($raw) === false || array_is_list($raw) === false) {
			return ['entries' => [], 'errors' => ['"config.scheduledSwitches" must be a list.']];
		}

		$entries = [];
		$errors = [];
		foreach ($raw as $index => $item) {
			$entry = $this->validateImportEntry(item: $item, index: $index, setExists: $setExists, errors: $errors);
			if ($entry === null) {
				continue;
			}

			$overlap = self::findOverlap(candidate: $entry, entries: $entries);
			if ($overlap !== null) {
				$errors[] = 'Planned switch ' . $index . ' overlaps planned switch ' . (string)$overlap['id'] . '.';
				continue;
			}

			$entries[] = $entry;
		}

		return ['entries' => $entries, 'errors' => $errors];
	}//end validateImport()

	/**
	 * Validate one bundle entry.
	 *
	 * @param mixed             $item      The entry.
	 * @param int               $index     Its position, for the error message.
	 * @param callable          $setExists Answers whether a token set id exists.
	 * @param array<int,string> $errors    Accumulator.
	 *
	 * @return array<string, mixed>|null The normalised entry, or null when it failed.
	 */
	private function validateImportEntry(mixed $item, int $index, callable $setExists, array &$errors): ?array {
		$prefix = 'Planned switch ' . $index . ': ';
		if (is_array($item) === false || is_string($item['id'] ?? null) === false || is_string($item['tokenSet'] ?? null) === false) {
			$errors[] = $prefix . 'needs a string "id" and "tokenSet".';
			return null;
		}

		$start = self::toUtc(value: ($item['startAt'] ?? null));
		$end = null;
		if (($item['endAt'] ?? null) !== null) {
			$end = self::toUtc(value: $item['endAt']);
		}

		$message = null;
		if ($start === null || (($item['endAt'] ?? null) !== null && $end === null)) {
			$message = 'a time does not parse.';
		} else if ($end !== null && strtotime($end) <= strtotime($start)) {
			$message = 'the end is not after the start.';
		} else if ($setExists($item['tokenSet']) !== true) {
			$message = 'token set "' . $item['tokenSet'] . '" is neither installed nor in this bundle.';
		}

		if ($message !== null) {
			$errors[] = $prefix . $message;
			return null;
		}

		return [
			'id' => $item['id'],
			'tokenSet' => $item['tokenSet'],
			'startAt' => $start,
			'endAt' => $end,
			'syncCoreTheming' => (($item['syncCoreTheming'] ?? false) === true),
			'createdBy' => (string)($item['createdBy'] ?? ''),
			'createdAt' => (string)($item['createdAt'] ?? ''),
			'status' => 'planned',
		];
	}//end validateImportEntry()

	/**
	 * The window of an entry in seconds; no end means forever.
	 *
	 * @param array<string, mixed> $entry The entry.
	 *
	 * @return array{0: int, 1: int} Start and end.
	 */
	private static function window(array $entry): array {
		$start = (int)strtotime((string)($entry['startAt'] ?? ''));
		$end = PHP_INT_MAX;
		if (($entry['endAt'] ?? null) !== null) {
			$end = (int)strtotime((string)$entry['endAt']);
		}

		return [$start, $end];
	}//end window()
}//end class
