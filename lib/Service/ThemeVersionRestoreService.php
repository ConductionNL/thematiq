<?php

/**
 * Thematiq Theme Version Restore Service.
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

/**
 * Previews and restores a kept version.
 *
 * Both go through ConfigBundleService::import(), a dry run first, so every
 * validator that guards an upload guards a restore and a version that no
 * longer validates is refused whole. A restore is audited as
 * `version_restored`, which keeps a new version itself, so it can be undone.
 * Its own class, because the audit service keeps versions and a restore
 * writes an audit entry.
 *
 * @spec openspec/specs/theme-versions/spec.md
 */
class ThemeVersionRestoreService {

	/**
	 * The scalar config keys a preview compares.
	 */
	private const CONFIG_FIELDS = [
		'tokenSet',
		'hideSlogan',
		'showMenuLabels',
		'primaryDrivesComponents',
		'upstreamFreshnessEnabled',
	];

	/**
	 * Constructor.
	 *
	 * @param ThemeVersionService $versions The kept versions.
	 * @param ConfigBundleService $bundles  The bundle export and import.
	 * @param ThemingAuditService $audit    The audit log.
	 * @param FontService         $fonts    The uploaded fonts.
	 */
	public function __construct(
		private readonly ThemeVersionService $versions,
		private readonly ConfigBundleService $bundles,
		private readonly ThemingAuditService $audit,
		private readonly FontService $fonts,
	) {
	}//end __construct()

	/**
	 * Show what restoring a version will change, writing nothing.
	 *
	 * @param string $id The version id.
	 *
	 * @return array<string, mixed>|null The dry run and the changes, or null for an unknown id.
	 *
	 * @spec openspec/specs/theme-versions/spec.md
	 */
	public function preview(string $id): ?array {
		$version = $this->versions->get(id: $id);
		if ($version === null) {
			return null;
		}

		$target = $version['bundle'];
		$current = $this->bundles->export();
		$dryRun = $this->bundles->import(bundle: $target, dryRun: true);

		return [
			'id'              => $id,
			'valid'           => ($dryRun['valid'] === true),
			'errors'          => ($dryRun['errors'] ?? []),
			'changes'         => $this->configChanges(current: $current, target: $target),
			'customTokenSets' => $this->customSetChanges(current: $current, target: $target),
			'missingFonts'    => $this->missingFonts(target: $target),
		];
	}//end preview()

	/**
	 * Restore a version: dry run, then import, then audit.
	 *
	 * @param string $id The version id.
	 *
	 * @return array<string, mixed>|null The preview plus `applied`, or null for an unknown id.
	 *
	 * @spec openspec/specs/theme-versions/spec.md
	 */
	public function restore(string $id): ?array {
		$preview = $this->preview(id: $id);
		if ($preview === null) {
			return null;
		}

		if ($preview['valid'] === false) {
			return array_merge($preview, ['applied' => false]);
		}

		$latest = $this->versions->list();
		$before = ($latest[0]['id'] ?? null);

		$result = $this->bundles->import(bundle: $this->versions->get(id: $id)['bundle']);
		if (($result['applied'] ?? false) !== true) {
			return array_merge($preview, ['applied' => false, 'errors' => ($result['errors'] ?? [])]);
		}

		$this->audit->log(action: 'version_restored', context: ['old' => $before, 'new' => $id]);

		return array_merge($preview, ['applied' => true]);
	}//end restore()

	/**
	 * The scalar and list config fields that differ.
	 *
	 * @param array<string, mixed> $current The current bundle.
	 * @param array<string, mixed> $target  The version's bundle.
	 *
	 * @return array<int, array{field: string, from: mixed, to: mixed}> The changes.
	 */
	private function configChanges(array $current, array $target): array {
		$changes = [];
		foreach (self::CONFIG_FIELDS as $field) {
			$from = ($current['config'][$field] ?? null);
			$to = ($target['config'][$field] ?? null);
			if ($from !== $to) {
				$changes[] = ['field' => $field, 'from' => $from, 'to' => $to];
			}
		}

		$fromApps = ($current['config']['disabledApps'] ?? []);
		$toApps = ($target['config']['disabledApps'] ?? []);
		if ($fromApps !== $toApps) {
			$changes[] = ['field' => 'disabledApps', 'from' => $fromApps, 'to' => $toApps];
		}

		if (($current['customOverridesCss'] ?? '') !== ($target['customOverridesCss'] ?? '')) {
			$changes[] = ['field' => 'customOverridesCss', 'from' => 'current', 'to' => 'version'];
		}

		if (($current['emailFooter'] ?? []) !== ($target['emailFooter'] ?? [])) {
			$changes[] = ['field' => 'emailFooter', 'from' => ($current['emailFooter'] ?? []), 'to' => ($target['emailFooter'] ?? [])];
		}

		return $changes;
	}//end configChanges()

	/**
	 * The custom token sets a restore adds and removes.
	 *
	 * @param array<string, mixed> $current The current bundle.
	 * @param array<string, mixed> $target  The version's bundle.
	 *
	 * @return array{add: array<int, string>, remove: array<int, string>} The set ids.
	 */
	private function customSetChanges(array $current, array $target): array {
		$from = array_column(($current['customTokenSets'] ?? []), 'id');
		$to = array_column(($target['customTokenSets'] ?? []), 'id');

		return [
			'add'    => array_values(array_diff($to, $from)),
			'remove' => array_values(array_diff($from, $to)),
		];
	}//end customSetChanges()

	/**
	 * The fonts a version names that are no longer uploaded. Their role
	 * stays on the default font after the restore.
	 *
	 * @param array<string, mixed> $target The version's bundle.
	 *
	 * @return array<int, array{id: string, name: string, role: string}> The missing fonts.
	 */
	private function missingFonts(array $target): array {
		$missing = [];
		foreach (($target['customFonts']['manifest'] ?? []) as $id => $entry) {
			if ($this->fonts->getEntry(id: (string)$id) === null) {
				$missing[] = [
					'id'   => (string)$id,
					'name' => (string)($entry['name'] ?? $id),
					'role' => (string)($entry['role'] ?? 'body'),
				];
			}
		}

		return $missing;
	}//end missingFonts()
}//end class
