<?php

/**
 * The two buttons of a branded mail's button group.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Mail
 * @package   OCA\Thematiq
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 *
 * @spec openspec/specs/email-theming/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Mail;

/**
 * The left (primary) and the right (secondary) button of a button group.
 *
 * @spec openspec/specs/email-theming/spec.md
 */
final class MailButtonPair {

	/**
	 * Constructor.
	 *
	 * @param MailButton $left The left, primary button.
	 * @param MailButton $right The right, secondary button.
	 */
	public function __construct(
		public readonly MailButton $left,
		public readonly MailButton $right,
	) {
	}//end __construct()
}//end class
