<?php

/**
 * Move runtime files an older version wrote into the app directory to app data.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Repair
 * @package   OCA\Thematiq
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 *
 * @spec openspec/specs/runtime-file-storage/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Repair;

use OCA\Thematiq\AppInfo\Application;
use OCA\Thematiq\Service\RuntimeFile\RuntimeFileNames;
use OCA\Thematiq\Service\RuntimeFile\RuntimeFileStore;
use OCP\App\IAppManager;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Moves overrides, custom CSS, uploaded sets and captured images out of the
 * app directory into the runtime file store.
 *
 * Older versions wrote these inside the app directory. Every one of them is a
 * file the release signature does not know, so Nextcloud's code integrity
 * check flagged the app on every instance an admin had customised. This step
 * copies each into app data, unless app data already holds a file of that
 * name, and then removes the app-directory copy, so the warning clears.
 *
 * Only names that can never be shipped are touched: overrides, custom CSS,
 * `custom-` sets and their dark variants, `custom-` logos, and captured
 * images. A shipped file is never moved or deleted.
 *
 * @spec openspec/specs/runtime-file-storage/spec.md
 */
class MoveRuntimeFilesToAppData implements IRepairStep {

	/**
	 * Where runtime files belong, relative to the app directory, as glob patterns.
	 *
	 * @var array<int, string>
	 */
	private const CANDIDATES = [
		'css/custom-overrides.css',
		'css/custom-overrides-*.css',
		'css/custom-css.css',
		'css/tokens/custom-*.css',
		'css/tokens/dark/custom-*.css',
		'img/logos/custom-*.*',
		'img/logos/*-captured-*.*',
		'img/backgrounds/custom-*.*',
		'img/backgrounds/*-captured-*.*',
	];

	/**
	 * Constructor.
	 *
	 * @param IAppManager $appManager Resolves the app directory.
	 * @param RuntimeFileStore $store Where runtime files belong.
	 * @param LoggerInterface $logger Logs what was moved and what could not be removed.
	 * @param RuntimeFileNames $names The name policy.
	 *
	 * @spec openspec/specs/runtime-file-storage/spec.md
	 */
	public function __construct(
		private readonly IAppManager $appManager,
		private readonly RuntimeFileStore $store,
		private readonly LoggerInterface $logger,
		private readonly RuntimeFileNames $names = new RuntimeFileNames(),
	) {
	}//end __construct()

	/**
	 * The step's name in the upgrade output.
	 *
	 * @return string The name.
	 *
	 * @spec openspec/specs/runtime-file-storage/spec.md
	 */
	public function getName(): string {
		return 'Move thematiq runtime files out of the app directory into app data';
	}//end getName()

	/**
	 * Move every runtime file found in the app directory.
	 *
	 * Never throws: a file that cannot be read, stored or removed is logged
	 * and skipped, because an exception here would abort the upgrade.
	 *
	 * @param IOutput $output The upgrade output.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/runtime-file-storage/spec.md
	 */
	public function run(IOutput $output): void {
		$appPath = rtrim($this->appManager->getAppPath(Application::APP_ID), '/');
		$moved = 0;
		foreach ($this->candidates(appPath: $appPath) as $name) {
			if ($this->moveOne(appPath: $appPath, name: $name) === true) {
				$moved++;
			}
		}

		$output->info('thematiq: moved ' . $moved . ' runtime file(s) from the app directory into app data.');
	}//end run()

	/**
	 * The runtime file names present in the app directory.
	 *
	 * @param string $appPath The app directory.
	 *
	 * @return array<int, string> App-relative names that pass the runtime name policy.
	 */
	private function candidates(string $appPath): array {
		$names = [];
		foreach (self::CANDIDATES as $pattern) {
			$paths = glob($appPath . '/' . $pattern);
			if ($paths === false) {
				continue;
			}

			foreach ($paths as $path) {
				$name = substr($path, (strlen($appPath) + 1));
				if (is_file($path) === true && $this->names->isAllowed(name: $name) === true) {
					$names[] = $name;
				}
			}
		}

		$names = array_values(array_unique($names));
		sort($names);

		return $names;
	}//end candidates()

	/**
	 * Copy one file into the store unless the store already holds it, then remove the original.
	 *
	 * @param string $appPath The app directory.
	 * @param string $name The app-relative name.
	 *
	 * @return bool True when the file was copied into the store.
	 */
	private function moveOne(string $appPath, string $name): bool {
		$path = $appPath . '/' . $name;
		$copied = false;
		try {
			if ($this->store->exists(name: $name) === false) {
				$content = file_get_contents($path);
				if ($content === false) {
					$this->logger->warning('thematiq: could not read ' . $name . ' to move it into app data.', ['app' => Application::APP_ID]);
					return false;
				}

				$this->store->write(name: $name, content: $content);
				$copied = true;
			}
		} catch (Throwable $e) {
			$this->logger->warning(
				'thematiq: could not move ' . $name . ' into app data; it stays in the app directory.',
				['app' => Application::APP_ID, 'exception' => $e]
			);
			return false;
		}

		if (is_writable($path) === false || unlink($path) === false) {
			$this->logger->warning(
				'thematiq: ' . $name . ' is now in app data, but the copy in the app directory could not be removed. '
				. 'Remove it by hand to clear the code integrity warning.',
				['app' => Application::APP_ID]
			);
		}

		return $copied;
	}//end moveOne()
}//end class
