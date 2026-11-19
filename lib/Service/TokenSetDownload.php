<?php

/**
 * One token set as it is downloaded: what it declares and the file format asked for.
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
 * @spec openspec/specs/token-set-dtcg-export/spec.md#requirement-any-token-set-can-be-downloaded-as-a-dtcg-document
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * A token set download: the set, its own declarations, the format of the
 * file, and what the DTCG document records beside the values (the app
 * version and the deprecation records).
 *
 * @spec openspec/specs/token-set-dtcg-export/spec.md#requirement-any-token-set-can-be-downloaded-as-a-dtcg-document
 */
final class TokenSetDownload {

	/**
	 * Constructor.
	 *
	 * @param string $setId The set id.
	 * @param string $setName The set's display name.
	 * @param array<string, string> $declarations Custom property => value, as the set declares them.
	 * @param string $format One of FigmaTokenWriter::FORMATS.
	 * @param string $appVersion The app version, in the DTCG document's root extension.
	 * @param array<string, array<string, mixed>> $deprecations Token name => deprecation record.
	 */
	public function __construct(
		public readonly string $setId,
		public readonly string $setName,
		public readonly array $declarations,
		public readonly string $format = 'dtcg',
		public readonly string $appVersion = '',
		public readonly array $deprecations = [],
	) {
	}//end __construct()
}//end class
