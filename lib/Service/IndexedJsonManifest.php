<?php

/**
 * A JSON manifest file read as its entries indexed by id.
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
 * @spec openspec/specs/token-sets/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * Reads a shipped JSON manifest (`token-sets.json`, `design-systems.json`):
 * a list of entries, each with an `id`.
 *
 * @spec openspec/specs/token-sets/spec.md
 */
final class IndexedJsonManifest {

	/**
	 * The entries of a JSON manifest file, indexed by id.
	 *
	 * An entry without an id is left out; a missing, unreadable or non-list file has none.
	 *
	 * @param string $path Absolute path to the JSON file.
	 *
	 * @return array<string, array<string, mixed>> Entries indexed by id.
	 *
	 * @spec openspec/specs/token-sets/spec.md
	 */
	public function read(string $path): array {
		if (file_exists($path) === false) {
			return [];
		}

		$content = file_get_contents($path);
		if ($content === false) {
			return [];
		}

		$data = json_decode($content, true);
		if (is_array($data) === false) {
			return [];
		}

		return $this->indexed(data: $data);
	}//end read()

	/**
	 * The entries that carry an id, by id; a later entry with the same id wins.
	 *
	 * @param array<mixed> $data The decoded list.
	 *
	 * @return array<string, array<string, mixed>> Entries indexed by id.
	 */
	private function indexed(array $data): array {
		$indexed = [];
		foreach ($data as $entry) {
			if (isset($entry['id']) === true) {
				$indexed[$entry['id']] = $entry;
			}
		}

		return $indexed;
	}//end indexed()
}//end class
