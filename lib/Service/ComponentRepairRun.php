<?php

/**
 * The state of one repair of a theme's own component pairs.
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
 * One run of {@see ConverterComponentRepair::repair()}: the repaired semantic
 * layer and the table's `contrastRepair` section it reads, and the component
 * layer and report it changes.
 *
 * @spec openspec/specs/token-sync-workflow/spec.md#requirement-converted-and-gated-sync
 *
 * @phpstan-import-type ReportEntry from TokenSetConverterService
 * @psalm-import-type   ReportEntry from TokenSetConverterService
 */
final class ComponentRepairRun {

	/**
	 * The component layer, repaired in place.
	 *
	 * @var array<string, string>
	 */
	public array $component = [];

	/**
	 * The conversion report, appended to.
	 *
	 * @var array<int, ReportEntry>
	 */
	public array $report = [];

	/**
	 * The page colour every pair is measured against.
	 *
	 * @var array{0: int, 1: int, 2: int}
	 */
	public array $page = [255, 255, 255];

	/**
	 * Constructor.
	 *
	 * @param array<string, string> $semantic The repaired semantic layer.
	 * @param array<string, mixed> $spec The table's contrastRepair section.
	 */
	public function __construct(
		public readonly array $semantic,
		public readonly array $spec,
	) {
	}//end __construct()

	/**
	 * The page colour as the section writes it.
	 *
	 * @return string The colour; white when the section names none.
	 *
	 * @spec openspec/specs/token-sync-workflow/spec.md#requirement-converted-and-gated-sync
	 */
	public function pageHex(): string {
		return (string)($this->spec['page'] ?? '#ffffff');
	}//end pageHex()

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
	 * The component pairs the section lists.
	 *
	 * @return array<int, mixed> The pairs.
	 *
	 * @spec openspec/specs/token-sync-workflow/spec.md#requirement-converted-and-gated-sync
	 */
	public function pairs(): array {
		return (array)($this->spec['componentPairs'] ?? []);
	}//end pairs()
}//end class
