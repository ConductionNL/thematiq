<?php

/**
 * Thematiq Theme Version Service.
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
 * @spec openspec/specs/theme-versions/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCA\Thematiq\AppInfo\Application;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\AppData\IAppDataFactory;
use OCP\Files\NotFoundException;
use OCP\Files\SimpleFS\ISimpleFolder;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Keeps a version of the complete configuration after every audited change.
 *
 * A version is the configuration bundle ConfigBundleService::export()
 * produces, stored as `versions/<id>.json` in app data with the audit action
 * and actor that produced it. The id is the UTC second plus an ordinal
 * (`YmdHis-NNNN`), so file names sort in capture order. At most 50 versions
 * and 20 MB are kept, oldest removed first. A capture never throws: a failure
 * is logged and the change it follows stands, the same contract as the audit
 * log.
 *
 * @spec openspec/specs/theme-versions/spec.md
 */
class ThemeVersionService {

	/**
	 * The app data folder.
	 */
	private const FOLDER_NAME = 'versions';

	/**
	 * The most versions kept.
	 */
	public const MAX_COUNT = 50;

	/**
	 * The most bytes kept across all versions.
	 */
	public const MAX_BYTES = 20971520;

	/**
	 * A version id: the UTC second and a four digit ordinal.
	 */
	private const ID_PATTERN = '/^\d{14}-\d{4}$/';

	/**
	 * Constructor.
	 *
	 * @param IAppDataFactory $appDataFactory The app data factory.
	 * @param ConfigBundleService $bundleService Produces the bundle a version holds.
	 * @param ITimeFactory $timeFactory The clock.
	 * @param LoggerInterface $logger Records a version that could not be kept.
	 */
	public function __construct(
		private readonly IAppDataFactory $appDataFactory,
		private readonly ConfigBundleService $bundleService,
		private readonly ITimeFactory $timeFactory,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Keep the current configuration as a new version.
	 *
	 * @param string $auditAction The audit action that produced this state.
	 * @param string $actor The actor of that action.
	 *
	 * @return string|null The version id, or null when no version was kept.
	 *
	 * @spec openspec/specs/theme-versions/spec.md
	 */
	public function capture(string $auditAction, string $actor): ?string {
		try {
			$time = $this->timeFactory->getTime();
			$folder = $this->getFolder();
			$id = $this->nextId(folder: $folder, prefix: gmdate('YmdHis', $time));
			$record = [
				'id' => $id,
				'ts' => gmdate('Y-m-d\TH:i:s\Z', $time),
				'actor' => $actor,
				'action' => $auditAction,
				'bundle' => $this->bundleService->export(),
			];
			$folder->newFile($id . '.json', json_encode($record, (JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)));
			$this->prune(folder: $folder);

			return $id;
		} catch (Throwable $e) {
			$this->logger->warning(
				'thematiq: no version was kept after "{action}": {message}',
				['app' => Application::APP_ID, 'action' => $auditAction, 'message' => $e->getMessage()]
			);

			return null;
		}//end try
	}//end capture()

	/**
	 * The kept versions, newest first, without their bundles.
	 *
	 * @return array<int, array{id: string, ts: string, actor: string, action: string}> The versions.
	 *
	 * @spec openspec/specs/theme-versions/spec.md
	 */
	public function list(): array {
		try {
			$folder = $this->getFolder();
		} catch (Throwable $e) {
			return [];
		}

		$versions = [];
		foreach (array_reverse($this->sortedNames(folder: $folder)) as $name) {
			$record = $this->read(folder: $folder, name: $name);
			if ($record === null) {
				continue;
			}

			$versions[] = [
				'id' => (string)$record['id'],
				'ts' => (string)($record['ts'] ?? ''),
				'actor' => (string)($record['actor'] ?? ''),
				'action' => (string)($record['action'] ?? ''),
			];
		}

		return $versions;
	}//end list()

	/**
	 * Read one version, bundle included.
	 *
	 * @param string $id The version id.
	 *
	 * @return array<string, mixed>|null The version, or null when unknown or malformed.
	 *
	 * @spec openspec/specs/theme-versions/spec.md
	 */
	public function get(string $id): ?array {
		if (preg_match(self::ID_PATTERN, $id) !== 1) {
			return null;
		}

		try {
			return $this->read(folder: $this->getFolder(), name: $id . '.json');
		} catch (Throwable $e) {
			return null;
		}
	}//end get()

	/**
	 * Read and decode one version file.
	 *
	 * @param ISimpleFolder $folder The versions folder.
	 * @param string $name The file name.
	 *
	 * @return array<string, mixed>|null The record, or null when absent or unreadable.
	 */
	private function read(ISimpleFolder $folder, string $name): ?array {
		if ($folder->fileExists($name) === false) {
			return null;
		}

		$record = json_decode($folder->getFile($name)->getContent(), true);
		if (is_array($record) === false || isset($record['id'], $record['bundle']) === false || is_array($record['bundle']) === false) {
			return null;
		}

		return $record;
	}//end read()

	/**
	 * The next free id for this second.
	 *
	 * @param ISimpleFolder $folder The versions folder.
	 * @param string $prefix The `YmdHis` second.
	 *
	 * @return string The id.
	 */
	private function nextId(ISimpleFolder $folder, string $prefix): string {
		$ordinal = 1;
		foreach ($this->sortedNames(folder: $folder) as $name) {
			if (str_starts_with($name, $prefix . '-') === true) {
				$ordinal = max($ordinal, ((int)substr($name, 15, 4) + 1));
			}
		}

		return sprintf('%s-%04d', $prefix, $ordinal);
	}//end nextId()

	/**
	 * Version file names, oldest first.
	 *
	 * @param ISimpleFolder $folder The versions folder.
	 *
	 * @return array<int, string> The names.
	 */
	private function sortedNames(ISimpleFolder $folder): array {
		$names = [];
		foreach ($folder->getDirectoryListing() as $file) {
			$name = $file->getName();
			if (preg_match('/^\d{14}-\d{4}\.json$/', $name) === 1) {
				$names[] = $name;
			}
		}

		sort($names, SORT_STRING);

		return $names;
	}//end sortedNames()

	/**
	 * Remove the oldest versions beyond the count and size caps.
	 *
	 * @param ISimpleFolder $folder The versions folder.
	 *
	 * @return void
	 */
	private function prune(ISimpleFolder $folder): void {
		$names = $this->sortedNames(folder: $folder);
		$sizes = [];
		foreach ($names as $name) {
			$sizes[$name] = (int)$folder->getFile($name)->getSize();
		}

		$total = array_sum($sizes);
		$kept = count($names);
		while ($kept > 1 && ($kept > self::MAX_COUNT || $total > self::MAX_BYTES)) {
			$oldest = array_shift($names);
			$total -= $sizes[$oldest];
			$kept--;
			$folder->getFile($oldest)->delete();
		}
	}//end prune()

	/**
	 * The versions folder, created on first use.
	 *
	 * @return ISimpleFolder The folder.
	 */
	private function getFolder(): ISimpleFolder {
		$root = $this->appDataFactory->get(Application::APP_ID);

		try {
			return $root->getFolder(self::FOLDER_NAME);
		} catch (NotFoundException $e) {
			return $root->newFolder(self::FOLDER_NAME);
		}
	}//end getFolder()
}//end class
