<?php

/**
 * Thematiq Restricted Set Not Confirmed Exception.
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
 * @spec openspec/changes/token-set-licence-gate/specs/token-set-licences/spec.md#requirement-a-restricted-set-is-refused-until-an-administrator-confirms-it
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service\Exception;

/**
 * Thrown when a token set whose licence limits its use to its owner is applied
 * before an administrator confirmed that this organisation is that owner or has
 * its permission.
 *
 * It extends \InvalidArgumentException on purpose: every path that applies a
 * set already refuses on that type, so a caller nobody updated treats a
 * restricted set as one it may not apply, never as one it may.
 *
 * @spec openspec/changes/token-set-licence-gate/specs/token-set-licences/spec.md#requirement-a-restricted-set-is-refused-until-an-administrator-confirms-it
 */
class RestrictedSetNotConfirmedException extends \InvalidArgumentException {

	/**
	 * The machine-readable reason a controller answers with.
	 */
	public const ERROR = 'restricted_set_not_confirmed';

	/**
	 * Constructor.
	 *
	 * @param string $tokenSetId The restricted set that was refused.
	 */
	public function __construct(
		private readonly string $tokenSetId,
	) {
		parent::__construct(
			message: sprintf('Token set "%s" is restricted to its owner and has not been confirmed for this instance.', $tokenSetId),
			code: 403
		);
	}//end __construct()

	/**
	 * The restricted set that was refused.
	 *
	 * @return string The set id.
	 *
	 * @spec openspec/changes/token-set-licence-gate/specs/token-set-licences/spec.md#requirement-a-restricted-set-is-refused-until-an-administrator-confirms-it
	 */
	public function getTokenSetId(): string {
		return $this->tokenSetId;
	}//end getTokenSetId()
}//end class
