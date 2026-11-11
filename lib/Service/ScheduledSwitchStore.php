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
 * @spec openspec/specs/scheduled-switch/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
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
 * @spec openspec/specs/scheduled-switch/spec.md
 * @spec openspec/specs/config-portability/spec.md
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
	 * (`status`, `revertTo`, `failureReason`) stays on the server. An older
	 * bundle's `syncCoreTheming` is ignored: every switch now brings the
	 * Nextcloud logo and colours along, as the apply dialog does.
	 *
	 * @var array<int, string>
	 */
	public const PORTABLE_FIELDS = ['id', 'tokenSet', 'startAt', 'endAt', 'createdBy', 'createdAt'];

	/**
	 * The server-side state a running switch keeps when a bundle carrying it
	 * is imported: where it goes back to, and how.
	 *
	 * @var array<int, string>
	 */
	private const RUNNING_FIELDS = ['status', 'revertTo', 'coreSnapshot', 'syncCoreTheming'];

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
	 * @spec openspec/specs/scheduled-switch/spec.md
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
	 * @spec openspec/specs/scheduled-switch/spec.md
	 */
	public function save(array $entries): void {
		$this->config->setAppValue(Application::APP_ID, self::CONFIG_KEY, (string)json_encode(array_values($entries), JSON_UNESCAPED_SLASHES));
	}//end save()

	/**
	 * The validated bundle entries to store over the current ones.
	 *
	 * A bundle carries portable fields only, so every entry arrives as
	 * `planned`. Storing that as it is put a running switch back to planned,
	 * and the next run started it again: it took its snapshot from the
	 * campaign look and set the campaign's own set as the one to return to.
	 * Every version kept during a campaign holds the switch, so restoring any
	 * of them lost the branding from before the campaign. A stored running
	 * entry with the same id therefore keeps its runtime state, and an entry
	 * whose window has already ended is dropped rather than replayed.
	 *
	 * @param array<int, array<string, mixed>> $imported The validated bundle entries.
	 * @param int $now The current time.
	 *
	 * @return array<int, array<string, mixed>> The entries to store.
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 */
	public function mergeImported(array $imported, int $now): array {
		$running = $this->runningById();

		$result = [];
		foreach ($imported as $entry) {
			$stored = ($running[$entry['id']] ?? null);
			if ($stored !== null) {
				$result[] = $this->withRunningState(entry: $entry, stored: $stored);
			} elseif ($this->hasEnded(end: $entry['endAt'], now: $now) === false) {
				$result[] = $entry;
			}
		}

		return $result;
	}//end mergeImported()

	/**
	 * The stored running entries that have a string id, by id.
	 *
	 * @return array<string, array<string, mixed>> The entries.
	 */
	private function runningById(): array {
		$running = [];
		foreach ($this->all() as $entry) {
			if (($entry['status'] ?? null) === 'running' && is_string($entry['id'] ?? null) === true) {
				$running[$entry['id']] = $entry;
			}
		}

		return $running;
	}//end runningById()

	/**
	 * An imported entry with the runtime fields of the stored running entry it replaces.
	 *
	 * @param array<string, mixed> $entry The imported entry.
	 * @param array<string, mixed> $stored The stored running entry with the same id.
	 *
	 * @return array<string, mixed> The entry to store.
	 */
	private function withRunningState(array $entry, array $stored): array {
		foreach (self::RUNNING_FIELDS as $field) {
			if (array_key_exists($field, $stored) === true) {
				$entry[$field] = $stored[$field];
			}
		}

		return $entry;
	}//end withRunningState()

	/**
	 * Whether a window's end has passed. A window without an end never ends.
	 *
	 * @param string|null $end The end in UTC, or null.
	 * @param int $now The current time.
	 *
	 * @return bool True when the end is at or before now.
	 *
	 * @spec openspec/specs/scheduled-switch/spec.md
	 */
	public function hasEnded(?string $end, int $now): bool {
		return $end !== null && strtotime($end) <= $now;
	}//end hasEnded()

	/**
	 * The entries as a bundle carries them: portable fields only.
	 *
	 * @return array<int, array<string, mixed>> The entries.
	 *
	 * @spec openspec/specs/config-portability/spec.md
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
	 * @spec openspec/specs/scheduled-switch/spec.md
	 */
	public function toUtc(mixed $value): ?string {
		if (is_string($value) === false || preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/', $value) !== 1) {
			return null;
		}

		try {
			$time = new DateTimeImmutable($value, new DateTimeZone('UTC'));
		} catch (Exception $e) {
			return null;
		}

		return $time->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
	}//end toUtc()

	/**
	 * The first entry whose window overlaps the candidate's, ignoring failed
	 * entries and the candidate itself. A window without an end runs forever.
	 *
	 * @param array<string, mixed> $candidate The entry to check (UTC times).
	 * @param array<int, array<string, mixed>> $entries The entries to check against.
	 *
	 * @return array<string, mixed>|null The overlapping entry, or null.
	 *
	 * @spec openspec/specs/scheduled-switch/spec.md
	 */
	public function findOverlap(array $candidate, array $entries): ?array {
		$window = $this->window(entry: $candidate);
		foreach ($entries as $entry) {
			if ($this->outOfOverlap(entry: $entry, candidate: $candidate) === true) {
				continue;
			}

			if ($this->overlaps(window: $window, entry: $entry) === true) {
				return $entry;
			}
		}

		return null;
	}//end findOverlap()

	/**
	 * Whether an entry's window overlaps a window.
	 *
	 * @param array{0: int, 1: int} $window The start and end in seconds.
	 * @param array<string, mixed> $entry The entry.
	 *
	 * @return bool True when they overlap.
	 */
	private function overlaps(array $window, array $entry): bool {
		[$otherStart, $otherEnd] = $this->window(entry: $entry);

		return $window[0] < $otherEnd && $otherStart < $window[1];
	}//end overlaps()

	/**
	 * Whether an entry is left out of the overlap check: it failed, or it is the candidate itself.
	 *
	 * @param array<string, mixed> $entry The entry.
	 * @param array<string, mixed> $candidate The entry being checked.
	 *
	 * @return bool True when it is left out.
	 */
	private function outOfOverlap(array $entry, array $candidate): bool {
		return ($entry['status'] ?? 'planned') === 'failed' || ($entry['id'] ?? null) === ($candidate['id'] ?? null);
	}//end outOfOverlap()

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
	public function validateImport(mixed $raw, callable $setExists): array {
		return (new SwitchImportValidation(store: $this))->validate(raw: $raw, setExists: $setExists);
	}//end validateImport()

	/**
	 * The window of an entry in seconds; no end means forever.
	 *
	 * @param array<string, mixed> $entry The entry.
	 *
	 * @return array{0: int, 1: int} Start and end.
	 */
	private function window(array $entry): array {
		$start = (int)strtotime((string)($entry['startAt'] ?? ''));
		$end = PHP_INT_MAX;
		if (($entry['endAt'] ?? null) !== null) {
			$end = (int)strtotime((string)$entry['endAt']);
		}

		return [$start, $end];
	}//end window()
}//end class
