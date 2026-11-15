<?php

/**
 * Thematiq source update run: the state of one update of a recorded source.
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

/**
 * One update of a recorded source: the source id, the new document, the source
 * records, the brands the new document holds and the brands the record lists.
 * Built by MultiBrandImportService.
 *
 * @spec openspec/specs/multi-brand-token-sources/spec.md#requirement-a-new-version-of-a-source-updates-every-brand-together
 */
final class SourceUpdateRun {

	/**
	 * Every source record.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	public array $records = [];

	/**
	 * The brands the new document holds, by key.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	public array $detected = [];

	/**
	 * The brands the record lists: brand key => set id.
	 *
	 * @var array<string, string>
	 */
	public array $listed = [];

	/**
	 * Constructor.
	 *
	 * @param string $sourceId The source id.
	 * @param BrandDocument $document The new document.
	 */
	public function __construct(
		public readonly string $sourceId,
		public readonly BrandDocument $document,
	) {
	}//end __construct()
}//end class
