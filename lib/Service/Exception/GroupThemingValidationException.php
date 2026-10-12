<?php

/**
 * NL Design Group Theming Validation Exception.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Exception
 * @package   OCA\Thematiq
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 *
 * @spec openspec/specs/per-group-theming/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service\Exception;

/**
 * Thrown when a group-theming mapping entry fails save-time validation: an
 * unknown group id, an unavailable token-set id, or a duplicate group across
 * entries. Carries the offending entry so the controller can surface it in a
 * structured HTTP 422 response naming the entry and the reason, per the
 * "Unknown group or set is rejected without partial writes" and "Duplicate
 * group entries are rejected" scenarios.
 *
 * @spec openspec/specs/per-group-theming/spec.md
 */
class GroupThemingValidationException extends \InvalidArgumentException {

	/**
	 * The offending mapping entry, as submitted (may be malformed).
	 *
	 * @var mixed
	 */
	private mixed $entry;

	/**
	 * Constructor.
	 *
	 * @param mixed $entry The offending mapping entry.
	 * @param string $reason The human-readable validation failure reason.
	 * @param string|null $restrictedSet The set that was refused because its licence limits use to its
	 *                                   owner and nobody confirmed it; null for every other failure.
	 *
	 * @spec openspec/specs/per-group-theming/spec.md
	 */
	public function __construct(
		mixed $entry,
		string $reason,
		private readonly ?string $restrictedSet = null,
	) {
		parent::__construct(message: $reason);
		$this->entry = $entry;
	}//end __construct()

	/**
	 * The restricted set this entry was refused for, if that is the reason.
	 *
	 * @return string|null The set id, or null when the entry failed for another reason.
	 *
	 * @spec openspec/changes/token-set-licence-gate/specs/token-set-licences/spec.md#requirement-every-path-that-applies-a-set-asks-the-same-gate
	 */
	public function getRestrictedSet(): ?string {
		return $this->restrictedSet;
	}//end getRestrictedSet()

	/**
	 * Get the offending mapping entry.
	 *
	 * @return mixed The entry, exactly as submitted.
	 *
	 * @spec openspec/specs/per-group-theming/spec.md
	 */
	public function getEntry(): mixed {
		return $this->entry;
	}//end getEntry()

	/**
	 * Get the validation failure reason.
	 *
	 * @return string The reason.
	 *
	 * @spec openspec/specs/per-group-theming/spec.md
	 */
	public function getReason(): string {
		return $this->getMessage();
	}//end getReason()
}//end class
