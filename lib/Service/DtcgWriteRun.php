<?php

/**
 * The state of writing one DTCG document.
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
 * @spec openspec/specs/token-set-dtcg-export/spec.md#requirement-each-value-gets-a-dtcg-type-by-its-shape
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * One run of {@see DesignTokensWriter}: the declarations it reads, and the
 * typed tokens, untyped values, paths and document it builds up.
 *
 * @spec openspec/specs/token-set-dtcg-export/spec.md#requirement-each-value-gets-a-dtcg-type-by-its-shape
 */
final class DtcgWriteRun {

	/**
	 * Name => token, for every value DTCG has a type for.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	public array $typed = [];

	/**
	 * Name => value, for every value written to the `cssOnly` map.
	 *
	 * @var array<string, string>
	 */
	public array $cssOnly = [];

	/**
	 * Name => path, for every typed token.
	 *
	 * @var array<string, array<int, string>>
	 */
	public array $paths = [];

	/**
	 * The document being written.
	 *
	 * @var array<string, mixed>
	 */
	public array $document = [];

	/**
	 * Constructor.
	 *
	 * @param array<string, string> $declarations Custom property => value, as the set declares them.
	 * @param array<string, array<string, mixed>> $deprecations Token name => deprecation record.
	 */
	public function __construct(
		public readonly array $declarations,
		public readonly array $deprecations,
	) {
	}//end __construct()
}//end class
