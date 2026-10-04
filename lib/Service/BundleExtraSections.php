<?php

/**
 * Thematiq configuration bundle: the sections added after the bundle service grew full.
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
 * The `assistantMark`, `documentStyle` and `appBrands` sections of the
 * configuration bundle: export, validate, summarise and apply, each through
 * the owning service. Kept apart from {@see ConfigBundleService} so that class
 * stays within its size limits; it calls this class once in each phase.
 *
 * Every section is optional: a bundle without it (an older one) leaves that
 * configuration alone.
 *
 * @spec openspec/specs/config-portability/spec.md
 * @spec openspec/specs/assistant-approved-mark/spec.md
 * @spec openspec/specs/document-house-style/spec.md
 * @spec openspec/specs/per-app-theming/spec.md
 */
class BundleExtraSections {

	/**
	 * Constructor.
	 *
	 * @param AssistantMarkService $assistantMark The approved mark for the AI assistant.
	 * @param DocumentAssetService $documentAssets The document footer line (images travel as metadata).
	 * @param AppBrandService $appBrands The brand per app (logos travel as metadata).
	 */
	public function __construct(
		private readonly AssistantMarkService $assistantMark,
		private readonly DocumentAssetService $documentAssets,
		private readonly AppBrandService $appBrands,
	) {
	}//end __construct()

	/**
	 * The sections for an export.
	 *
	 * @return array<string, mixed> Section name => content.
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 */
	public function export(): array {
		return [
			'assistantMark' => $this->assistantMark->exportBundle(),
			'documentStyle' => $this->documentAssets->exportBundle(),
			'appBrands' => (object)$this->appBrands->exportBundle(),
		];
	}//end export()

	/**
	 * Validate the sections of a bundle.
	 *
	 * @param array<string, mixed> $bundle The decoded bundle.
	 * @param callable(string): bool $setExists Whether a set id is installed or carried by the bundle.
	 * @param array<int, array<string, mixed>> $errors Accumulator, appended to on failure.
	 *
	 * @return array<string, mixed> The values to apply per section; null means leave it alone.
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 */
	public function validate(array $bundle, callable $setExists, array &$errors): array {
		$results = [
			'assistantMark' => $this->assistantMark->validateBundle(section: ($bundle['assistantMark'] ?? null)),
			'documentStyle' => $this->documentAssets->validateBundle(section: ($bundle['documentStyle'] ?? null)),
			'appBrands' => $this->appBrands->validateBundle(section: ($bundle['appBrands'] ?? null), setExists: $setExists),
		];

		$resolved = [];
		foreach ($results as $section => $result) {
			foreach ($result['errors'] as $message) {
				$errors[] = ['section' => $section, 'message' => $message];
			}

			$resolved[$section] = $result['value'];
		}

		return $resolved;
	}//end validate()

	/**
	 * The per-section result summary.
	 *
	 * @param array<string, mixed> $resolved The values from {@see validate()}.
	 *
	 * @return array<string, mixed> Section name => summary.
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 */
	public function summary(array $resolved): array {
		return [
			'assistantMark' => ['applied' => (($resolved['assistantMark'] ?? null) !== null)],
			'documentStyle' => ['footerLineApplied' => (($resolved['documentStyle'] ?? null) !== null), 'binariesIncluded' => false],
			'appBrands' => [
				'count' => count(($resolved['appBrands'] ?? [])),
				'applied' => (($resolved['appBrands'] ?? null) !== null),
				'logosIncluded' => false,
			],
		];
	}//end summary()

	/**
	 * Apply the validated sections.
	 *
	 * @param array<string, mixed> $resolved The values from {@see validate()}.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 */
	public function apply(array $resolved): void {
		if (($resolved['assistantMark'] ?? null) !== null) {
			$this->assistantMark->applyBundle(value: $resolved['assistantMark']);
		}

		if (($resolved['documentStyle'] ?? null) !== null) {
			$this->documentAssets->setFooterLine(line: $resolved['documentStyle']);
		}

		if (($resolved['appBrands'] ?? null) !== null) {
			$this->appBrands->applyBundle(value: $resolved['appBrands']);
		}
	}//end apply()
}//end class
