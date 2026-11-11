<?php

/**
 * The state of validating one configuration bundle.
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
 * One validation of a configuration bundle: the bundle, every section error
 * found so far, and the normalised data each section resolved to.
 *
 * @spec openspec/specs/config-portability/spec.md
 */
final class BundleValidationRun {

	/**
	 * The section errors found so far.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	public array $errors = [];

	/**
	 * The normalised, ready-to-apply data, by section.
	 *
	 * @var array<string, mixed>
	 */
	public array $resolved = [];

	/**
	 * Constructor.
	 *
	 * @param array<string, mixed> $bundle The decoded bundle.
	 */
	public function __construct(
		public readonly array $bundle,
	) {
	}//end __construct()
}//end class
