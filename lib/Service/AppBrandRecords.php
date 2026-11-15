<?php

/**
 * Thematiq App Brand Records.
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
 * @spec openspec/specs/per-app-theming/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCA\Thematiq\AppInfo\Application;
use OCP\IConfig;

/**
 * The stored app brands, read cleaned and written as one map, and their
 * bundle section. Built by AppBrandService.
 *
 * @spec openspec/specs/per-app-theming/spec.md
 */
final class AppBrandRecords {

	/**
	 * Constructor.
	 *
	 * @param IConfig $config The app config the brands are stored in.
	 */
	public function __construct(
		private readonly IConfig $config,
	) {
	}//end __construct()

	/**
	 * The stored brands, cleaned.
	 *
	 * @return array<string, array{tokenSet: string, logoLarge: array<string, mixed>|null, logoSmall: array<string, mixed>|null}> The brands.
	 *
	 * @spec openspec/specs/per-app-theming/spec.md
	 */
	public function all(): array {
		$raw = json_decode((string)$this->config->getAppValue(Application::APP_ID, AppBrandService::CONFIG_KEY, '{}'), true);
		if (is_array($raw) === false) {
			return [];
		}

		$brands = [];
		foreach ($raw as $appId => $brand) {
			$clean = $this->cleanBrand(brand: $brand);
			if ($clean !== null) {
				$brands[(string)$appId] = $clean;
			}
		}

		return $brands;
	}//end all()

	/**
	 * One stored brand, cleaned; null when it names no string set.
	 *
	 * @param mixed $brand The stored brand.
	 *
	 * @return array{tokenSet: string, logoLarge: array<string, mixed>|null, logoSmall: array<string, mixed>|null}|null The brand.
	 */
	private function cleanBrand(mixed $brand): array|null {
		if (is_array($brand) === false || is_string($brand['tokenSet'] ?? null) === false) {
			return null;
		}

		return [
			'tokenSet' => $brand['tokenSet'],
			'logoLarge' => $this->arrayOrNull(value: ($brand['logoLarge'] ?? null)),
			'logoSmall' => $this->arrayOrNull(value: ($brand['logoSmall'] ?? null)),
		];
	}//end cleanBrand()

	/**
	 * Persist the brands.
	 *
	 * @param array<string, mixed> $brands The brands.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/per-app-theming/spec.md
	 */
	public function save(array $brands): void {
		$this->config->setAppValue(Application::APP_ID, AppBrandService::CONFIG_KEY, (string)json_encode((object)$brands));
	}//end save()

	/**
	 * An app's brand with a set, keeping the logos it had.
	 *
	 * @param array<string, mixed>|null $stored The app's stored brand, if any.
	 * @param string $tokenSet The set id.
	 *
	 * @return array{tokenSet: string, logoLarge: mixed, logoSmall: mixed} The brand.
	 *
	 * @spec openspec/specs/per-app-theming/spec.md
	 */
	public function withLogos(array|null $stored, string $tokenSet): array {
		return [
			'tokenSet' => $tokenSet,
			'logoLarge' => ($stored['logoLarge'] ?? null),
			'logoSmall' => ($stored['logoSmall'] ?? null),
		];
	}//end withLogos()

	/**
	 * Validate the bundle's `appBrands` section. Absent leaves the brands alone; logos are never applied.
	 *
	 * @param mixed $section The section, or null.
	 * @param callable(string): bool $setExists Whether a set id exists on the target or in the bundle.
	 *
	 * @return array{errors: array<int, string>, value: array<string, string>|null} The errors and the app => set map to apply.
	 *
	 * @spec openspec/specs/per-app-theming/spec.md
	 */
	public function validateBundle(mixed $section, callable $setExists): array {
		if ($section === null) {
			return ['errors' => [], 'value' => null];
		}

		if ($section instanceof \stdClass) {
			// The export writes an empty map as an object, so it reads back as `{}`.
			$section = (array)$section;
		}

		if (is_array($section) === false) {
			return ['errors' => ['"appBrands" must be an object keyed by app id.'], 'value' => null];
		}

		return $this->bundleBrands(section: $section, setExists: $setExists);
	}//end validateBundle()

	/**
	 * The app => set map of a bundle section, refused at the first app that names no available set.
	 *
	 * @param array<mixed> $section The section.
	 * @param callable(string): bool $setExists Whether a set id exists on the target or in the bundle.
	 *
	 * @return array{errors: array<int, string>, value: array<string, string>|null} The errors and the map.
	 */
	private function bundleBrands(array $section, callable $setExists): array {
		$value = [];
		foreach ($section as $appId => $brand) {
			$tokenSet = ($this->arrayOrNull(value: $brand)['tokenSet'] ?? null);
			if (is_string($tokenSet) === false || $setExists($tokenSet) === false) {
				return ['errors' => ['"appBrands.' . $appId . '" names no available token set.'], 'value' => null];
			}

			$value[(string)$appId] = $tokenSet;
		}

		return ['errors' => [], 'value' => $value];
	}//end bundleBrands()

	/**
	 * An array, or null for anything else.
	 *
	 * @param mixed $value The value.
	 *
	 * @return array<string, mixed>|null The array, or null.
	 *
	 * @spec openspec/specs/per-app-theming/spec.md
	 */
	private function arrayOrNull(mixed $value): ?array {
		if (is_array($value) === true) {
			return $value;
		}

		return null;
	}//end arrayOrNull()
}//end class
