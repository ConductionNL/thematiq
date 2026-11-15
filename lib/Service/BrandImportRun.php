<?php

/**
 * Thematiq brand import run: the state of one import of a new source.
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
 * One import of a new source: its name, the selection, the source id, the brands
 * the source holds and the converted brand per chosen key. Built by MultiBrandImportService.
 *
 * @spec openspec/specs/multi-brand-token-sources/spec.md#requirement-a-brand-is-a-custom-token-set-linked-to-its-source
 */
final class BrandImportRun {

	/**
	 * The id the new source gets.
	 *
	 * @var string
	 */
	public string $sourceId = '';

	/**
	 * The brands of the source, by key.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	public array $detected = [];

	/**
	 * The converted brand per chosen key, filled before anything is stored.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	public array $plans = [];

	/**
	 * Constructor.
	 *
	 * @param string $sourceName The display name of the source.
	 * @param BrandSelection $selection The uploaded document and the chosen brand keys.
	 */
	public function __construct(
		public readonly string $sourceName,
		public readonly BrandSelection $selection,
	) {
	}//end __construct()
}//end class
