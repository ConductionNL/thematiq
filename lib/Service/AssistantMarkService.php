<?php

/**
 * Thematiq approved mark for the AI assistant.
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
 * @spec openspec/specs/assistant-approved-mark/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCA\Thematiq\AppInfo\Application;
use OCA\Thematiq\Capabilities;
use OCA\Thematiq\Service\Exception\AssistantMarkException;
use OCP\IConfig;
use OCP\IL10N;

/**
 * Whether the organisation marks the fleet's AI assistant as the one it
 * sanctioned, with which name and logo. Off by default: turning it on is a
 * statement by the organisation, not a default of the software.
 *
 * The organisation name defaults to the email footer organisation name and
 * the logo to the active house style logo (`logos.default` of the theming
 * capability). The mark is informative, not a security control.
 *
 * @spec openspec/specs/assistant-approved-mark/spec.md
 */
class AssistantMarkService {

	/**
	 * App config key: whether the mark is on (`1`) or off.
	 *
	 * @var string
	 */
	public const KEY_ENABLED = 'assistant_mark_enabled';

	/**
	 * App config key: the organisation name, empty for the email footer name.
	 *
	 * @var string
	 */
	public const KEY_ORGANISATION = 'assistant_mark_organisation';

	/**
	 * App config key: the logo URL, empty for the house style logo.
	 *
	 * @var string
	 */
	public const KEY_LOGO = 'assistant_mark_logo';

	/**
	 * Error code: turning the mark on without an organisation name.
	 *
	 * @var int
	 */
	public const ERROR_NAME = 1;

	/**
	 * Error code: a logo that is not an https or local address.
	 *
	 * @var int
	 */
	public const ERROR_LOGO = 2;

	/**
	 * Constructor.
	 *
	 * @param IConfig             $config       App config.
	 * @param EmailThemingService $emailTheming The email footer, for the default organisation name.
	 * @param Capabilities        $capabilities The theming capability, for the default logo.
	 */
	public function __construct(
		private readonly IConfig $config,
		private readonly EmailThemingService $emailTheming,
		private readonly Capabilities $capabilities,
	) {
	}//end __construct()

	/**
	 * The settings as the administrator edits them.
	 *
	 * @return array{enabled: bool, organisation: string, organisationDefault: string, logo: string, logoDefault: string|null} The settings.
	 *
	 * @spec openspec/specs/assistant-approved-mark/spec.md
	 */
	public function getSettings(): array {
		return [
			'enabled' => ($this->get(key: self::KEY_ENABLED) === '1'),
			'organisation' => $this->get(key: self::KEY_ORGANISATION),
			'organisationDefault' => $this->defaultOrganisation(),
			'logo' => $this->get(key: self::KEY_LOGO),
			'logoDefault' => $this->defaultLogo(),
		];
	}//end getSettings()

	/**
	 * Save the settings.
	 *
	 * @param bool   $enabled      Whether the mark is on.
	 * @param string $organisation The organisation name, empty for the email footer name.
	 * @param string $logo         The logo URL, empty for the house style logo.
	 *
	 * @return array<string, mixed> The saved settings.
	 *
	 * @throws AssistantMarkException When turning on without a name, or with a logo that is not an https or local address.
	 *
	 * @spec openspec/specs/assistant-approved-mark/spec.md
	 */
	public function save(bool $enabled, string $organisation, string $logo): array {
		$organisation = trim($organisation);
		$logo = trim($logo);
		if ($logo !== '' && $this->isUsableLogo(url: $logo) === false) {
			throw new AssistantMarkException(message: 'The logo is not an https address or a path on this server.', code: self::ERROR_LOGO);
		}

		if ($enabled === true && $organisation === '' && $this->defaultOrganisation() === '') {
			throw new AssistantMarkException(message: 'The approved mark needs an organisation name.', code: self::ERROR_NAME);
		}

		$this->applyBundle(value: ['enabled' => $enabled, 'organisation' => $organisation, 'logo' => $logo]);

		return $this->getSettings();
	}//end save()

	/**
	 * The mark as a signed-in user's assistant panel reads it.
	 *
	 * @param IL10N $l10n The requesting user's translations.
	 *
	 * @return array<string, mixed> `{enabled: false}`, or `{enabled, label, organisation, logo: {url, alt}|null}`.
	 *
	 * @spec openspec/specs/assistant-approved-mark/spec.md
	 */
	public function forUser(IL10N $l10n): array {
		$organisation = $this->effectiveOrganisation();
		if ($this->get(key: self::KEY_ENABLED) !== '1' || $organisation === '') {
			return ['enabled' => false];
		}

		$logo = null;
		$url = $this->get(key: self::KEY_LOGO);
		if ($url === '') {
			$url = (string)$this->defaultLogo();
		}

		if ($url !== '') {
			$logo = ['url' => $url, 'alt' => $l10n->t('%s logo', [$organisation])];
		}

		return [
			'enabled' => true,
			'label' => $l10n->t('Approved by %s', [$organisation]),
			'organisation' => $organisation,
			'logo' => $logo,
		];
	}//end forUser()

	/**
	 * The three values for the configuration bundle.
	 *
	 * @return array{enabled: bool, organisation: string, logo: string} The values as stored.
	 *
	 * @spec openspec/specs/assistant-approved-mark/spec.md
	 */
	public function exportBundle(): array {
		return [
			'enabled' => ($this->get(key: self::KEY_ENABLED) === '1'),
			'organisation' => $this->get(key: self::KEY_ORGANISATION),
			'logo' => $this->get(key: self::KEY_LOGO),
		];
	}//end exportBundle()

	/**
	 * Validate the bundle's `assistantMark` section. Absent (an older bundle) leaves the mark alone.
	 *
	 * @param mixed $section The section, or null when absent.
	 *
	 * @return array{errors: array<int, string>, value: array{enabled: bool, organisation: string, logo: string}|null} The errors and the values to apply.
	 *
	 * @spec openspec/specs/assistant-approved-mark/spec.md
	 */
	public function validateBundle(mixed $section): array {
		if ($section === null) {
			return ['errors' => [], 'value' => null];
		}

		if (is_array($section) === false || is_bool($section['enabled'] ?? false) === false
			|| is_string($section['organisation'] ?? '') === false || is_string($section['logo'] ?? '') === false
		) {
			return ['errors' => ['"assistantMark" must be an object with enabled, organisation and logo.'], 'value' => null];
		}

		$value = [
			'enabled' => (($section['enabled'] ?? false) === true),
			'organisation' => trim((string)($section['organisation'] ?? '')),
			'logo' => trim((string)($section['logo'] ?? '')),
		];
		if ($value['logo'] !== '' && $this->isUsableLogo(url: $value['logo']) === false) {
			return ['errors' => ['"assistantMark.logo" must be an https address or a path on this server.'], 'value' => null];
		}

		return ['errors' => [], 'value' => $value];
	}//end validateBundle()

	/**
	 * Apply a validated `assistantMark` section.
	 *
	 * @param array{enabled: bool, organisation: string, logo: string} $value The values.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/assistant-approved-mark/spec.md
	 */
	public function applyBundle(array $value): void {
		$enabled = '0';
		if ($value['enabled'] === true) {
			$enabled = '1';
		}

		$this->config->setAppValue(Application::APP_ID, self::KEY_ORGANISATION, $value['organisation']);
		$this->config->setAppValue(Application::APP_ID, self::KEY_LOGO, $value['logo']);
		$this->config->setAppValue(Application::APP_ID, self::KEY_ENABLED, $enabled);
	}//end applyBundle()

	/**
	 * The organisation name the mark shows: the own name, else the email footer name.
	 *
	 * @return string The name, empty when neither is set.
	 *
	 * @spec openspec/specs/assistant-approved-mark/spec.md
	 */
	private function effectiveOrganisation(): string {
		$own = $this->get(key: self::KEY_ORGANISATION);
		if ($own !== '') {
			return $own;
		}

		return $this->defaultOrganisation();
	}//end effectiveOrganisation()

	/**
	 * The email footer organisation name.
	 *
	 * @return string The name.
	 *
	 * @spec openspec/specs/assistant-approved-mark/spec.md
	 */
	private function defaultOrganisation(): string {
		return trim((string)($this->emailTheming->getFooterConfig()['orgName'] ?? ''));
	}//end defaultOrganisation()

	/**
	 * The active house style logo.
	 *
	 * @return string|null The URL, or null when the set has none.
	 *
	 * @spec openspec/specs/assistant-approved-mark/spec.md
	 */
	private function defaultLogo(): ?string {
		$logos = ($this->capabilities->getCapabilities()['nldesign']['logos'] ?? []);
		if (is_array($logos) === false || is_string($logos['default'] ?? null) === false) {
			return null;
		}

		return $logos['default'];
	}//end defaultLogo()

	/**
	 * Whether a logo address is an https URL or a path on this server.
	 *
	 * @param string $url The address.
	 *
	 * @return bool True when usable.
	 *
	 * @spec openspec/specs/assistant-approved-mark/spec.md
	 */
	private function isUsableLogo(string $url): bool {
		if (str_starts_with($url, '/') === true && str_starts_with($url, '//') === false) {
			return true;
		}

		return (str_starts_with(strtolower($url), 'https://') === true && filter_var($url, FILTER_VALIDATE_URL) !== false);
	}//end isUsableLogo()

	/**
	 * Read an app config value.
	 *
	 * @param string $key The key.
	 *
	 * @return string The trimmed value.
	 *
	 * @spec openspec/specs/assistant-approved-mark/spec.md
	 */
	private function get(string $key): string {
		return trim((string)$this->config->getAppValue(Application::APP_ID, $key, ''));
	}//end get()
}//end class
