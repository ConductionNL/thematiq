<?php

/**
 * Thematiq multi-brand import.
 *
 * One token source, several brands: the first upload lists the brands and stores
 * nothing; the confirmed upload stores each chosen brand as an ordinary custom set
 * ("{source}: {brand}") linked to its source; a source update replaces every listed
 * brand together, or none. The raw source is not kept, only its hash, in appconfig
 * `custom_token_sources`.
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
 * @spec openspec/specs/multi-brand-token-sources/spec.md#requirement-a-brand-is-a-custom-token-set-linked-to-its-source
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCA\Thematiq\AppInfo\Application;
use RuntimeException;

/**
 * Import and update multi-brand sources.
 *
 * @spec openspec/specs/multi-brand-token-sources/spec.md#requirement-a-brand-is-a-custom-token-set-linked-to-its-source
 */
class MultiBrandImportService {

	/**
	 * The appconfig key of the source records.
	 *
	 * @var string
	 */
	public const SOURCES_KEY = 'custom_token_sources';

	/**
	 * The most brands one import may store.
	 *
	 * @var int
	 */
	public const MAX_BRANDS = 20;

	/**
	 * Constructor.
	 *
	 * @param BrandConversion $conversion The brand source, the converter, the CSS whitelist and the CSS parser.
	 * @param BrandSetStorage $storage Custom set storage, the contrast maths and the dark palette.
	 * @param BrandImportRecords $records The app config and the audit trail.
	 */
	public function __construct(
		private readonly BrandConversion $conversion,
		private readonly BrandSetStorage $storage,
		private readonly BrandImportRecords $records,
	) {
	}//end __construct()

	/**
	 * The brands of a source, or [] for a one-brand source.
	 *
	 * @param string $content The uploaded document.
	 *
	 * @return array<int, array<string, mixed>>
	 *
	 * @spec openspec/specs/multi-brand-token-sources/spec.md#requirement-a-brand-is-a-custom-token-set-linked-to-its-source
	 */
	public function brands(string $content): array {
		return $this->conversion->source->detectBrands(content: $content);
	}//end brands()

	/**
	 * Store the chosen brands of a source, all or none.
	 *
	 * @param string $sourceName The display name of the source.
	 * @param string $content The uploaded document.
	 * @param array<int, string> $keys The chosen brand keys.
	 * @param string|null $fileName The uploaded file's name.
	 *
	 * @return array{sourceId: string, sets: array<int, array<string, mixed>>}
	 *
	 * @throws RuntimeException 422 for an unknown or too many keys or a brand that fails
	 *                          validation, 409 for a set or source that already exists.
	 *
	 * @spec openspec/specs/multi-brand-token-sources/spec.md#requirement-a-brand-is-a-custom-token-set-linked-to-its-source
	 */
	public function import(string $sourceName, string $content, array $keys, ?string $fileName = null): array {
		$detected = array_column($this->brands(content: $content), null, 'key');
		$keys = $this->checkedKeys(keys: $keys, detected: array_keys($detected));

		$sourceId = $this->storage->customSets->slugify(name: $sourceName);
		if ($sourceId === '' || isset($this->records()[$sourceId]) === true) {
			throw new RuntimeException('A source named "' . $sourceName . '" already exists. Update it instead.', 409);
		}

		$plans = [];
		foreach ($keys as $key) {
			$displayName = $sourceName . ': ' . (string)$detected[$key]['name'];
			$id = CustomTokenSetId::PREFIX . $this->storage->customSets->slugify(name: $displayName);
			if (isset($this->storage->customSets->getManifest()[$id]) === true || $this->storage->customSets->getRawContent(id: $id) !== null) {
				throw new RuntimeException('The brand "' . $detected[$key]['name'] . '" would replace the existing set ' . $id . '.', 409);
			}

			$converted = $this->convertBrand(content: $content, brand: ['key' => $key, 'displayName' => $displayName, 'fileName' => $fileName]);
			$plans[$key] = ['displayName' => $displayName, 'id' => $id] + $converted;
		}

		$sets = [];
		foreach ($plans as $key => $plan) {
			$sets[] = $this->storeBrand(origin: ['sourceId' => $sourceId, 'key' => $key, 'fileName' => $fileName], plan: $plan);
		}

		$records = $this->records();
		$records[$sourceId] = [
			'name' => $sourceName,
			'inputKind' => (string)reset($plans)['inputKind'],
			'contentHash' => $this->hash(content: $content),
			'brands' => array_map(static fn (array $plan): string => $plan['id'], $plans),
			'updatedAt' => gmdate(DATE_ATOM),
		];
		$this->saveRecords(records: $records);

		return ['sourceId' => $sourceId, 'sets' => $sets];
	}//end import()

	/**
	 * The chosen keys, each one the source has, at most MAX_BRANDS.
	 *
	 * @param array<int, mixed> $keys The chosen keys.
	 * @param array<int, string> $detected The keys the source has.
	 *
	 * @return array<int, string>
	 *
	 * @throws RuntimeException 422 naming what is wrong.
	 */
	private function checkedKeys(array $keys, array $detected): array {
		$keys = array_values(array_unique(array_map('strval', $keys)));
		$unknown = array_diff($keys, $detected);
		if ($keys === [] || $unknown !== []) {
			throw new RuntimeException('Choose brands this source has: ' . implode(', ', $unknown), 422);
		}

		if (count($keys) > self::MAX_BRANDS) {
			throw new RuntimeException('One import can hold at most ' . self::MAX_BRANDS . ' brands.', 422);
		}

		return $keys;
	}//end checkedKeys()

	/**
	 * Replace every brand a source lists from new content, all or none.
	 *
	 * @param string $sourceId The source id.
	 * @param string $content The new document.
	 * @param string|null $fileName The uploaded file's name.
	 *
	 * @return array{updated: array<int, string>, missing: array<int, string>, new: array<int, string>}
	 *
	 * @throws RuntimeException 404 for an unknown source, 422 for a brand that fails validation.
	 *
	 * @spec openspec/specs/multi-brand-token-sources/spec.md#requirement-a-new-version-of-a-source-updates-every-brand-together
	 */
	public function update(string $sourceId, string $content, ?string $fileName = null): array {
		$records = $this->records();
		$record = ($records[$sourceId] ?? null);
		if (is_array($record) === false) {
			throw new RuntimeException('Unknown source.', 404);
		}

		$detected = array_column($this->brands(content: $content), null, 'key');
		$listed = (array)$record['brands'];
		$present = array_intersect_key($listed, $detected);
		$plans = [];
		foreach ($present as $key => $id) {
			$manifest = ($this->storage->customSets->getManifest()[$id] ?? ['name' => $id]);
			$converted = $this->convertBrand(
				content: $content,
				brand: ['key' => (string)$key, 'displayName' => (string)$manifest['name'], 'fileName' => $fileName]
			);
			$plans[$key] = ['id' => $id, 'entry' => $manifest] + $converted;
		}

		foreach ($plans as $plan) {
			$entry = $plan['entry'];
			$entry['warnings'] = $this->storage->contrast->check(declarations: $plan['accepted']);
			$entry['theming'] = array_merge((array)($entry['theming'] ?? []), $plan['theming']);
			$this->storage->customSets->replace(id: $plan['id'], entry: $entry, css: $plan['css']);
			$this->regenerateDark(id: $plan['id']);
		}

		$result = [
			'updated' => array_map('strval', array_keys($plans)),
			'missing' => array_map('strval', array_keys(array_diff_key($listed, $detected))),
			'new' => array_map('strval', array_keys(array_diff_key($detected, $listed))),
		];

		$oldHash = (string)($record['contentHash'] ?? '');
		$records[$sourceId]['contentHash'] = $this->hash(content: $content);
		$records[$sourceId]['updatedAt'] = gmdate(DATE_ATOM);
		$this->saveRecords(records: $records);

		$this->records->audit->log(
			action: 'custom_source_updated',
			context: ['sourceId' => $sourceId, 'old' => $oldHash, 'new' => $records[$sourceId]['contentHash']] + $result
		);

		return $result;
	}//end update()

	/**
	 * Forget a deleted brand set; the source record goes with its last brand.
	 *
	 * @param string $id The deleted set id.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/multi-brand-token-sources/spec.md#requirement-a-new-version-of-a-source-updates-every-brand-together
	 */
	public function forgetSet(string $id): void {
		$records = $this->records();
		foreach ($records as $sourceId => $record) {
			$brands = array_filter((array)($record['brands'] ?? []), static fn ($setId): bool => $setId !== $id);
			if ($brands === (array)($record['brands'] ?? [])) {
				continue;
			}

			if ($brands === []) {
				unset($records[$sourceId]);
				continue;
			}

			$records[$sourceId]['brands'] = $brands;
		}

		$this->saveRecords(records: $records);
	}//end forgetSet()

	/**
	 * Every source record.
	 *
	 * @return array<string, array<string, mixed>>
	 *
	 * @spec openspec/specs/multi-brand-token-sources/spec.md#requirement-a-brand-is-a-custom-token-set-linked-to-its-source
	 */
	public function records(): array {
		$decoded = json_decode($this->records->config->getAppValue(Application::APP_ID, self::SOURCES_KEY, '{}'), true);

		if (is_array($decoded) === false) {
			return [];
		}

		return array_filter($decoded, 'is_array');
	}//end records()

	/**
	 * Convert and validate one brand, writing nothing.
	 *
	 * @param string $content The source.
	 * @param array<string, mixed> $brand The brand key, the set's display name and the uploaded file's name.
	 *
	 * @return array{css: string, accepted: array<string, string>, theming: array<string, mixed>, inputKind: string, imported: int}
	 *
	 * @throws RuntimeException 422 naming the brand.
	 */
	private function convertBrand(string $content, array $brand): array {
		['key' => $key, 'displayName' => $displayName, 'fileName' => $fileName] = $brand;
		$slug = $this->storage->customSets->slugify(name: $displayName);
		$cut = $this->conversion->source->cut(content: $content, key: $key);
		try {
			$converted = $this->conversion->converter->convert(
				content: $cut['content'],
				slug: $slug,
				displayName: $displayName,
				options: ['sourceName' => $fileName, 'assetName' => CustomTokenSetId::PREFIX . $slug, 'referenceOnlyPaths' => $cut['referenceOnlyPaths']]
			);
		} catch (RuntimeException $e) {
			throw new RuntimeException('Brand "' . $key . '": ' . $e->getMessage(), 422);
		}

		$declarations = $this->conversion->cssParser->parseRootBlock(css: (string)$converted['css']);
		$split = $this->conversion->validator->validateDeclarations(declarations: $declarations, slug: $slug);
		if ($split === null || (string)$converted['css'] === '') {
			$error = $this->conversion->validator->getLastError();
			throw new RuntimeException('Brand "' . $key . '": ' . (string)($error['message'] ?? 'nothing in it maps onto a Nextcloud token.'), 422);
		}

		return [
			'css' => (string)$converted['css'],
			'accepted' => $split['accepted'],
			'theming' => (array)($converted['manifestEntry']['theming'] ?? []),
			'inputKind' => (string)$converted['inputKind'],
			'imported' => (int)$converted['imported'],
		];
	}//end convertBrand()

	/**
	 * Store one converted brand, link it to its source, and audit it.
	 *
	 * @param array<string, mixed> $origin The source id, the brand key and the uploaded file's name.
	 * @param array<string, mixed> $plan The converted brand.
	 *
	 * @return array<string, mixed> The brand key, the set id and the warnings.
	 */
	private function storeBrand(array $origin, array $plan): array {
		['sourceId' => $sourceId, 'key' => $key, 'fileName' => $fileName] = $origin;
		$result = $this->storage->customSets->store(
			set: new NewCustomTokenSet(
				fields: [
					'displayName' => $plan['displayName'],
					'description' => '',
					'declarations' => $plan['accepted'],
					'css' => $plan['css'],
					'theming' => $plan['theming'],
				]
			)
		);

		$entry = ($this->storage->customSets->getManifest()[$result['id']] ?? []);
		$entry['source'] = ['id' => $sourceId, 'brand' => $key];
		$this->storage->customSets->replace(id: $result['id'], entry: $entry, css: $plan['css']);

		$this->records->audit->log(
			action: 'custom_set_uploaded',
			context: [
				'id' => $result['id'],
				'name' => $plan['displayName'],
				'declarationCount' => $plan['imported'],
				'sourceId' => $sourceId,
				'brand' => $key,
				'file' => $fileName,
			]
		);

		return ['brand' => $key, 'id' => $result['id'], 'warnings' => $result['warnings']];
	}//end storeBrand()

	/**
	 * A fresh dark variant; a failure never fails the update.
	 *
	 * @param string $id The set id.
	 *
	 * @return void
	 */
	private function regenerateDark(string $id): void {
		try {
			$this->storage->darkPalette->generateAndWrite(setId: $id, force: true);
		} catch (\Throwable) {
			// Presentation, not a hard dependency: see openspec/specs/dark-mode/spec.md.
			return;
		}
	}//end regenerateDark()

	/**
	 * The short hash a record keeps of its source.
	 *
	 * @param string $content The source.
	 *
	 * @return string
	 */
	private function hash(string $content): string {
		return 'sha256:' . substr(hash('sha256', $content), 0, 12);
	}//end hash()

	/**
	 * Store the source records.
	 *
	 * @param array<string, array<string, mixed>> $records Source id => record.
	 *
	 * @return void
	 */
	private function saveRecords(array $records): void {
		ksort($records);
		$this->records->config->setAppValue(Application::APP_ID, self::SOURCES_KEY, (string)json_encode($records, JSON_UNESCAPED_SLASHES));
	}//end saveRecords()
}//end class
