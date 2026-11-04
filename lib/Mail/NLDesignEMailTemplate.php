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
	 * The logo URL a branded header used; empty when the header is the parent's.
	 *
	 * @var string
	 */
	private string $headerLogoUrl = '';

	/**
	 * Whether that logo is the token set's own rather than the instance's.
	 *
	 * @var bool
	 */
	private bool $headerLogoIsOwn = false;

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

		$logoUrl = (string)($theme['logoUrl'] ?? '');
		$this->headerLogoIsOwn = ($logoUrl !== '');
		if ($logoUrl === '') {
			$logoUrl = $this->urlGenerator->getAbsoluteURL($this->themingDefaults->getLogo(false));
		}

		$this->headerLogoUrl = $logoUrl;
		$this->htmlBody .= vsprintf(
			$this->header,
			[
				$theme['primaryColor'],
				$this->headerLogoSource(logoUrl: $logoUrl),
				$this->themingDefaults->getName(),
				$this->logoSizeAttributes(),
			]
		);
	}//end addHeader()

	/**
	 * Whether the server attaches what getInlineImages() holds without asking for
	 * renderHtmlWithInlineImages(), so the header itself has to refer to the embedded logo.
	 *
	 * Released 35 servers do this. Later ones embed while rendering, and older ones embed nothing.
	 *
	 * @return bool True when the header embeds the logo.
	 */
	protected function serverEmbedsInHeader(): bool {
		$server = new \ReflectionClass(EMailTemplate::class);

		return $server->hasMethod('getInlineImages') === true
			&& $server->hasMethod('renderHtmlWithInlineImages') === false;
	}//end serverEmbedsInHeader()

	/**
	 * What the header's image points at: the embedded logo on a server that expects it there, else the URL.
	 *
	 * @param string $logoUrl The logo's absolute URL.
	 *
	 * @return string The image source.
	 */
	private function headerLogoSource(string $logoUrl): string {
		if ($this->serverEmbedsInHeader() === false) {
			return $logoUrl;
		}

		$image = $this->headerLogoImage();
		if ($image === null) {
			return $logoUrl;
		}

		$this->inlineImages[] = ['name' => 'logo'] + $image;

		return 'cid:logo';
	}//end headerLogoSource()

	/**
	 * The logo's size attributes, so it displays nicely in Outlook; mirrors the parent's behaviour.
	 *
	 * @return string The attributes, or empty when the dimensions are unknown.
	 */
	private function logoSizeAttributes(): string {
		if ($this->logoWidth === null || $this->logoHeight === null) {
			return '';
		}

		return ' width="' . $this->logoWidth . '" height="' . $this->logoHeight . '"';
	}//end logoSizeAttributes()

	/**
	 * The HTML part with a branded header's logo embedded instead of linked.
	 *
	 * Where the server renders through this method, the parent replaces only the logo URL it
	 * wrote itself, kept in a private member, so a branded header has to do this itself.
	 *
	 * @return string The HTML; the caller attaches getInlineImages() afterwards.
	 *
	 * @spec openspec/specs/email-theming/spec.md
	 */
	public function renderHtmlWithInlineImages(): string {
		if ($this->headerLogoUrl === '') {
			return parent::renderHtmlWithInlineImages();
		}

		$html = $this->renderHtml();
		$this->inlineImages = [];
		$logoSrc = 'src="' . $this->headerLogoUrl . '"';
		$position = strpos($html, $logoSrc);
		$image = $this->headerLogoImage();
		if ($position === false || $image === null) {
			return $html;
		}

		$this->inlineImages[] = ['name' => 'logo'] + $image;

		return substr_replace($html, 'src="cid:logo"', $position, strlen($logoSrc));
	}//end renderHtmlWithInlineImages()

	/**
	 * The bytes of the logo a branded header shows.
	 *
	 * @return array{content: string, mimeType: string}|null The image, or null when it stays linked.
	 */
	private function headerLogoImage(): ?array {
		if ($this->headerLogoIsOwn === false) {
			return $this->themingDefaults->getLogoImage();
		}

		try {
			return $this->getEmailThemingService()?->getActiveEmailLogoImage();
		} catch (\Throwable $e) {
			return null;
		}
	}//end headerLogoImage()

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

		$complianceHtml = (new ComplianceMailFooter())->html(footerConfig: $footerConfig, l10n: $this->l10nFactory->get('thematiq', $lang));
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

		$this->plainBody .= (new ComplianceMailFooter())->plain(footerConfig: $footerConfig, l10n: $this->l10nFactory->get('thematiq', $lang));
	}//end addFooter()

}//end class
