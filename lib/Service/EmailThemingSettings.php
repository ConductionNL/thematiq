<?php

/**
 * Thematiq email theming settings: the read and save answers of the settings panel.
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

use OCA\Thematiq\Service\Exception\ConfigReadOnlyException;
use OCA\Thematiq\Service\Exception\FooterValidationException;
use OCA\Thematiq\Service\Exception\ForeignMailTemplateClassException;

/**
 * Reads and saves the email template toggle and the compliance footer, and
 * answers each with the HTTP status and body the settings panel expects.
 *
 * The footer is saved first (app config, always writable) and reported on
 * its own, so it is kept even when the toggle write fails on a read-only
 * config.php or a foreign `mail_template_class`.
 *
 * @spec openspec/specs/email-theming/spec.md
 */
class EmailThemingSettings {

	/**
	 * Constructor.
	 *
	 * @param EmailThemingService $emailThemingService The email theming service.
	 */
	public function __construct(
		private readonly EmailThemingService $emailThemingService,
	) {
	}//end __construct()

	/**
	 * The toggle state, the footer config and the manual occ commands.
	 *
	 * @return array<string, mixed> The body.
	 *
	 * @spec openspec/specs/email-theming/spec.md
	 */
	public function read(): array {
		return [
			'state' => $this->emailThemingService->getState(),
			'footer' => $this->emailThemingService->getFooterConfig(),
			'occEnable' => EmailThemingService::OCC_ENABLE_COMMAND,
			'occDisable' => EmailThemingService::OCC_DISABLE_COMMAND,
		];
	}//end read()

	/**
	 * Save the footer, then switch the template on or off.
	 *
	 * @param bool $enabled Whether the branded template should be enabled.
	 * @param array{orgName: string, accessibilityUrl: string, privacyUrl: string} $footer The footer config.
	 *
	 * @return array{status: 200|409|422, body: array<string, mixed>} The answer: 200, or a 409/422 error.
	 *
	 * @spec openspec/specs/email-theming/spec.md
	 */
	public function save(bool $enabled, array $footer): array {
		try {
			$this->emailThemingService->setFooterConfig($footer['orgName'], $footer['accessibilityUrl'], $footer['privacyUrl']);
		} catch (FooterValidationException $e) {
			return [
				'status' => 422,
				'body' => ['error' => 'invalid_footer', 'field' => $e->getField(), 'message' => $e->getMessage()],
			];
		}

		$refused = $this->toggle(enabled: $enabled);
		if ($refused !== null) {
			return ['status' => 409, 'body' => $refused + ['footer' => $this->emailThemingService->getFooterConfig()]];
		}

		return [
			'status' => 200,
			'body' => [
				'status' => 'ok',
				'state' => $this->emailThemingService->getState(),
				'footer' => $this->emailThemingService->getFooterConfig(),
			],
		];
	}//end save()

	/**
	 * Switch the template on or off.
	 *
	 * @param bool $enabled Whether the branded template should be enabled.
	 *
	 * @return array<string, string>|null The error body when the system config cannot be written, or null.
	 *
	 * @spec openspec/specs/email-theming/spec.md
	 */
	private function toggle(bool $enabled): ?array {
		try {
			if ($enabled === true) {
				$this->emailThemingService->enable();
			}

			if ($enabled === false) {
				$this->emailThemingService->disable();
			}
		} catch (ConfigReadOnlyException $e) {
			return ['error' => 'config_read_only', 'occEnable' => $e->getOccEnableCommand(), 'occDisable' => $e->getOccDisableCommand()];
		} catch (ForeignMailTemplateClassException $e) {
			return ['error' => 'foreign_mail_template_class', 'class' => $e->getForeignClass()];
		}

		return null;
	}//end toggle()
}//end class
