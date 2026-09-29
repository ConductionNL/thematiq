<?php

/**
 * NL Design Email Template.
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

use OC\Mail\EMailTemplate;
use OCA\Thematiq\Service\EmailThemingService;

/**
 * Branded email template — extends the server's private EMailTemplate.
 *
 * Registered via the `mail_template_class` system config, read by
 * `OC\Mail\Mailer::makeTemplate()` (server checkout, `lib/private/Mail/Mailer.php`),
 * which is the platform's own sanctioned hook for deep mail branding: it
 * instantiates any class satisfying `is_a($class, \OC\Mail\EMailTemplate::class,
 * true)` and falls back to the stock template whenever the configured class
 * does not exist (app disabled, manifest missing) — extending the
 * private-namespace parent is therefore not a hack, it is the mechanism the
 * server provides and guards.
 *
 * The server constructs this class with a FIXED argument list (Defaults,
 * IURLGenerator, l10n IFactory, logo dimensions, emailId, data) — the
 * constructor is intentionally left untouched (inherited as-is). All
 * nldesign service resolution happens lazily inside the render methods, and
 * every resolution is wrapped so ANY failure (nldesign disabled mid-request,
 * unreadable manifest, DI failure) degrades to the parent's stock rendering
 * — a theming failure must never prevent an email from being built or sent.
 *
 * @spec openspec/specs/email-theming/spec.md
 */
class NLDesignEMailTemplate extends EMailTemplate {
	/**
	 * Lazily resolve the email theming service.
	 *
	 * Returns null on ANY failure so callers fall back to stock rendering.
	 * This resolver gates presentation fallback only (branded vs. stock mail
	 * markup) — it is never used for an authorization decision, so it is not
	 * subject to the unsafe-auth-resolver concern (a null return here can
	 * never widen access; at most it removes branding).
	 *
	 * Protected (not private) so unit tests can override this single seam to
	 * inject a stubbed EmailThemingService, instead of touching the live
	 * \OCP\Server container that the server calls this constructor from.
	 *
	 * @return EmailThemingService|null The service, or null on any failure.
	 *
	 * @spec openspec/specs/email-theming/spec.md
	 *
	 * @SuppressWarnings(PHPMD.StaticAccess) - Mailer::makeTemplate() constructs this class with a
	 * fixed argument list, so constructor injection is impossible; \OCP\Server::get() is the
	 * sanctioned lazy-resolution seam here.
	 */
	protected function getEmailThemingService(): ?EmailThemingService {
		try {
			// phpcs:ignore CustomSniffs.Nextcloud.NoServiceLocator.GlobalContainerLookup -- Not a DI-built class: the server's Mailer::makeTemplate() constructs this template with a fixed argument list, so there is no constructor to inject a container into.
			$service = \OCP\Server::get(EmailThemingService::class);
			if ($service instanceof EmailThemingService === false) {
				return null;
			}

			return $service;
		} catch (\Throwable $e) {
			return null;
		}
	}//end getEmailThemingService()

	/**
	 * Resolve the active token set's email theme, or null if unavailable.
	 *
	 * @return array{primaryColor: string, primaryTextColor: string, logoUrl: ?string}|null
	 *                                                                                      The resolved theme, or null when no service/active theme is
	 *                                                                                      available.
	 *
	 * @spec openspec/specs/email-theming/spec.md
	 */
	private function resolveTheme(): ?array {
		$service = $this->getEmailThemingService();
		if ($service === null) {
			return null;
		}

		try {
			return $service->getActiveEmailTheme();
		} catch (\Throwable $e) {
			return null;
		}
	}//end resolveTheme()

	/**
	 * Resolve the configured compliance footer, or null if unavailable.
	 *
	 * @return array{orgName: string, accessibilityUrl: string, privacyUrl: string}|null
	 *                                                                                   The footer config, or null when no service is available.
	 *
	 * @spec openspec/specs/email-theming/spec.md
	 */
	private function resolveFooterConfig(): ?array {
		$service = $this->getEmailThemingService();
		if ($service === null) {
			return null;
		}

		try {
			return $service->getFooterConfig();
		} catch (\Throwable $e) {
			return null;
		}
	}//end resolveFooterConfig()

	/**
	 * Whether a resolved footer config has at least one non-empty value.
	 *
	 * @param array{orgName: string, accessibilityUrl: string, privacyUrl: string} $footerConfig The footer config.
	 *
	 * @return bool True when at least one field is non-empty.
	 *
	 * @spec openspec/specs/email-theming/spec.md
	 */
	private function hasFooterValues(array $footerConfig): bool {
		return ($footerConfig['orgName'] ?? '') !== ''
			|| ($footerConfig['accessibilityUrl'] ?? '') !== ''
			|| ($footerConfig['privacyUrl'] ?? '') !== '';
	}//end hasFooterValues()

	/**
	 * Adds a header to the email.
	 *
	 * Branded with the active token set's primary color and logo when
	 * available; falls back to the parent's stock header (theme colors)
	 * otherwise.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/email-theming/spec.md
	 */
	public function addHeader(): void {
		if ($this->headerAdded === true) {
			return;
		}

		$theme = $this->resolveTheme();
		if ($theme === null) {
			parent::addHeader();
			return;
		}

		$this->headerAdded = true;

		$logoSizeDimensions = '';
		if ($this->logoWidth !== null && $this->logoHeight !== null) {
			// Provide a logo size when we have the dimensions so it displays
			// nicely in Outlook — mirrors the parent's behaviour exactly.
			$logoSizeDimensions = ' width="' . $this->logoWidth . '" height="' . $this->logoHeight . '"';
		}

		$logoUrl = $theme['logoUrl'] ?? null;
		if ($logoUrl === null || $logoUrl === '') {
			$logoUrl = $this->urlGenerator->getAbsoluteURL($this->themingDefaults->getLogo(false));
		}

		$this->htmlBody .= vsprintf(
			$this->header,
			[
				$theme['primaryColor'],
				$logoUrl,
				$this->themingDefaults->getName(),
				$logoSizeDimensions,
			]
		);
	}//end addHeader()

	/**
	 * Adds a button to the body of the email.
	 *
	 * Branded with the active token set's primary color/text color when
	 * available; falls back to the parent's stock button otherwise. Reuses
	 * the parent's `$button` HEREDOC markup unchanged — only the substituted
	 * color values change.
	 *
	 * @param string $text Text of button (HTML-escaped unless $plainText given).
	 * @param string $url URL of button.
	 * @param string|false $plainText Text of button in plain text version, or false to omit.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/email-theming/spec.md
	 */
	public function addBodyButton(string $text, string $url, $plainText = ''): void {
		$theme = $this->resolveTheme();
		if ($theme === null) {
			parent::addBodyButton(text: $text, url: $url, plainText: $plainText);
			return;
		}

		if ($this->footerAdded === true) {
			return;
		}

		$this->ensureBodyIsOpened();
		$this->ensureBodyListClosed();

		if ($plainText === '') {
			$plainText = $text;
			$text = htmlspecialchars($text);
		}

		$color = $theme['primaryColor'];
		$textColor = $theme['primaryTextColor'];
		$this->htmlBody .= vsprintf($this->button, [$color, $color, $url, $color, $textColor, $textColor, $text]);

		if ($plainText !== false) {
			$this->plainBody .= $plainText . ': ';
		}

		$this->plainBody .= $url . PHP_EOL;
	}//end addBodyButton()

	/**
	 * Adds a button group of two buttons to the body of the email.
	 *
	 * Branded analogously to addBodyButton() — the primary (left) button
	 * uses the token set's color/text color; the secondary (right) button
	 * keeps the parent's neutral styling.
	 *
	 * @param string $textLeft Text of left button.
	 * @param string $urlLeft URL of left button.
	 * @param string $textRight Text of right button.
	 * @param string $urlRight URL of right button.
	 * @param string $plainTextLeft Plain-text override for the left button.
	 * @param string $plainTextRight Plain-text override for the right button.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/email-theming/spec.md
	 */
	public function addBodyButtonGroup(
		string $textLeft,
		string $urlLeft,
		string $textRight,
		string $urlRight,
		string $plainTextLeft = '',
		string $plainTextRight = '',
	): void {
		$theme = $this->resolveTheme();
		if ($theme === null) {
			parent::addBodyButtonGroup(
				textLeft: $textLeft,
				urlLeft: $urlLeft,
				textRight: $textRight,
				urlRight: $urlRight,
				plainTextLeft: $plainTextLeft,
				plainTextRight: $plainTextRight
			);
			return;
		}

		if ($this->footerAdded === true) {
			return;
		}

		if ($plainTextLeft === '') {
			$plainTextLeft = $textLeft;
			$textLeft = htmlspecialchars($textLeft);
		}

		if ($plainTextRight === '') {
			$plainTextRight = $textRight;
			$textRight = htmlspecialchars($textRight);
		}

		$this->ensureBodyIsOpened();
		$this->ensureBodyListClosed();

		$color = $theme['primaryColor'];
		$textColor = $theme['primaryTextColor'];

		$this->htmlBody .= vsprintf(
			$this->buttonGroup,
			[$color, $color, $urlLeft, $color, $textColor, $textColor, $textLeft, $urlRight, $textRight]
		);
		$this->plainBody .= PHP_EOL . $plainTextLeft . ': ' . $urlLeft . PHP_EOL;
		$this->plainBody .= $plainTextRight . ': ' . $urlRight . PHP_EOL . PHP_EOL;
	}//end addBodyButtonGroup()

	/**
	 * Adds a logo and text to the footer, then appends the configured
	 * compliance block (organization name, accessibility statement link,
	 * privacy statement link) when at least one value is configured.
	 *
	 * Always calls the parent implementation first — when no footer values
	 * are configured (or the service is unavailable), the output is
	 * byte-identical to stock. When values ARE configured, the compliance
	 * block is spliced in BEFORE the parent's closing `$tail` (which closes
	 * `</body></html>`), so the appended markup stays inside a valid HTML
	 * document instead of trailing after the closing tag.
	 *
	 * @param string $text The footer text override (parent semantics unchanged).
	 * @param string|null $lang The language override (parent semantics unchanged).
	 *
	 * @return void
	 *
	 * @spec openspec/specs/email-theming/spec.md
	 */
	public function addFooter(string $text = '', ?string $lang = null): void {
		if ($this->footerAdded === true) {
			return;
		}

		parent::addFooter(text: $text, lang: $lang);

		$footerConfig = $this->resolveFooterConfig();
		if ($footerConfig === null || $this->hasFooterValues(footerConfig: $footerConfig) === false) {
			return;
		}

		$complianceHtml = $this->renderComplianceFooterHtml(footerConfig: $footerConfig, lang: $lang);
		$tailFound = str_ends_with($this->htmlBody, $this->tail);
		if ($tailFound === true) {
			$this->htmlBody = substr($this->htmlBody, 0, -strlen($this->tail)) . $complianceHtml . $this->tail;
		}

		if ($tailFound === false) {
			// Defensive: parent's tail marker not found verbatim (should not
			// happen given the current server implementation) — append
			// rather than silently drop the compliance block.
			$this->htmlBody .= $complianceHtml;
		}

		$this->plainBody .= $this->renderComplianceFooterPlain(footerConfig: $footerConfig, lang: $lang);
	}//end addFooter()

	/**
	 * Render the compliance footer block as HTML, output-encoded.
	 *
	 * @param array{orgName: string, accessibilityUrl: string, privacyUrl: string} $footerConfig The footer config.
	 * @param string|null $lang The language override.
	 *
	 * @return string The HTML block (a standalone table, valid inside `<body>`).
	 *
	 * @spec openspec/specs/email-theming/spec.md
	 */
	private function renderComplianceFooterHtml(array $footerConfig, ?string $lang): string {
		$l10n = $this->l10nFactory->get('thematiq', $lang);

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
	}//end renderComplianceFooterHtml()

	/**
	 * Render the compliance footer block as plain text lines.
	 *
	 * @param array{orgName: string, accessibilityUrl: string, privacyUrl: string} $footerConfig The footer config.
	 * @param string|null $lang The language override.
	 *
	 * @return string The plain-text lines, each terminated with PHP_EOL.
	 *
	 * @spec openspec/specs/email-theming/spec.md
	 */
	private function renderComplianceFooterPlain(array $footerConfig, ?string $lang): string {
		$l10n = $this->l10nFactory->get('thematiq', $lang);

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
	}//end renderComplianceFooterPlain()
}//end class
