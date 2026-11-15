<?php

/**
 * Test double for the server's `OC\Mail\EMailTemplate`.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * lib/Mail/NLDesignEMailTemplate.php extends the server's private mail
 * template, which is not part of the nextcloud/ocp package. tests/bootstrap.php
 * loads this double only when no Nextcloud server is booted, so a standalone
 * run can execute tests/Unit/Mail; with a server the real class is used.
 *
 * Members, signatures and the plain-text output follow
 * lib/private/Mail/EMailTemplate.php on stable35, the newest declared major.
 * The HTML fragments are short neutral markup with the same placeholders,
 * because the tests compare branded output against this class's own stock
 * output, never against server markup.
 * tests/Server/Mail/EMailTemplateDoubleParityTest.php checks the double
 * against the real class in the Nextcloud CI job.
 */

declare(strict_types=1);

namespace OC\Mail;

use OCP\Defaults;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\Mail\IEMailTemplate;

/**
 * Behavioural double of the server's stock mail template.
 */
class EMailTemplate implements IEMailTemplate {
	/**
	 * The subject.
	 *
	 * @var string
	 */
	protected string $subject = '';

	/**
	 * The HTML part built so far.
	 *
	 * @var string
	 */
	protected string $htmlBody = '';

	/**
	 * The plain-text part built so far.
	 *
	 * @var string
	 */
	protected string $plainBody = '';

	/**
	 * Whether the header was added.
	 *
	 * @var bool
	 */
	protected bool $headerAdded = false;

	/**
	 * Whether the body is open.
	 *
	 * @var bool
	 */
	protected bool $bodyOpened = false;

	/**
	 * Whether a list is open in the body.
	 *
	 * @var bool
	 */
	protected bool $bodyListOpened = false;

	/**
	 * Whether the footer was added.
	 *
	 * @var bool
	 */
	protected bool $footerAdded = false;

	/**
	 * Images embedded inline, referenced via cid:.
	 *
	 * @var array<array{name: string, content: string, mimeType: string}>
	 */
	protected array $inlineImages = [];

	/**
	 * The absolute logo URL the header used.
	 *
	 * @var string
	 */
	private string $logoUrl = '';

	/**
	 * Document start.
	 *
	 * @var string
	 */
	protected string $head = '<html><body>';

	/**
	 * Document end.
	 *
	 * @var string
	 */
	protected string $tail = '</body></html>';

	/**
	 * Header: colour, logo source, name, logo size attributes.
	 *
	 * @var string
	 */
	protected string $header = '<header style="background:%s"><img src="%s" alt="%s"%s></header>';

	/**
	 * Heading: title.
	 *
	 * @var string
	 */
	protected string $heading = '<h1>%s</h1>';

	/**
	 * Body start.
	 *
	 * @var string
	 */
	protected string $bodyBegin = '<main>';

	/**
	 * Body paragraph: text.
	 *
	 * @var string
	 */
	protected string $bodyText = '<p>%s</p>';

	/**
	 * List start.
	 *
	 * @var string
	 */
	protected string $listBegin = '<ul>';

	/**
	 * List item: icon, text.
	 *
	 * @var string
	 */
	protected string $listItem = '<li>%s %s</li>';

	/**
	 * List end.
	 *
	 * @var string
	 */
	protected string $listEnd = '</ul>';

	/**
	 * Button group: colour, colour, left url, colour, text colour, text colour, left text, right url, right text.
	 *
	 * @var string
	 */
	protected string $buttonGroup = '<p style="background:%s;border-color:%s">'
		. '<a href="%s" style="background:%s;color:%s;border-color:%s">%s</a> <a href="%s">%s</a></p>';

	/**
	 * Button: colour, colour, url, colour, text colour, text colour, text.
	 *
	 * @var string
	 */
	protected string $button = '<p style="background:%s;border-color:%s"><a href="%s" style="background:%s;color:%s;border-color:%s">%s</a></p>';

	/**
	 * Body end.
	 *
	 * @var string
	 */
	protected string $bodyEnd = '</main>';

	/**
	 * Footer: text.
	 *
	 * @var string
	 */
	protected string $footer = '<footer>%s</footer>';

	/**
	 * The server's fixed constructor argument list.
	 *
	 * @param Defaults $themingDefaults The theming defaults.
	 * @param IURLGenerator $urlGenerator The URL generator.
	 * @param IFactory $l10nFactory The l10n factory.
	 * @param int|null $logoWidth The logo width.
	 * @param int|null $logoHeight The logo height.
	 * @param string $emailId The email id.
	 * @param array<string, mixed> $data The template data.
	 */
	public function __construct(
		protected Defaults $themingDefaults,
		protected IURLGenerator $urlGenerator,
		protected IFactory $l10nFactory,
		protected ?int $logoWidth,
		protected ?int $logoHeight,
		protected string $emailId,
		protected array $data,
	) {
		$this->htmlBody .= $this->head;
	}//end __construct()

	/**
	 * Set the subject.
	 *
	 * @param string $subject The subject.
	 *
	 * @return void
	 */
	public function setSubject(string $subject): void {
		$this->subject = $subject;
	}//end setSubject()

	/**
	 * Add the header once.
	 *
	 * @return void
	 */
	public function addHeader(): void {
		if ($this->headerAdded === true) {
			return;
		}

		$this->headerAdded = true;

		$logoSizeDimensions = '';
		if ($this->logoWidth !== null && $this->logoWidth !== 0 && $this->logoHeight !== null && $this->logoHeight !== 0) {
			$logoSizeDimensions = ' width="' . $this->logoWidth . '" height="' . $this->logoHeight . '"';
		}

		$this->logoUrl = $this->urlGenerator->getAbsoluteURL($this->themingDefaults->getLogo(false));
		$this->htmlBody .= vsprintf(
			$this->header,
			[$this->themingDefaults->getDefaultColorPrimary(), $this->logoUrl, $this->themingDefaults->getName(), $logoSizeDimensions]
		);
	}//end addHeader()

	/**
	 * The HTML part with the header logo embedded as an inline image.
	 *
	 * @return string
	 */
	public function renderHtmlWithInlineImages(): string {
		$html = $this->renderHtml();
		$this->inlineImages = [];
		if ($this->logoUrl === '') {
			return $html;
		}

		$logoSrc = 'src="' . $this->logoUrl . '"';
		$position = strpos($html, $logoSrc);
		if ($position === false) {
			return $html;
		}

		$logoImage = $this->themingDefaults->getLogoImage();
		if ($logoImage === null) {
			return $html;
		}

		$this->inlineImages[] = ['name' => 'logo'] + $logoImage;

		return substr_replace($html, 'src="cid:logo"', $position, strlen($logoSrc));
	}//end renderHtmlWithInlineImages()

	/**
	 * The images to embed inline.
	 *
	 * @return array<array{name: string, content: string, mimeType: string}>
	 */
	public function getInlineImages(): array {
		return $this->inlineImages;
	}//end getInlineImages()

	/**
	 * Add a heading.
	 *
	 * @param string $title The title.
	 * @param string|bool $plainTitle The plain-text title; '' uses $title, false adds none.
	 *
	 * @return void
	 */
	public function addHeading(string $title, $plainTitle = ''): void {
		if ($this->footerAdded === true) {
			return;
		}

		if ($plainTitle === '') {
			$plainTitle = $title;
		}

		$this->htmlBody .= vsprintf($this->heading, [htmlspecialchars($title)]);
		if ($plainTitle !== false) {
			$this->plainBody .= $plainTitle . PHP_EOL . PHP_EOL;
		}
	}//end addHeading()

	/**
	 * Open the body when it is not open yet.
	 *
	 * @return void
	 */
	protected function ensureBodyIsOpened(): void {
		if ($this->bodyOpened === true) {
			return;
		}

		$this->htmlBody .= $this->bodyBegin;
		$this->bodyOpened = true;
	}//end ensureBodyIsOpened()

	/**
	 * Add a paragraph.
	 *
	 * @param string $text The text; escaped when $plainText falls back to it.
	 * @param string|bool $plainText The plain-text paragraph; '' uses $text, false adds none.
	 *
	 * @return void
	 */
	public function addBodyText(string $text, $plainText = ''): void {
		if ($this->footerAdded === true) {
			return;
		}

		if ($plainText === '') {
			$plainText = $text;
			$text = htmlspecialchars($text);
		}

		$this->ensureBodyListClosed();
		$this->ensureBodyIsOpened();

		$this->htmlBody .= vsprintf($this->bodyText, [$text]);
		if ($plainText !== false) {
			$this->plainBody .= $plainText . PHP_EOL . PHP_EOL;
		}
	}//end addBodyText()

	/**
	 * Add a list item.
	 *
	 * @param string $text The text.
	 * @param string $metaInfo The meta info shown above the text.
	 * @param string $icon The icon URL.
	 * @param string|bool $plainText The plain-text item; '' or true uses $text, false adds none.
	 * @param string|bool $plainMetaInfo The plain-text meta info; '' or true uses $metaInfo.
	 * @param int $plainIndent The plain-text indent; 0 renders a bullet.
	 *
	 * @return void
	 */
	public function addBodyListItem(
		string $text,
		string $metaInfo = '',
		string $icon = '',
		$plainText = '',
		$plainMetaInfo = '',
		$plainIndent = 0,
	): void {
		$this->ensureBodyListOpened();

		if ($plainText === '' || $plainText === true) {
			$plainText = $text;
			$text = str_replace("\n", '<br/>', htmlspecialchars($text));
		}

		if ($plainMetaInfo === '' || $plainMetaInfo === true) {
			$plainMetaInfo = $metaInfo;
			$metaInfo = htmlspecialchars($metaInfo);
		}

		$htmlText = $text;
		if ($metaInfo !== '') {
			$htmlText = '<em>' . $metaInfo . '</em><br>' . $htmlText;
		}

		$iconHtml = '&bull;';
		if ($icon !== '') {
			$iconHtml = '<img src="' . htmlspecialchars($icon) . '" alt="&bull;">';
		}

		$this->htmlBody .= vsprintf($this->listItem, [$iconHtml, $htmlText]);
		if ($plainText === false) {
			return;
		}

		if ($plainIndent === 0) {
			$this->plainBody .= '  * ' . $plainText;
			if ($plainMetaInfo !== false) {
				$this->plainBody .= ' (' . $plainMetaInfo . ')';
			}

			$this->plainBody .= PHP_EOL;
			return;
		}

		$label = '';
		if ($plainMetaInfo !== false) {
			$label = $plainMetaInfo;
		}

		$this->plainBody .= sprintf(
			"%{$plainIndent}s %s\n",
			$label,
			str_replace("\n", "\n" . str_repeat(' ', $plainIndent + 1), $plainText)
		);
	}//end addBodyListItem()

	/**
	 * Open a list when none is open.
	 *
	 * @return void
	 */
	protected function ensureBodyListOpened(): void {
		if ($this->bodyListOpened === true) {
			return;
		}

		$this->ensureBodyIsOpened();
		$this->bodyListOpened = true;
		$this->htmlBody .= $this->listBegin;
	}//end ensureBodyListOpened()

	/**
	 * Close the open list.
	 *
	 * @return void
	 */
	protected function ensureBodyListClosed(): void {
		if ($this->bodyListOpened === false) {
			return;
		}

		$this->bodyListOpened = false;
		$this->htmlBody .= $this->listEnd;
	}//end ensureBodyListClosed()

	/**
	 * Add two buttons side by side.
	 *
	 * @param string $textLeft The left text.
	 * @param string $urlLeft The left URL.
	 * @param string $textRight The right text.
	 * @param string $urlRight The right URL.
	 * @param string $plainTextLeft The plain-text left label; '' uses $textLeft.
	 * @param string $plainTextRight The plain-text right label; '' uses $textRight.
	 *
	 * @return void
	 */
	public function addBodyButtonGroup(
		string $textLeft,
		string $urlLeft,
		string $textRight,
		string $urlRight,
		string $plainTextLeft = '',
		string $plainTextRight = '',
	): void {
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

		$color = $this->themingDefaults->getDefaultColorPrimary();
		$textColor = $this->themingDefaults->getDefaultTextColorPrimary();

		$this->htmlBody .= vsprintf(
			$this->buttonGroup,
			[$color, $color, $urlLeft, $color, $textColor, $textColor, $textLeft, $urlRight, $textRight]
		);
		$this->plainBody .= PHP_EOL . $plainTextLeft . ': ' . $urlLeft . PHP_EOL;
		$this->plainBody .= $plainTextRight . ': ' . $urlRight . PHP_EOL . PHP_EOL;
	}//end addBodyButtonGroup()

	/**
	 * Add a button.
	 *
	 * @param string $text The text.
	 * @param string $url The URL.
	 * @param string|false $plainText The plain-text label; '' uses $text, false adds none.
	 *
	 * @return void
	 */
	public function addBodyButton(string $text, string $url, $plainText = ''): void {
		if ($this->footerAdded === true) {
			return;
		}

		$this->ensureBodyIsOpened();
		$this->ensureBodyListClosed();

		if ($plainText === '') {
			$plainText = $text;
			$text = htmlspecialchars($text);
		}

		$color = $this->themingDefaults->getDefaultColorPrimary();
		$textColor = $this->themingDefaults->getDefaultTextColorPrimary();
		$this->htmlBody .= vsprintf($this->button, [$color, $color, $url, $color, $textColor, $textColor, $text]);

		if ($plainText !== false) {
			$this->plainBody .= $plainText . ': ';
		}

		$this->plainBody .= $url . PHP_EOL;
	}//end addBodyButton()

	/**
	 * Close the open body.
	 *
	 * @return void
	 */
	protected function ensureBodyIsClosed(): void {
		if ($this->bodyOpened === false) {
			return;
		}

		$this->ensureBodyListClosed();

		$this->htmlBody .= $this->bodyEnd;
		$this->bodyOpened = false;
	}//end ensureBodyIsClosed()

	/**
	 * Add the footer once.
	 *
	 * @param string $text The footer text; '' uses the instance name, slogan and the no-reply line.
	 * @param string|null $lang The language.
	 *
	 * @return void
	 */
	public function addFooter(string $text = '', ?string $lang = null): void {
		if ($text === '') {
			$l10n = $this->l10nFactory->get('lib', $lang);
			$slogan = $this->themingDefaults->getSlogan($lang);
			if ($slogan !== '') {
				$slogan = ' - ' . $slogan;
			}

			$text = $this->themingDefaults->getName() . $slogan . '<br>' . $l10n->t('This is an automatically sent email, please do not reply.');
		}

		if ($this->footerAdded === true) {
			return;
		}

		$this->footerAdded = true;

		$this->ensureBodyIsClosed();

		$this->htmlBody .= vsprintf($this->footer, [$text]);
		$this->htmlBody .= $this->tail;
		$this->plainBody .= PHP_EOL . '-- ' . PHP_EOL;
		$this->plainBody .= str_replace('<br>', PHP_EOL, $text);
	}//end addFooter()

	/**
	 * The subject.
	 *
	 * @return string
	 */
	public function renderSubject(): string {
		return $this->subject;
	}//end renderSubject()

	/**
	 * The HTML part, closed when no footer was added.
	 *
	 * @return string
	 */
	public function renderHtml(): string {
		$this->closeWithoutFooter();
		return $this->htmlBody;
	}//end renderHtml()

	/**
	 * The plain-text part, with the HTML part closed when no footer was added.
	 *
	 * @return string
	 */
	public function renderText(): string {
		$this->closeWithoutFooter();
		return $this->plainBody;
	}//end renderText()

	/**
	 * Close body and document when no footer was added.
	 *
	 * @return void
	 */
	private function closeWithoutFooter(): void {
		if ($this->footerAdded === true) {
			return;
		}

		$this->footerAdded = true;
		$this->ensureBodyIsClosed();
		$this->htmlBody .= $this->tail;
	}//end closeWithoutFooter()
}//end class
