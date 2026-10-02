<?php

/**
 * Static-analysis stub for the server's `OC\Mail\EMailTemplate`.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * lib/Mail/NLDesignEMailTemplate.php extends the server's stock mail template,
 * which is the platform's sanctioned `mail_template_class` hook
 * (`OC\Mail\Mailer::makeTemplate()`). The class lives in
 * `lib/private/Mail/EMailTemplate.php`, outside the `nextcloud/ocp` package the
 * analysers see. Without this stub phpstan had to exclude the whole file
 * (so nothing in it was checked) and psalm suppressed UndefinedClass for it,
 * then reported its private helpers as unused and its callers as
 * MissingDependency, because it could not see the parent.
 *
 * Wired into analysis ONLY (phpstan.neon `scanDirectories`, psalm.xml
 * `<stubs>`) and never autoloaded at runtime: composer maps only
 * `OCA\Thematiq\` to lib/, so the server's real class always executes.
 *
 * Members mirror lib/private/Mail/EMailTemplate.php on server master; the
 * HEREDOC markup properties are declared with empty defaults because only
 * their type matters to the analysers. Keep in sync if the server changes it.
 */

declare(strict_types=1);

// phpcs:disable

namespace OC\Mail;

use OCP\Defaults;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\Mail\IEMailTemplate;

class EMailTemplate implements IEMailTemplate
{
    protected string $subject = '';
    protected string $htmlBody = '';
    protected string $plainBody = '';
    protected bool $headerAdded = false;
    protected bool $bodyOpened = false;
    protected bool $bodyListOpened = false;
    protected bool $footerAdded = false;
    /** @var array<string, string> */
    protected array $inlineImages = [];
    protected string $head = '';
    protected string $tail = '';
    protected string $header = '';
    protected string $heading = '';
    protected string $bodyBegin = '';
    protected string $bodyText = '';
    protected string $listBegin = '';
    protected string $listItem = '';
    protected string $listEnd = '';
    protected string $buttonGroup = '';
    protected string $button = '';
    protected string $bodyEnd = '';
    protected string $footer = '';

    /**
     * @param array<string, mixed> $data
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
    }

    public function setSubject(string $subject): void
    {
    }

    public function addHeader(): void
    {
    }

    /**
     * @return array<string, string>
     */
    public function getInlineImages(): array
    {
        return [];
    }

    /**
     * @param string|bool $plainTitle
     */
    public function addHeading(string $title, $plainTitle = ''): void
    {
    }

    protected function ensureBodyIsOpened(): void
    {
    }

    /**
     * @param string|bool $plainText
     */
    public function addBodyText(string $text, $plainText = ''): void
    {
    }

    /**
     * @param string|bool $plainText
     * @param string|bool $plainMetaInfo
     * @param int         $plainIndent
     */
    public function addBodyListItem(
        string $text,
        string $metaInfo = '',
        string $icon = '',
        $plainText = '',
        $plainMetaInfo = '',
        $plainIndent = 0,
    ): void {
    }

    protected function ensureBodyListOpened(): void
    {
    }

    protected function ensureBodyListClosed(): void
    {
    }

    public function addBodyButtonGroup(
        string $textLeft,
        string $urlLeft,
        string $textRight,
        string $urlRight,
        string $plainTextLeft = '',
        string $plainTextRight = '',
    ): void {
    }

    /**
     * @param string|false $plainText
     */
    public function addBodyButton(string $text, string $url, $plainText = ''): void
    {
    }

    protected function ensureBodyIsClosed(): void
    {
    }

    public function addFooter(string $text = '', ?string $lang = null): void
    {
    }

    public function renderSubject(): string
    {
        return '';
    }

    public function renderHtml(): string
    {
        return '';
    }

    public function renderText(): string
    {
        return '';
    }
}
