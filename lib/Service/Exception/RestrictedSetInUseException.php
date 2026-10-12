<?php

/**
 * Thematiq Restricted Set In Use Exception.
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
 * @spec openspec/changes/token-set-licence-gate/specs/token-set-licences/spec.md#requirement-a-confirmation-can-be-withdrawn-once-the-set-is-no-longer-applied
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service\Exception;

/**
 * Thrown when an administrator withdraws the confirmation of a restricted
 * token set that is still applied somewhere.
 *
 * @spec openspec/changes/token-set-licence-gate/specs/token-set-licences/spec.md#requirement-a-confirmation-can-be-withdrawn-once-the-set-is-no-longer-applied
 */
class RestrictedSetInUseException extends \RuntimeException {

	/**
	 * Constructor.
	 *
	 * @param string $tokenSetId The set whose confirmation was to be withdrawn.
	 * @param list<array{kind: string, id: string}> $appliedIn Where the set is applied.
	 */
	public function __construct(string $tokenSetId, private readonly array $appliedIn) {
		parent::__construct(message: sprintf('Token set "%s" is still applied.', $tokenSetId), code: 409);
	}//end __construct()

	/**
	 * Where the set is applied.
	 *
	 * @return list<array{kind: string, id: string}> One entry per place.
	 *
	 * @spec openspec/changes/token-set-licence-gate/specs/token-set-licences/spec.md#requirement-a-confirmation-can-be-withdrawn-once-the-set-is-no-longer-applied
	 */
	public function getAppliedIn(): array {
		return $this->appliedIn;
	}//end getAppliedIn()
}//end class
