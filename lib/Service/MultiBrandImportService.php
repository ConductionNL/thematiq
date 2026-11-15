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
	 * @param BrandSelection $selection The uploaded document and the chosen brand keys.
	 *
	 * @return array{sourceId: string, sets: array<int, array<string, mixed>>}
	 *
	 * @throws RuntimeException 422 for an unknown or too many keys or a brand that fails
	 *                          validation, 409 for a set or source that already exists.
	 *
	 * @spec openspec/specs/multi-brand-token-sources/spec.md#requirement-a-brand-is-a-custom-token-set-linked-to-its-source
	 */
	public function import(string $sourceName, BrandSelection $selection): array {
		$run = new BrandImportRun(sourceName: $sourceName, selection: $selection);
		$run->detected = array_column($this->brands(content: $selection->document->content), null, 'key');
		$keys = $this->checkedKeys(keys: $selection->keys, detected: array_keys($run->detected));
		$run->sourceId = $this->newSourceId(sourceName: $sourceName);

		foreach ($keys as $key) {
			$run->plans[$key] = $this->planBrand(run: $run, key: $key);
		}

		$sets = [];
		foreach (array_keys($run->plans) as $key) {
			$sets[] = $this->storeBrand(run: $run, key: $key);
		}

		$this->recordSource(run: $run);

		return ['sourceId' => $run->sourceId, 'sets' => $sets];
	}//end import()

	/**
	 * The id a new source gets from its name.
	 *
	 * @param string $sourceName The display name of the source.
	 *
	 * @return string The source id.
	 *
	 * @throws RuntimeException 409 when the name gives no id or a source with that id exists.
	 */
	private function newSourceId(string $sourceName): string {
		$sourceId = $this->storage->customSets->slugify(name: $sourceName);
		if ($sourceId === '' || isset($this->records()[$sourceId]) === true) {
			throw new RuntimeException('A source named "' . $sourceName . '" already exists. Update it instead.', 409);
		}

		return $sourceId;
	}//end newSourceId()

	/**
	 * Convert one chosen brand into the set it becomes, refusing one that would replace an existing set.
	 *
	 * @param BrandImportRun $run The import.
	 * @param string $key The brand key.
	 *
	 * @return array<string, mixed> The display name, the set id and the converted brand.
	 *
	 * @throws RuntimeException 409 when the set exists, 422 naming the brand when it fails validation.
	 */
	private function planBrand(BrandImportRun $run, string $key): array {
		$displayName = $run->sourceName . ': ' . (string)$run->detected[$key]['name'];
		$id = CustomTokenSetId::PREFIX . $this->storage->customSets->slugify(name: $displayName);
		if (isset($this->storage->customSets->getManifest()[$id]) === true || $this->storage->customSets->getRawContent(id: $id) !== null) {
			throw new RuntimeException('The brand "' . $run->detected[$key]['name'] . '" would replace the existing set ' . $id . '.', 409);
		}

		$converted = $this->convertBrand(document: $run->selection->document, brand: ['key' => $key, 'displayName' => $displayName]);

		return ['displayName' => $displayName, 'id' => $id] + $converted;
	}//end planBrand()

	/**
	 * Record a newly imported source with the sets of its brands.
	 *
	 * @param BrandImportRun $run The import, its brands stored.
	 *
	 * @return void
	 */
	private function recordSource(BrandImportRun $run): void {
		$records = $this->records();
		$records[$run->sourceId] = [
			'name' => $run->sourceName,
			'inputKind' => (string)reset($run->plans)['inputKind'],
			'contentHash' => $this->hash(content: $run->selection->document->content),
			'brands' => array_map(static fn (array $plan): string => $plan['id'], $run->plans),
			'updatedAt' => gmdate(DATE_ATOM),
		];
		$this->saveRecords(records: $records);
	}//end recordSource()

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
	 * @param BrandDocument $document The new document and the uploaded file's name.
	 *
	 * @return array{updated: array<int, string>, missing: array<int, string>, new: array<int, string>}
	 *
	 * @throws RuntimeException 404 for an unknown source, 422 for a brand that fails validation.
	 *
	 * @spec openspec/specs/multi-brand-token-sources/spec.md#requirement-a-new-version-of-a-source-updates-every-brand-together
	 */
	public function update(string $sourceId, BrandDocument $document): array {
		$run = new SourceUpdateRun(sourceId: $sourceId, document: $document);
		$run->records = $this->records();
		if (is_array(($run->records[$sourceId] ?? null)) === false) {
			throw new RuntimeException('Unknown source.', 404);
		}

		$run->detected = array_column($this->brands(content: $document->content), null, 'key');
		$run->listed = (array)$run->records[$sourceId]['brands'];
		$plans = $this->updatePlans(run: $run);
		$this->replaceBrands(plans: $plans);

		$result = $this->updateReport(run: $run, plans: $plans);
		$this->restampSource(run: $run, result: $result);

		return $result;
	}//end update()

	/**
	 * Convert every listed brand the new document still holds, writing nothing.
	 *
	 * @param SourceUpdateRun $run The update.
	 *
	 * @return array<string, array<string, mixed>> The set id, its manifest entry and the converted brand, per brand key.
	 *
	 * @throws RuntimeException 422 naming the brand that fails validation.
	 */
	private function updatePlans(SourceUpdateRun $run): array {
		$plans = [];
		foreach (array_intersect_key($run->listed, $run->detected) as $key => $id) {
			$manifest = ($this->storage->customSets->getManifest()[$id] ?? ['name' => $id]);
			$brand = ['key' => (string)$key, 'displayName' => (string)$manifest['name']];
			$converted = $this->convertBrand(document: $run->document, brand: $brand);
			$plans[$key] = ['id' => $id, 'entry' => $manifest] + $converted;
		}

		return $plans;
	}//end updatePlans()

	/**
	 * Replace each brand's set with its new conversion, in order.
	 *
	 * @param array<string, array<string, mixed>> $plans The converted brands, by key.
	 *
	 * @return void
	 */
	private function replaceBrands(array $plans): void {
		foreach ($plans as $plan) {
			$this->replaceBrand(plan: $plan);
		}
	}//end replaceBrands()

	/**
	 * Replace one brand's set with its new conversion and give it a fresh dark variant.
	 *
	 * @param array<string, mixed> $plan The set id, its manifest entry and the converted brand.
	 *
	 * @return void
	 */
	private function replaceBrand(array $plan): void {
		$entry = $plan['entry'];
		$entry['warnings'] = $this->storage->contrast->check(declarations: $plan['accepted']);
		$entry['theming'] = array_merge((array)($entry['theming'] ?? []), $plan['theming']);
		$this->storage->customSets->replace(id: $plan['id'], entry: $entry, css: $plan['css']);
		$this->regenerateDark(id: $plan['id']);
	}//end replaceBrand()

	/**
	 * Which brands were updated, which listed ones the new document lacks, and which it adds.
	 *
	 * @param SourceUpdateRun $run The update.
	 * @param array<string, array<string, mixed>> $plans The replaced brands, by key.
	 *
	 * @return array{updated: array<int, string>, missing: array<int, string>, new: array<int, string>}
	 */
	private function updateReport(SourceUpdateRun $run, array $plans): array {
		return [
			'updated' => array_map('strval', array_keys($plans)),
			'missing' => array_map('strval', array_keys(array_diff_key($run->listed, $run->detected))),
			'new' => array_map('strval', array_keys(array_diff_key($run->detected, $run->listed))),
		];
	}//end updateReport()

	/**
	 * Store the new content hash and time on the source record, and audit the update.
	 *
	 * @param SourceUpdateRun $run The update.
	 * @param array<string, array<int, string>> $result The update report.
	 *
	 * @return void
	 */
	private function restampSource(SourceUpdateRun $run, array $result): void {
		$sourceId = $run->sourceId;
		$oldHash = (string)($run->records[$sourceId]['contentHash'] ?? '');
		$run->records[$sourceId]['contentHash'] = $this->hash(content: $run->document->content);
		$run->records[$sourceId]['updatedAt'] = gmdate(DATE_ATOM);
		$this->saveRecords(records: $run->records);

		$this->records->audit->log(
			action: 'custom_source_updated',
			context: ['sourceId' => $sourceId, 'old' => $oldHash, 'new' => $run->records[$sourceId]['contentHash']] + $result
		);
	}//end restampSource()

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
	 * @param BrandDocument $document The source and the uploaded file's name.
	 * @param array{key: string, displayName: string} $brand The brand key and the set name it gets.
	 *
	 * @return array{css: string, accepted: array<string, string>, theming: array<string, mixed>, inputKind: string, imported: int}
	 *
	 * @throws RuntimeException 422 naming the brand.
	 */
	private function convertBrand(BrandDocument $document, array $brand): array {
		$slug = $this->storage->customSets->slugify(name: $brand['displayName']);
		$converted = $this->convertedBrand(document: $document, brand: $brand);
		$declarations = $this->conversion->cssParser->parseRootBlock(css: (string)$converted['css']);
		$split = $this->conversion->validator->validateDeclarations(declarations: $declarations, slug: $slug);
		if ($split === null || (string)$converted['css'] === '') {
			$error = $this->conversion->validator->getLastError();
			$reason = (string)($error['message'] ?? 'nothing in it maps onto a Nextcloud token.');
			throw new RuntimeException('Brand "' . $brand['key'] . '": ' . $reason, 422);
		}

		return $this->convertedSet(converted: $converted, accepted: $split['accepted']);
	}//end convertBrand()

	/**
	 * What a converted brand stores: its file, its accepted declarations, its theming, its input kind and its count.
	 *
	 * @param array<string, mixed> $converted The converter's result.
	 * @param array<string, string> $accepted The declarations the whitelist accepted.
	 *
	 * @return array{css: string, accepted: array<string, string>, theming: array<string, mixed>, inputKind: string, imported: int}
	 */
	private function convertedSet(array $converted, array $accepted): array {
		return [
			'css' => (string)$converted['css'],
			'accepted' => $accepted,
			'theming' => (array)($converted['manifestEntry']['theming'] ?? []),
			'inputKind' => (string)$converted['inputKind'],
			'imported' => (int)$converted['imported'],
		];
	}//end convertedSet()

	/**
	 * Cut one brand out of the source and run it through the converter.
	 *
	 * @param BrandDocument $document The source and the uploaded file's name.
	 * @param array{key: string, displayName: string} $brand The brand key and the set name it gets.
	 *
	 * @return array<string, mixed> The converter's result.
	 *
	 * @throws RuntimeException 422 naming the brand when the converter refuses it.
	 */
	private function convertedBrand(BrandDocument $document, array $brand): array {
		$slug = $this->storage->customSets->slugify(name: $brand['displayName']);
		$cut = $this->conversion->source->cut(content: $document->content, key: $brand['key']);
		$fileName = $document->fileName;
		try {
			return $this->conversion->converter->convert(
				content: $cut['content'],
				slug: $slug,
				displayName: $brand['displayName'],
				options: ['sourceName' => $fileName, 'assetName' => CustomTokenSetId::PREFIX . $slug, 'referenceOnlyPaths' => $cut['referenceOnlyPaths']]
			);
		} catch (RuntimeException $e) {
			throw new RuntimeException('Brand "' . $brand['key'] . '": ' . $e->getMessage(), 422);
		}
	}//end convertedBrand()

	/**
	 * Store one converted brand, link it to its source, and audit it.
	 *
	 * @param BrandImportRun $run The import, its brands converted.
	 * @param string $key The brand key.
	 *
	 * @return array<string, mixed> The brand key, the set id and the warnings.
	 */
	private function storeBrand(BrandImportRun $run, string $key): array {
		$plan = $run->plans[$key];
		$result = $this->storage->customSets->store(set: $this->newSet(plan: $plan));

		$entry = ($this->storage->customSets->getManifest()[$result['id']] ?? []);
		$entry['source'] = ['id' => $run->sourceId, 'brand' => $key];
		$this->storage->customSets->replace(id: $result['id'], entry: $entry, css: $plan['css']);

		$stored = ['brand' => $key, 'id' => $result['id'], 'warnings' => $result['warnings']];
		$this->auditStoredBrand(run: $run, stored: $stored);

		return $stored;
	}//end storeBrand()

	/**
	 * The new custom set one converted brand becomes.
	 *
	 * @param array<string, mixed> $plan The converted brand.
	 *
	 * @return NewCustomTokenSet The set to store.
	 */
	private function newSet(array $plan): NewCustomTokenSet {
		return new NewCustomTokenSet(
			fields: [
				'displayName' => $plan['displayName'],
				'description' => '',
				'declarations' => $plan['accepted'],
				'css' => $plan['css'],
				'theming' => $plan['theming'],
			]
		);
	}//end newSet()

	/**
	 * Audit one stored brand with its source and the uploaded file's name.
	 *
	 * @param BrandImportRun $run The import.
	 * @param array<string, mixed> $stored The brand key and the set id it was stored as.
	 *
	 * @return void
	 */
	private function auditStoredBrand(BrandImportRun $run, array $stored): void {
		$plan = $run->plans[$stored['brand']];
		$this->records->audit->log(
			action: 'custom_set_uploaded',
			context: [
				'id' => $stored['id'],
				'name' => $plan['displayName'],
				'declarationCount' => $plan['imported'],
				'sourceId' => $run->sourceId,
				'brand' => $stored['brand'],
				'file' => $run->selection->document->fileName,
			]
		);
	}//end auditStoredBrand()

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
