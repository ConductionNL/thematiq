<?php

/**
 * The collaborators that fetch and judge the gallery index.
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
 * @spec openspec/specs/theme-gallery/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCP\Http\Client\IClientService;
use Psr\Log\LoggerInterface;

/**
 * The HTTP client the index is downloaded with, the validator that decides which entries may be listed, and the logger for a failed fetch.
 *
 * @spec openspec/specs/theme-gallery/spec.md
 */
final class GalleryIndexFetch {

	/**
	 * Constructor.
	 *
	 * @param IClientService $clientService The HTTP client service.
	 * @param GalleryEntryValidator $entries Decides which index entries may be listed.
	 * @param LoggerInterface $logger The logger.
	 *
	 * @spec openspec/specs/theme-gallery/spec.md
	 */
	public function __construct(
		public readonly IClientService $clientService,
		public readonly GalleryEntryValidator $entries,
		public readonly LoggerInterface $logger,
	) {
	}//end __construct()
}//end class
