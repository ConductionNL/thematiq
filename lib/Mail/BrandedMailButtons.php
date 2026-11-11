<?php

/**
 * The buttons of a branded mail: their texts, their markup in the set's colours and their plain-text lines.
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
 * Writes the parent's button markup in the active set's colours, and the matching plain-text lines.
 *
 * @spec openspec/specs/email-theming/spec.md
 */
final class BrandedMailButtons {

	/**
	 * Constructor.
	 *
	 * @param array<string, mixed> $theme The active email theme, with `primaryColor` and `primaryTextColor`.
	 */
	public function __construct(
		private readonly array $theme,
	) {
	}//end __construct()

	/**
	 * A button's HTML text and plain text: without a plain override, the text is the plain text and is escaped.
	 *
	 * @param string $text The button text.
	 * @param mixed $plainText The plain-text override, '' for none or false to omit.
	 *
	 * @return array{0: string, 1: mixed} The HTML text and the plain text.
	 *
	 * @spec openspec/specs/email-theming/spec.md
	 */
	public function texts(string $text, mixed $plainText): array {
		if ($plainText === '') {
			return [htmlspecialchars($text), $text];
		}

		return [$text, $plainText];
	}//end texts()

	/**
	 * A button's HTML and plain-text halves.
	 *
	 * @param MailButton $button The button as passed in.
	 * @param string $plainText The plain-text override, '' for none.
	 *
	 * @return array{0: MailButton, 1: MailButton} The button for the HTML part and for the text part.
	 *
	 * @spec openspec/specs/email-theming/spec.md
	 */
	public function parts(MailButton $button, string $plainText): array {
		[$text, $plain] = $this->texts(text: $button->text, plainText: $plainText);

		return [new MailButton(text: $text, url: $button->url), new MailButton(text: (string)$plain, url: $button->url)];
	}//end parts()

	/**
	 * The parent's button markup in the set's colours.
	 *
	 * @param string $template The parent's button markup.
	 * @param MailButton $button The button, its text escaped.
	 *
	 * @return string The HTML.
	 *
	 * @spec openspec/specs/email-theming/spec.md
	 */
	public function button(string $template, MailButton $button): string {
		$color = $this->theme['primaryColor'];
		$textColor = $this->theme['primaryTextColor'];

		return vsprintf($template, [$color, $color, $button->url, $color, $textColor, $textColor, $button->text]);
	}//end button()

	/**
	 * The parent's button group markup, its left button in the set's colours.
	 *
	 * @param string $template The parent's button group markup.
	 * @param MailButtonPair $buttons The buttons, their texts escaped.
	 *
	 * @return string The HTML.
	 *
	 * @spec openspec/specs/email-theming/spec.md
	 */
	public function group(string $template, MailButtonPair $buttons): string {
		$color = $this->theme['primaryColor'];
		$textColor = $this->theme['primaryTextColor'];

		return vsprintf(
			$template,
			[$color, $color, $buttons->left->url, $color, $textColor, $textColor, $buttons->left->text, $buttons->right->url, $buttons->right->text]
		);
	}//end group()

	/**
	 * A button's line in the plain-text part.
	 *
	 * @param mixed $plainText The plain text, or false to write the URL alone.
	 * @param string $url The URL.
	 *
	 * @return string The line.
	 *
	 * @spec openspec/specs/email-theming/spec.md
	 */
	public function plainLine(mixed $plainText, string $url): string {
		$line = '';
		if ($plainText !== false) {
			$line = $plainText . ': ';
		}

		return $line . $url . PHP_EOL;
	}//end plainLine()

	/**
	 * A button group's lines in the plain-text part.
	 *
	 * @param MailButtonPair $buttons The buttons, with their plain texts.
	 *
	 * @return string The lines.
	 *
	 * @spec openspec/specs/email-theming/spec.md
	 */
	public function groupPlain(MailButtonPair $buttons): string {
		return PHP_EOL . $buttons->left->text . ': ' . $buttons->left->url . PHP_EOL
			. $buttons->right->text . ': ' . $buttons->right->url . PHP_EOL . PHP_EOL;
	}//end groupPlain()
}//end class
