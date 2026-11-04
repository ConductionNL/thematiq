<?php

/**
 * The compliance footer of a branded mail.
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

use OCP\IL10N;

/**
 * Renders the organisation name and the accessibility and privacy links under a branded mail.
 *
 * @spec openspec/specs/email-theming/spec.md
 */
class ComplianceMailFooter {

	/**
	 * Render the compliance footer block as HTML, output-encoded.
	 *
	 * @param array{orgName: string, accessibilityUrl: string, privacyUrl: string} $footerConfig The footer config.
	 * @param IL10N $l10n The language to write the labels in.
	 *
	 * @return string The HTML block (a standalone table, valid inside `<body>`).
	 *
	 * @spec openspec/specs/email-theming/spec.md
	 */
	public function html(array $footerConfig, IL10N $l10n): string {
		$lines = $this->htmlLines(footerConfig: $footerConfig, l10n: $l10n);
		if (empty($lines) === true) {
			return '';
		}

		$paragraphs = '';
		foreach ($lines as $line) {
			$paragraphs .= '<p class="text-center float-center" align="center" style="Margin:0;Margin-bottom:4px;'
				. 'color:#C8C8C8;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Oxygen-Sans,Ubuntu,'
				. 'Cantarell,\'Helvetica Neue\',Arial,sans-serif;font-size:12px;font-weight:400;line-height:16px;'
				. 'margin:0;margin-bottom:4px;padding:0;text-align:center">' . $line . '</p>';
		}

		return '<table align="center" class="wrapper footer float-center nldesign-compliance-footer" '
			. 'style="Margin:0 auto;border-collapse:collapse;border-spacing:0;float:none;margin:0 auto;padding:0;'
			. 'text-align:center;vertical-align:top;width:100%">'
			. '<tr style="padding:0;text-align:left;vertical-align:top">'
			. '<td class="wrapper-inner" style="Margin:0;padding:0;text-align:left;vertical-align:top">'
			. '<center data-parsed="" style="min-width:580px;width:100%">' . $paragraphs . '</center>'
			. '</td></tr></table>';
	}//end html()

	/**
	 * Render the compliance footer block as plain text lines.
	 *
	 * @param array{orgName: string, accessibilityUrl: string, privacyUrl: string} $footerConfig The footer config.
	 * @param IL10N $l10n The language to write the labels in.
	 *
	 * @return string The plain-text lines, each terminated with PHP_EOL.
	 *
	 * @spec openspec/specs/email-theming/spec.md
	 */
	public function plain(array $footerConfig, IL10N $l10n): string {
		$plain = '';
		if ($footerConfig['orgName'] !== '') {
			$plain .= $footerConfig['orgName'] . PHP_EOL;
		}

		if ($footerConfig['accessibilityUrl'] !== '') {
			$plain .= $l10n->t('Accessibility statement') . ': ' . $footerConfig['accessibilityUrl'] . PHP_EOL;
		}

		if ($footerConfig['privacyUrl'] !== '') {
			$plain .= $l10n->t('Privacy statement') . ': ' . $footerConfig['privacyUrl'] . PHP_EOL;
		}

		return $plain;
	}//end plain()

	/**
	 * The footer's lines as output-encoded HTML.
	 *
	 * @param array{orgName: string, accessibilityUrl: string, privacyUrl: string} $footerConfig The footer config.
	 * @param IL10N $l10n The language to write the labels in.
	 *
	 * @return array<int, string> The lines.
	 */
	private function htmlLines(array $footerConfig, IL10N $l10n): array {
		$lines = [];
		if ($footerConfig['orgName'] !== '') {
			$lines[] = htmlspecialchars($footerConfig['orgName']);
		}

		if ($footerConfig['accessibilityUrl'] !== '') {
			$lines[] = '<a href="' . htmlspecialchars($footerConfig['accessibilityUrl']) . '" style="color:#C8C8C8;text-decoration:underline">'
				. htmlspecialchars($l10n->t('Accessibility statement')) . '</a>';
		}

		if ($footerConfig['privacyUrl'] !== '') {
			$lines[] = '<a href="' . htmlspecialchars($footerConfig['privacyUrl']) . '" style="color:#C8C8C8;text-decoration:underline">'
				. htmlspecialchars($l10n->t('Privacy statement')) . '</a>';
		}

		return $lines;
	}//end htmlLines()
}//end class
