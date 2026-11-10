<?php

/**
 * A manifest kept as one JSON object in an app config value.
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
 * @spec openspec/specs/custom-fonts/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCA\Thematiq\AppInfo\Application;
use OCP\IConfig;

/**
 * One app config key of this app: the manifest of uploaded custom sets or
 * fonts (an object indexed by id, stored as JSON and listed by name), or a
 * plain value such as a revision counter.
 *
 * @spec openspec/specs/custom-fonts/spec.md
 */
final class AppConfigManifest {

	/**
	 * Constructor.
	 *
	 * @param IConfig $config The app config.
	 * @param string $key The app config key the manifest is stored under.
	 */
	public function __construct(
		private readonly IConfig $config,
		private readonly string $key,
	) {
	}//end __construct()

	/**
	 * The decoded manifest.
	 *
	 * @return array<string, mixed> The manifest indexed by id (empty on absence/corruption).
	 *
	 * @spec openspec/specs/custom-fonts/spec.md
	 */
	public function read(): array {
		$raw = $this->config->getAppValue(Application::APP_ID, $this->key, '{}');
		$decoded = json_decode($raw, true);

		if (is_array($decoded) === false) {
			return [];
		}

		return $decoded;
	}//end read()

	/**
	 * Persist the manifest.
	 *
	 * @param array<string, mixed> $manifest The manifest indexed by id.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/custom-fonts/spec.md
	 */
	public function save(array $manifest): void {
		$this->config->setAppValue(
			Application::APP_ID,
			$this->key,
			json_encode($manifest, JSON_UNESCAPED_SLASHES)
		);
	}//end save()

	/**
	 * The stored value as it is.
	 *
	 * @param string $default The value when none is stored.
	 *
	 * @return string The value.
	 *
	 * @spec openspec/specs/custom-fonts/spec.md
	 */
	public function value(string $default): string {
		return $this->config->getAppValue(Application::APP_ID, $this->key, $default);
	}//end value()

	/**
	 * Store a plain value.
	 *
	 * @param string $value The value.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/custom-fonts/spec.md
	 */
	public function setValue(string $value): void {
		$this->config->setAppValue(Application::APP_ID, $this->key, $value);
	}//end setValue()

	/**
	 * One manifest entry as a listing returns it: its metadata with its id.
	 *
	 * @param int|string $id The entry's id.
	 * @param mixed $meta The entry's metadata.
	 *
	 * @return array<string, mixed> The entry.
	 *
	 * @spec openspec/specs/custom-fonts/spec.md
	 */
	public function listed(int|string $id, mixed $meta): array {
		$entry = [];
		if (is_array($meta) === true) {
			$entry = $meta;
		}

		$entry['id'] = $id;

		return $entry;
	}//end listed()

	/**
	 * Listed entries in name order, case-insensitively; an entry without a name sorts by its id.
	 *
	 * @param array<int, array<string, mixed>> $entries The entries.
	 *
	 * @return array<int, array<string, mixed>> The sorted entries.
	 *
	 * @spec openspec/specs/custom-fonts/spec.md
	 */
	public function byName(array $entries): array {
		usort($entries, fn ($a, $b) => strcasecmp(($a['name'] ?? $a['id']), ($b['name'] ?? $b['id'])));

		return $entries;
	}//end byName()

	/**
	 * A slug from an admin-supplied display name.
	 *
	 * Lowercases, replaces non-alphanumerics with hyphens, collapses repeats,
	 * trims hyphens, and caps at 64 characters.
	 *
	 * @param string $name The display name.
	 *
	 * @return string The derived slug (may be empty for all-symbol input).
	 *
	 * @spec openspec/specs/custom-fonts/spec.md
	 */
	public function slugify(string $name): string {
		$slug = strtolower(trim($name));
		$slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
		$slug = trim($slug, '-');

		return substr($slug, 0, 64);
	}//end slugify()
}//end class
