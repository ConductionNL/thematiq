<?php

/**
 * Thematiq Vocabulary Set.
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
 * @spec openspec/specs/token-sets/spec.md#requirement-shipped-token-set-vocabulary-completeness
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * A token set as the vocabulary audit reads it: its id and its manifest entry.
 *
 * @spec openspec/specs/token-sets/spec.md#requirement-shipped-token-set-vocabulary-completeness
 */
final class VocabularySet {

	/**
	 * Constructor.
	 *
	 * @param string $id The token set id.
	 * @param array<string, mixed> $meta The set's manifest entry (design_system, theming).
	 */
	public function __construct(
		public readonly string $id,
		public readonly array $meta,
	) {
	}//end __construct()

	/**
	 * The design system the manifest entry names, `nldesign` when it names none or no string.
	 *
	 * @return string The design-system id.
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-shipped-token-set-vocabulary-completeness
	 */
	public function designSystem(): string {
		$designSystem = ($this->meta['design_system'] ?? 'nldesign');
		if (is_string($designSystem) === false) {
			return 'nldesign';
		}

		return $designSystem;
	}//end designSystem()
}//end class
