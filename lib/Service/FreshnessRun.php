<?php

/**
 * Thematiq Freshness Run.
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
 * @spec openspec/specs/upstream-freshness/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCP\Http\Client\IClient;

/**
 * One upstream freshness check: the client and the pinned manifest URL it
 * uses, the head revision it found, and the paths the compare request
 * listed. Built by UpstreamFreshnessService.
 *
 * @spec openspec/specs/upstream-freshness/spec.md
 */
final class FreshnessRun {

	/**
	 * The fetched upstream head SHA.
	 *
	 * @var string
	 */
	public string $headSha = '';

	/**
	 * The paths the compare request listed.
	 *
	 * @var array<int, string>
	 */
	public array $changedPaths = [];

	/**
	 * Constructor.
	 *
	 * @param IClient $client The client, reused for the compare request.
	 * @param string $manifestUrl The pinned manifest URL.
	 */
	public function __construct(
		public readonly IClient $client,
		public readonly string $manifestUrl,
	) {
	}//end __construct()
}//end class
