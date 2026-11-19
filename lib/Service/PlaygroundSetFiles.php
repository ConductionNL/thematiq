<?php

/**
 * The collaborators the playground reads set files and the server version through.
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
 * @spec openspec/changes/component-playground/specs/component-playground/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCA\Thematiq\Service\RuntimeFile\RuntimeFileStore;
use OCA\Thematiq\Service\RuntimeFile\SetFileReader;
use OCP\ServerVersion;

/**
 * Where uploaded sets and their dark files live, the reader for a set file, and the running server's version.
 * Each is optional, as it was on the playground service.
 *
 * @spec openspec/changes/component-playground/specs/component-playground/spec.md
 */
final class PlaygroundSetFiles {

	/**
	 * Constructor.
	 *
	 * @param RuntimeFileStore|null $store Where uploaded sets and their dark files live.
	 * @param SetFileReader $files Reads a set file from the release or the store.
	 * @param ServerVersion|null $serverVersion The running server's version.
	 *
	 * @spec openspec/changes/component-playground/specs/component-playground/spec.md
	 */
	public function __construct(
		public readonly ?RuntimeFileStore $store = null,
		public readonly SetFileReader $files = new SetFileReader(),
		public readonly ?ServerVersion $serverVersion = null,
	) {
	}//end __construct()
}//end class
