<?php

/**
 * NL Design Email Theming Service.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Service
 * @package   OCA\Thematiq
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 *
 * @spec openspec/specs/email-theming/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCA\Thematiq\AppInfo\Application;
use OCA\Thematiq\Mail\NLDesignEMailTemplate;
use OCA\Thematiq\Service\RuntimeFile\RuntimeFileLocator;
use OCA\Thematiq\Service\Exception\ConfigReadOnlyException;
use OCA\Thematiq\Service\Exception\FooterValidationException;
use OCA\Thematiq\Service\Exception\ForeignMailTemplateClassException;
use OCP\HintException;
use OCP\IConfig;
use OCP\IURLGenerator;

/**
 * Reads/writes the email-theming compliance footer, reports and toggles the
 * `mail_template_class` system config, and resolves the active token set's
 * email theme (primary color, primary text color, absolute logo URL).
 *
 * @spec openspec/specs/email-theming/spec.md
 */
class EmailThemingService {

	/**
	 * The system config key the server's Mailer reads to select the template class.
	 *
	 * @var string
	 */
	public const MAIL_TEMPLATE_CLASS_KEY = 'mail_template_class';

	/**
	 * The occ command to enable the branded template manually.
	 *
	 * Handed to the admin verbatim when config.php cannot be written — it is
	 * rendered into the admin panel as a copy-paste command
	 * (templates/settings/admin.php) and returned by the settings endpoint.
	 *
	 * THE NAMESPACE HERE IS `OCA\Thematiq`, NOT `OCA\NLDesign`. This string
	 * survived the namespace rename as a literal and kept naming the removed
	 * class: an admin on a read-only config.php who ran it wrote a class that
	 * does not exist, and `OC\Mail\Mailer::createEMailTemplate()` guards the
	 * stored value with `class_exists()` and silently falls back to the stock
	 * template. So the command reported success, the panel showed the value
	 * set, and branded email never came back — with nothing in any log. It must
	 * stay in sync with what `enable()` writes below; both now derive from
	 * {@see NLDesignEMailTemplate}. (The CLASS NAME `NLDesignEMailTemplate` is
	 * unchanged and correct — it names the NL Design System, which did not move;
	 * only the namespace did.)
	 *
	 * @var string
	 */
	public const OCC_ENABLE_COMMAND = 'occ config:system:set mail_template_class --value "OCA\\Thematiq\\Mail\\NLDesignEMailTemplate"';

	/**
	 * The occ command to disable the branded template manually.
	 *
	 * @var string
	 */
	public const OCC_DISABLE_COMMAND = 'occ config:system:delete mail_template_class';

	/**
	 * The appconfig key for the compliance footer organization name.
	 *
	 * @var string
	 */
	private const FOOTER_ORG_NAME_KEY = 'email_footer_org_name';

	/**
	 * The appconfig key for the compliance footer accessibility statement URL.
	 *
	 * @var string
	 */
	private const FOOTER_ACCESSIBILITY_URL_KEY = 'email_footer_accessibility_url';

	/**
	 * The appconfig key for the compliance footer privacy statement URL.
	 *
	 * @var string
	 */
	private const FOOTER_PRIVACY_URL_KEY = 'email_footer_privacy_url';

	/**
	 * The maximum stored length for a footer value.
	 *
	 * @var int
	 */
	private const MAX_FOOTER_VALUE_LENGTH = 2048;

	/**
	 * The application configuration service.
	 *
	 * @var IConfig
	 */
	private IConfig $config;

	/**
	 * The token set service, used to read shipped theming metadata.
	 *
	 * @var TokenSetService
	 */
	private TokenSetService $tokenSetService;

	/**
	 * The token set preview service, used as the resolved-color fallback.
	 *
	 * @var TokenSetPreviewService
	 */
	private TokenSetPreviewService $previewService;

	/**
	 * The URL generator, used to build the absolute logo URL.
	 *
	 * @var IURLGenerator
	 */
	private IURLGenerator $urlGenerator;

	/**
	 * Constructor.
	 *
	 * @param IConfig $config The config service.
	 * @param TokenSetService $tokenSetService The token set service.
	 * @param TokenSetPreviewService $previewService The token set preview service.
	 * @param IURLGenerator $urlGenerator The URL generator.
	 * @param RuntimeFileLocator|null $files Finds an uploaded set's logo, which lives in app data.
	 */
	public function __construct(
		IConfig $config,
		TokenSetService $tokenSetService,
		TokenSetPreviewService $previewService,
		IURLGenerator $urlGenerator,
		private readonly ?RuntimeFileLocator $files = null,
	) {
		$this->config = $config;
		$this->tokenSetService = $tokenSetService;
		$this->previewService = $previewService;
		$this->urlGenerator = $urlGenerator;
	}//end __construct()

	/**
	 * Get the configured compliance footer values.
	 *
	 * @return array{orgName: string, accessibilityUrl: string, privacyUrl: string} The footer config.
	 *
	 * @spec openspec/specs/email-theming/spec.md
	 */
	public function getFooterConfig(): array {
		return [
			'orgName' => $this->config->getAppValue(Application::APP_ID, self::FOOTER_ORG_NAME_KEY, ''),
			'accessibilityUrl' => $this->config->getAppValue(Application::APP_ID, self::FOOTER_ACCESSIBILITY_URL_KEY, ''),
			'privacyUrl' => $this->config->getAppValue(Application::APP_ID, self::FOOTER_PRIVACY_URL_KEY, ''),
		];
	}//end getFooterConfig()

	/**
	 * Validate a candidate footer config without persisting it.
	 *
	 * Extracted from {@see setFooterConfig()} so a validate-only caller (the
	 * `config-portability` bundle import's phase 1) can reuse the EXACT same
	 * rule set instead of re-implementing it — a bundle import must reject
	 * on the same conditions setFooterConfig() would, with zero writes.
	 *
	 * @param string $orgName The organization name.
	 * @param string $accessibilityUrl The toegankelijkheidsverklaring URL.
	 * @param string $privacyUrl The privacy statement URL.
	 *
	 * @return void
	 *
	 * @throws FooterValidationException When a value fails validation.
	 *
	 * @spec openspec/specs/email-theming/spec.md
	 * @spec openspec/specs/config-portability/spec.md
	 */
	public function validateFooterConfig(string $orgName, string $accessibilityUrl, string $privacyUrl): void {
		if (strlen($orgName) > self::MAX_FOOTER_VALUE_LENGTH) {
			throw new FooterValidationException(field: 'orgName', message: 'Organization name exceeds the maximum length.');
		}

		$this->validateUrl(field: 'accessibilityUrl', url: $accessibilityUrl);
		$this->validateUrl(field: 'privacyUrl', url: $privacyUrl);
	}//end validateFooterConfig()

	/**
	 * Set the compliance footer values.
	 *
	 * Empty values are accepted (omit that line). Non-empty URL values MUST
	 * use the `http://` or `https://` scheme and stay within the maximum
	 * stored length; anything else (including `javascript:` and non-URL
	 * junk) throws before anything is persisted.
	 *
	 * @param string $orgName The organization name.
	 * @param string $accessibilityUrl The toegankelijkheidsverklaring URL.
	 * @param string $privacyUrl The privacy statement URL.
	 *
	 * @return array{orgName: string, accessibilityUrl: string, privacyUrl: string} The persisted footer config.
	 *
	 * @throws FooterValidationException When a value fails validation.
	 *
	 * @spec openspec/specs/email-theming/spec.md
	 */
	public function setFooterConfig(string $orgName, string $accessibilityUrl, string $privacyUrl): array {
		$this->validateFooterConfig(orgName: $orgName, accessibilityUrl: $accessibilityUrl, privacyUrl: $privacyUrl);

		$this->config->setAppValue(Application::APP_ID, self::FOOTER_ORG_NAME_KEY, $orgName);
		$this->config->setAppValue(Application::APP_ID, self::FOOTER_ACCESSIBILITY_URL_KEY, $accessibilityUrl);
		$this->config->setAppValue(Application::APP_ID, self::FOOTER_PRIVACY_URL_KEY, $privacyUrl);

		return $this->getFooterConfig();
	}//end setFooterConfig()

	/**
	 * Validate a footer URL value. Empty values are always valid (omitted).
	 *
	 * @param string $field The field name, for the exception.
	 * @param string $url The URL value.
	 *
	 * @return void
	 *
	 * @throws FooterValidationException When the value is non-empty and invalid.
	 *
	 * @spec openspec/specs/email-theming/spec.md
	 */
	private function validateUrl(string $field, string $url): void {
		if ($url === '') {
			return;
		}

		if (strlen($url) > self::MAX_FOOTER_VALUE_LENGTH) {
			throw new FooterValidationException(field: $field, message: 'URL exceeds the maximum length.');
		}

		if (preg_match('#^https?://#i', $url) !== 1) {
			throw new FooterValidationException(field: $field, message: 'URL must use the http:// or https:// scheme.');
		}
	}//end validateUrl()

	/**
	 * Resolve the active token set's email theme.
	 *
	 * Reads `theming.primary_color` / `theming.logo` from the token set's
	 * `token-sets.json` metadata first; falls back to
	 * `TokenSetPreviewService::getResolvedColors()` for a set without
	 * explicit theming metadata. Returns null when the active set is
	 * `nextcloud` (stock, unthemed) or unresolvable.
	 *
	 * @return array{primaryColor: string, primaryTextColor: string, logoUrl: ?string}|null
	 *                                                                                      The resolved theme, or null when there is no active theme.
	 *
	 * @spec openspec/specs/email-theming/spec.md
	 */
	public function getActiveEmailTheme(): ?array {
		$tokenSetId = $this->config->getAppValue(Application::APP_ID, 'token_set', 'nextcloud');
		if ($tokenSetId === '' || $tokenSetId === 'nextcloud') {
			return null;
		}

		$entry = $this->findTokenSetEntry(tokenSetId: $tokenSetId);
		if ($entry === null) {
			return null;
		}

		$theming = [];
		if (is_array($entry['theming'] ?? null) === true) {
			$theming = $entry['theming'];
		}

		$colors = $this->resolveThemeColors(theming: $theming, tokenSetId: $tokenSetId);
		if ($colors === null) {
			return null;
		}

		return [
			'primaryColor' => $colors['primaryColor'],
			'primaryTextColor' => $colors['primaryTextColor'],
			'logoUrl' => $this->resolveLogoUrl(logoPath: ($theming['logo'] ?? null)),
		];
	}//end getActiveEmailTheme()

	/**
	 * Resolve the primary color / primary text color for a token set,
	 * falling back to `TokenSetPreviewService::getResolvedColors()` for any
	 * value not present in the manifest `theming` metadata.
	 *
	 * @param array<string, mixed> $theming The manifest `theming` block (possibly empty).
	 * @param string $tokenSetId The active token set identifier.
	 *
	 * @return array{primaryColor: string, primaryTextColor: string}|null
	 *                                                                    The resolved colors, or null when no primary color can be resolved at all.
	 *
	 * @spec openspec/specs/email-theming/spec.md
	 */
	private function resolveThemeColors(array $theming, string $tokenSetId): ?array {
		$primaryColor = $theming['primary_color'] ?? null;
		$primaryTextColor = $theming['primary_text_color'] ?? null;

		if ($primaryColor === null || $primaryTextColor === null) {
			$resolved = $this->previewService->getResolvedColors(tokenSetId: $tokenSetId);
			$primaryColor = $primaryColor ?? ($resolved['--color-primary'] ?? null);
			$primaryTextColor = $primaryTextColor ?? ($resolved['--color-primary-text'] ?? null);
		}

		if ($primaryColor === null) {
			return null;
		}

		return [
			'primaryColor' => $primaryColor,
			'primaryTextColor' => ($primaryTextColor ?? '#ffffff'),
		];
	}//end resolveThemeColors()

	/**
	 * Find a token set's metadata entry by id.
	 *
	 * @param string $tokenSetId The token set identifier.
	 *
	 * @return array<string, mixed>|null The entry, or null when not found.
	 *
	 * @spec openspec/specs/email-theming/spec.md
	 */
	private function findTokenSetEntry(string $tokenSetId): ?array {
		foreach ($this->tokenSetService->getAvailableTokenSets() as $tokenSet) {
			if ($tokenSet['id'] === $tokenSetId) {
				return $tokenSet;
			}
		}

		return null;
	}//end findTokenSetEntry()

	/**
	 * Resolve a token set's logo path to an absolute URL.
	 *
	 * @param string|null $logoPath The logo path from token-sets.json (e.g. 'img/logos/amsterdam.svg').
	 *
	 * @return string|null The absolute URL, or null when unresolvable.
	 *
	 * @spec openspec/specs/email-theming/spec.md
	 */
	private function resolveLogoUrl(?string $logoPath): ?string {
		if ($logoPath === null || $logoPath === '') {
			return null;
		}

		// An uploaded or captured logo lives in app data and is served by the
		// runtime file route; a mail client needs that URL absolute.
		if ($this->files !== null && $this->files->isShipped(name: $logoPath) === false) {
			$url = $this->files->url(name: $logoPath);
			if ($url === null) {
				return null;
			}

			return $this->urlGenerator->getAbsoluteURL($url);
		}

		// IURLGenerator::imagePath() resolves relative to the app's img/
		// directory itself, so a stored path already prefixed with 'img/'
		// must have that prefix stripped before being passed in.
		$relative = $logoPath;
		if (str_starts_with($relative, 'img/') === true) {
			$relative = substr($relative, 4);
		}

		try {
			return $this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath(Application::APP_ID, $relative));
		} catch (\Throwable $e) {
			return null;
		}
	}//end resolveLogoUrl()

	/**
	 * Whether the nldesign branded template is the configured mail template.
	 *
	 * @return bool True when `mail_template_class` names the nldesign template.
	 *
	 * @spec openspec/specs/email-theming/spec.md
	 */
	public function isEnabled(): bool {
		return $this->config->getSystemValue(self::MAIL_TEMPLATE_CLASS_KEY, '') === NLDesignEMailTemplate::class;
	}//end isEnabled()

	/**
	 * Get the current toggle state.
	 *
	 * @return array{state: string, configReadOnly: bool, foreignClass: ?string} The state:
	 *                                                                           `state` is one of `disabled`/`enabled`/`foreign`; `foreignClass` is
	 *                                                                           set only when `state` is `foreign`.
	 *
	 * @spec openspec/specs/email-theming/spec.md
	 */
	public function getState(): array {
		$configured = (string)$this->config->getSystemValue(self::MAIL_TEMPLATE_CLASS_KEY, '');
		$configReadOnly = $this->config->getSystemValueBool('config_is_read_only', false);

		$state = 'disabled';
		$foreignClass = null;
		if ($configured === NLDesignEMailTemplate::class) {
			$state = 'enabled';
		} elseif ($configured !== '') {
			$state = 'foreign';
			$foreignClass = $configured;
		}

		return [
			'state' => $state,
			'configReadOnly' => $configReadOnly,
			'foreignClass' => $foreignClass,
		];
	}//end getState()

	/**
	 * Enable the nldesign branded template.
	 *
	 * @return void
	 *
	 * @throws ForeignMailTemplateClassException When a different class is already configured.
	 * @throws ConfigReadOnlyException When config.php cannot be written.
	 *
	 * @spec openspec/specs/email-theming/spec.md
	 */
	public function enable(): void {
		$state = $this->getState();
		if ($state['state'] === 'foreign') {
			throw new ForeignMailTemplateClassException(foreignClass: (string)$state['foreignClass']);
		}

		if ($state['configReadOnly'] === true) {
			throw new ConfigReadOnlyException(occEnableCommand: self::OCC_ENABLE_COMMAND, occDisableCommand: self::OCC_DISABLE_COMMAND);
		}

		try {
			$this->config->setSystemValue(self::MAIL_TEMPLATE_CLASS_KEY, NLDesignEMailTemplate::class);
		} catch (HintException $e) {
			// The config_is_read_only flag can miss a filesystem-level
			// read-only config.php (e.g. chmod 444) — the write itself
			// throws HintException in that case.
			throw new ConfigReadOnlyException(occEnableCommand: self::OCC_ENABLE_COMMAND, occDisableCommand: self::OCC_DISABLE_COMMAND, previous: $e);
		}
	}//end enable()

	/**
	 * Disable the nldesign branded template.
	 *
	 * A foreign class is left untouched (nothing to disable on nldesign's
	 * behalf); config.php read-only still blocks the delete when nldesign's
	 * own value is configured.
	 *
	 * @return void
	 *
	 * @throws ConfigReadOnlyException When config.php cannot be written.
	 *
	 * @spec openspec/specs/email-theming/spec.md
	 */
	public function disable(): void {
		$state = $this->getState();
		if ($state['state'] === 'foreign' || $state['state'] === 'disabled') {
			return;
		}

		if ($state['configReadOnly'] === true) {
			throw new ConfigReadOnlyException(occEnableCommand: self::OCC_ENABLE_COMMAND, occDisableCommand: self::OCC_DISABLE_COMMAND);
		}

		try {
			$this->config->deleteSystemValue(self::MAIL_TEMPLATE_CLASS_KEY);
		} catch (HintException $e) {
			throw new ConfigReadOnlyException(occEnableCommand: self::OCC_ENABLE_COMMAND, occDisableCommand: self::OCC_DISABLE_COMMAND, previous: $e);
		}
	}//end disable()
}//end class
