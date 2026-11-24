<?php

/**
 * Contributes NL Design's theme type to OpenRegister's federated-config engine.
 *
 * Registered only when OpenRegister's shareable-config event exists (a
 * class_exists guard in Application.php), so an instance without OpenRegister —
 * or with an older one — still boots. The same guarded-contribution pattern the
 * app uses for OpenRegister's integration registry.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @category Listener
 * @package  OCA\Thematiq\Listener
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/specs/federated-config-sharing/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Listener;

use OCA\OpenRegister\Service\Config\RegisterShareableConfigTypesEvent;
use OCA\Thematiq\Service\Config\NlDesignThemeShareableConfigType;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

/**
 * Registers the NL Design theme shareable-config type.
 *
 * @template-implements IEventListener<RegisterShareableConfigTypesEvent>
 *
 * @spec openspec/specs/federated-config-sharing/spec.md
 */
class ShareableConfigTypeListener implements IEventListener {
	/**
	 * Constructor.
	 *
	 * @param NlDesignThemeShareableConfigType $theme The theme type.
	 */
	public function __construct(
		private readonly NlDesignThemeShareableConfigType $theme,
	) {

	}//end __construct()

	/**
	 * Contribute the theme type.
	 *
	 * @param Event $event The dispatched event.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/federated-config-sharing/spec.md
	 */
	public function handle(Event $event): void {
		if (($event instanceof RegisterShareableConfigTypesEvent) === false) {
			return;
		}

		$event->registerType(type: $this->theme);

	}//end handle()
}//end class
