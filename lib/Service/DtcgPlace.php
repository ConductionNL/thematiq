<?php

/**
 * Where the DTCG value checker stands in a document: the node's dotted path and its type.
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
 * @spec openspec/specs/theme-as-code/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * A node's place in a DTCG document while its colour values are checked.
 *
 * @spec openspec/specs/theme-as-code/spec.md
 */
final class DtcgPlace {

	/**
	 * Constructor.
	 *
	 * @param string|null $type The node's own type, else the nearest group's, else null.
	 * @param string $path The dotted path of the node, empty for the document itself.
	 */
	public function __construct(
		public readonly string|null $type,
		public readonly string $path,
	) {
	}//end __construct()
}//end class
