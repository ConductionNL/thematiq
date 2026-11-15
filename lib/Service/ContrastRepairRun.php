<?php

/**
 * The state of one contrast repair of a converted semantic layer.
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
 * @spec openspec/specs/token-sync-workflow/spec.md#requirement-converted-and-gated-sync
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * One run of {@see ConverterColourRepair::repairContrast()}: the table's
 * `contrastRepair` section and the page colour it measures against, and the
 * semantic layer, manifest values, first values and report it changes.
 *
 * @spec openspec/specs/token-sync-workflow/spec.md#requirement-converted-and-gated-sync
 *
 * @phpstan-import-type ReportEntry from TokenSetConverterService
 * @psalm-import-type   ReportEntry from TokenSetConverterService
 */
final class ContrastRepairRun {

	/**
	 * The semantic layer, repaired in place.
	 *
	 * @var array<string, string>
	 */
	public array $semantic = [];

	/**
	 * The manifest values; the primary follows a repaired primary.
	 *
	 * @var array<string, string>
	 */
	public array $manifest = [];

	/**
	 * The conversion report, appended to.
	 *
	 * @var array<int, ReportEntry>
	 */
	public array $report = [];

	/**
	 * The first value of every repaired token.
	 *
	 * @var array<string, string>
	 */
	public array $originals = [];

	/**
	 * Constructor.
	 *
	 * @param array<string, mixed> $spec The table's contrastRepair section; it has a `pairs` list.
	 * @param array{0: int, 1: int, 2: int} $page The page colour.
	 */
	public function __construct(
		public readonly array $spec,
		public readonly array $page,
	) {
	}//end __construct()

	/**
	 * The default values the section names, for tokens the theme leaves out.
	 *
	 * @return array<string, mixed> The defaults.
	 *
	 * @spec openspec/specs/token-sync-workflow/spec.md#requirement-converted-and-gated-sync
	 */
	public function defaults(): array {
		return (array)($this->spec['defaults'] ?? []);
	}//end defaults()

	/**
	 * The page colour and the defaults, as a pair repair reads them.
	 *
	 * @return array{page: array{0: int, 1: int, 2: int}, defaults: array<string, mixed>} The context.
	 *
	 * @spec openspec/specs/token-sync-workflow/spec.md#requirement-converted-and-gated-sync
	 */
	public function context(): array {
		return ['page' => $this->page, 'defaults' => $this->defaults()];
	}//end context()
}//end class
