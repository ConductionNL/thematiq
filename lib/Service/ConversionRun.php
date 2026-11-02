<?php

/**
 * The state of one token set conversion while it runs.
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
 * @spec openspec/changes/nlds-theme-converter/specs/token-set-converter/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * What one call of {@see TokenSetConverterService::convert()} was asked for, and what its steps
 * have found so far: the report, the detected input kind, the document's package version, the
 * extracted logo and the manifest the rules write.
 *
 * One instance lives for one conversion; the converter's steps read and fill it in order.
 *
 * @phpstan-import-type ReportEntry from TokenSetConverterService
 * @psalm-import-type   ReportEntry from TokenSetConverterService
 *
 * @spec openspec/changes/nlds-theme-converter/specs/token-set-converter/spec.md
 */
final class ConversionRun {

	/**
	 * The file or package name for the provenance block.
	 *
	 * @var string|null
	 */
	public ?string $sourceName = null;

	/**
	 * Base name for an extracted logo, without extension.
	 *
	 * @var string
	 */
	public string $assetName;

	/**
	 * Whether the converted pairs are brought to WCAG AA.
	 *
	 * @var bool
	 */
	public bool $repairContrast = false;

	/**
	 * Every source token and what happened to it.
	 *
	 * @var array<int, ReportEntry>
	 */
	public array $report = [];

	/**
	 * The detected input kind (A, B, C or D).
	 *
	 * @var string
	 */
	public string $inputKind = '';

	/**
	 * The package version a JSON document declares.
	 *
	 * @var string|null
	 */
	public ?string $sourceVersion = null;

	/**
	 * The dark values of a document thematiq exported; null when the document is no such export.
	 *
	 * @var array<string, string>|null
	 */
	public ?array $exportDark = null;

	/**
	 * The logo lifted out of the declarations, or null.
	 *
	 * @var array{source: string, aliases: array<int, string>, path: string, css: string, contents: string|null}|null
	 */
	public ?array $logo = null;

	/**
	 * The manifest entries the rules write.
	 *
	 * @var array<string, mixed>
	 */
	public array $manifest = [];

	/**
	 * How many declarations the document yielded before the rules ran.
	 *
	 * @var int
	 */
	public int $imported = 0;

	/**
	 * Constructor.
	 *
	 * @param string $slug The token set slug, which is also the brand prefix.
	 * @param string $displayName The human-readable set name.
	 *
	 * @spec openspec/changes/nlds-theme-converter/specs/token-set-converter/spec.md
	 */
	public function __construct(
		public readonly string $slug,
		public readonly string $displayName,
	) {
		$this->assetName = $slug;
	}//end __construct()

	/**
	 * The set's slug and name, the file name and the document's package version.
	 *
	 * @return array{slug: string, displayName: string, sourceName: string|null, sourceVersion: string|null}
	 *
	 * @spec openspec/changes/nlds-theme-converter/specs/token-set-converter/spec.md
	 */
	public function source(): array {
		return [
			'slug' => $this->slug,
			'displayName' => $this->displayName,
			'sourceName' => $this->sourceName,
			'sourceVersion' => $this->sourceVersion,
		];
	}//end source()
}//end class
