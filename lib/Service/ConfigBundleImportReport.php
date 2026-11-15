<?php

/**
 * What a configuration bundle import reports for each section.
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
 * @spec openspec/specs/config-portability/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * The result of a valid bundle import: whether it was applied, and what each
 * section holds.
 *
 * @spec openspec/specs/config-portability/spec.md
 */
final class ConfigBundleImportReport {

	/**
	 * Constructor.
	 *
	 * @param ConfigBundleSections $sections The custom token sets, the extra sections and the lifecycle section.
	 */
	public function __construct(
		private readonly ConfigBundleSections $sections,
	) {
	}//end __construct()

	/**
	 * Build the per-section result summary (used for both the dry-run
	 * report and the post-apply report — identical shape either way).
	 *
	 * @param array<string, mixed> $resolved The validated, resolved bundle data.
	 *
	 * @return array<string, mixed> The per-section summary.
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 */
	public function summary(array $resolved): array {
		return [
			'config' => $this->configSummary(config: $resolved['config']),
			'scheduledSwitches' => [
				'count' => count(($resolved['scheduledSwitches'] ?? [])),
				'applied' => ($resolved['scheduledSwitches'] !== null),
			],
			'emailFooter' => ['applied' => true],
			'customOverridesCss' => [
				'written' => count($resolved['customOverrides']['tokens']),
				'skipped' => count($resolved['customOverrides']['skipped']),
			],
			'customTokenSets' => [
				'count' => count($resolved['customTokenSets']),
				'ids' => array_column($resolved['customTokenSets'], 'id'),
			],
			'customFonts' => $this->fontsSummary(fonts: $resolved['customFonts']),
		] + $this->sections->extraSections->summary(resolved: $resolved['extra'])
			+ ($this->sections->lifecycle?->summary(resolved: ($resolved['lifecycle'] ?? null)) ?? []);
	}//end summary()

	/**
	 * The summary of the `config` section.
	 *
	 * @param array<string, mixed> $config The resolved config section.
	 *
	 * @return array<string, mixed> The summary.
	 */
	private function configSummary(array $config): array {
		return [
			'tokenSet' => $config['tokenSet'],
			'hideSlogan' => $config['hideSlogan'],
			'showMenuLabels' => $config['showMenuLabels'],
			'primaryDrivesComponents' => $config['primaryDrivesComponents'],
			'disabledAppsCount' => count($config['disabledApps']),
			'upstreamFreshnessEnabled' => $config['upstreamFreshnessEnabled'],
		];
	}//end configSummary()

	/**
	 * The summary of the `customFonts` section: recorded, never applied.
	 *
	 * @param array<int, mixed> $fonts The resolved font metadata.
	 *
	 * @return array<string, mixed> The summary.
	 */
	private function fontsSummary(array $fonts): array {
		return [
			'applied' => false,
			'binariesIncluded' => false,
			'recorded' => count($fonts),
			'note' => 'Font metadata recorded for information only — binaries are not part of the '
				. 'bundle and must be re-uploaded by hand on the target environment.',
		];
	}//end fontsSummary()

	/**
	 * The result of a valid import: applied, unless it was a dry run.
	 *
	 * @param bool $dryRun Whether the import was a dry run.
	 * @param array<string, mixed> $sections The per-section summary.
	 *
	 * @return array<string, mixed> The result.
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 */
	public function accepted(bool $dryRun, array $sections): array {
		return [
			'valid' => true,
			'dryRun' => $dryRun,
			'applied' => ($dryRun === false),
			'sections' => $sections,
		];
	}//end accepted()
}//end class
