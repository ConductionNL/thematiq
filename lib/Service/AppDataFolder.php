<?php

/**
 * An app data folder, created on first use.
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
 * @spec openspec/specs/runtime-file-storage/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Files\SimpleFS\ISimpleFolder;

/**
 * Opens a named folder of the app's app data, making it when it is not there yet.
 *
 * @spec openspec/specs/runtime-file-storage/spec.md
 */
final class AppDataFolder {

	/**
	 * The folder, created on first use.
	 *
	 * @param IAppData $appData The app's app data.
	 * @param string $name The folder name.
	 *
	 * @return ISimpleFolder The folder.
	 *
	 * @spec openspec/specs/runtime-file-storage/spec.md
	 */
	public function open(IAppData $appData, string $name): ISimpleFolder {
		try {
			return $appData->getFolder($name);
		} catch (NotFoundException $e) {
			return $appData->newFolder($name);
		}
	}//end open()
}//end class
