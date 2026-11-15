<?php

/**
 * One button of a branded mail: its text and where it goes.
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
 * A mail button's text, as the part it is written into needs it, and its URL.
 *
 * @spec openspec/specs/email-theming/spec.md
 */
final class MailButton {

	/**
	 * Constructor.
	 *
	 * @param string $text The text, HTML-escaped for the HTML part or plain for the text part.
	 * @param string $url The URL.
	 */
	public function __construct(
		public readonly string $text,
		public readonly string $url,
	) {
	}//end __construct()
}//end class
