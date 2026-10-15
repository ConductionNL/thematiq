<?php

/**
 * Writes one token set as the OpenProject plugin's set file.
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
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCA\Thematiq\Service\RuntimeFile\RuntimeFileStore;

/**
 * The set file is the set's shell catalogue entry with its logo. A shipped set carries the
 * logo verdict of the catalogue. An uploaded set names no logo: the file travels without
 * pictures, and the plugin keeps its own.
 *
 * @spec openspec/changes/openproject-set-file/specs/openproject-set-file/spec.md#requirement-any-token-set-can-be-downloaded-as-the-plugins-set-file
 */
class OpenProjectSetFileWriter {

	/**
	 * The logo block of a set that ships no logo verdict.
	 *
	 * @var array<string, string|null>
	 */
	public const NO_LOGO = [
		'file' => null,
		'inverse_file' => null,
		'licence' => null,
		'redistributable' => null,
		'basis' => 'An uploaded set: no logo travels in this file.',
	];

	/**
	 * Constructor.
	 *
	 * @param ShellCatalogueService $catalogue Builds the set's entry and its logo verdict.
	 * @param ReuseLicences $licences Reads `token-sets.json` and `REUSE.toml`.
	 */
	public function __construct(
		private readonly ShellCatalogueService $catalogue,
		private readonly ReuseLicences $licences = new ReuseLicences(),
	) {
	}//end __construct()

	/**
	 * One set as the plugin's set file.
	 *
	 * @param string $appPath The app root path.
	 * @param string $id The set id.
	 * @param string $name The set's name.
	 * @param RuntimeFileStore|null $store Where uploaded sets live.
	 *
	 * @return array<string, mixed> The set file.
	 *
	 * @spec openspec/changes/openproject-set-file/specs/openproject-set-file/spec.md#requirement-any-token-set-can-be-downloaded-as-the-plugins-set-file
	 */
	public function setFile(string $appPath, string $id, string $name, ?RuntimeFileStore $store): array {
		$logo = self::NO_LOGO;
		foreach ((array)json_decode($this->licences->read(path: $appPath . '/token-sets.json'), true) as $meta) {
			if (is_array($meta) === true && ($meta['id'] ?? null) === $id) {
				$logo = $this->catalogue->logoOf(appPath: $appPath, id: $id, meta: $meta, annotations: $this->licences->annotations(appPath: $appPath));
			}
		}

		$set = $this->catalogue->entry(appPath: $appPath, id: $id, name: $name, store: $store);

		return (array_slice($set, 0, 3, true) + ['logo' => $logo] + array_slice($set, 3, null, true));
	}//end setFile()
}//end class
