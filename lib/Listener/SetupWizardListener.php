<?php

/**
 * Thematiq Setup Wizard Listener.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Listener
 * @package   OCA\Thematiq
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 *
 * @spec openspec/changes/first-time-setup/specs/first-time-setup/spec.md#requirement-the-wizard-opens-for-an-administrator-on-the-first-nextcloud-page-while-setup-is-due-req-tqfs-001
 */

declare(strict_types=1);

namespace OCA\Thematiq\Listener;

use OCA\Thematiq\AppInfo\Application;
use OCA\Thematiq\Service\SetupAudience;
use OCA\Thematiq\Service\SetupStatusService;
use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Opens the first-time setup over whatever page an administrator lands on while setup is due.
 *
 * Only a user page of a member of the `admin` group gets the `setup_status` initial state and the
 * loader script; every other page (a non-admin's, a delegated theming administrator's, a guest,
 * public, error or login page, or any page once setup is done or skipped) gets nothing.
 *
 * @template-implements IEventListener<Event>
 *
 * @spec openspec/changes/first-time-setup/specs/first-time-setup/spec.md#requirement-the-wizard-opens-for-an-administrator-on-the-first-nextcloud-page-while-setup-is-due-req-tqfs-001
 */
class SetupWizardListener implements IEventListener {

	/**
	 * The bundle the loader adds, relative to the app root.
	 *
	 * @var string
	 */
	public const BUNDLE = 'js/vendor/setupWizard.js';

	/**
	 * Constructor.
	 *
	 * @param SetupStatusService $status Whether setup is due, and the status document.
	 * @param SetupAudience $audience Whether the signed-in user is an administrator.
	 * @param IInitialState $initialState Carries `setup_status` to the page.
	 * @param LoggerInterface $logger A failure here never breaks the page.
	 */
	public function __construct(
		private readonly SetupStatusService $status,
		private readonly SetupAudience $audience,
		private readonly IInitialState $initialState,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Add the state and the loader to an administrator's user page while setup is due.
	 *
	 * @param Event $event The event.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/first-time-setup/specs/first-time-setup/spec.md#requirement-the-wizard-opens-for-an-administrator-on-the-first-nextcloud-page-while-setup-is-due-req-tqfs-001
	 */
	public function handle(Event $event): void {
		if ($event instanceof BeforeTemplateRenderedEvent === false || $this->appliesTo(event: $event) === false) {
			return;
		}

		try {
			if ($this->status->isDue() === false || $this->bundleBuilt() === false) {
				return;
			}

			$this->initialState->provideInitialState('setup_status', $this->status->status());
			$this->addLoader();
		} catch (Throwable $e) {
			$this->logger->warning('thematiq: the first-time setup was not offered: {message}', ['app' => Application::APP_ID, 'message' => $e->getMessage()]);
		}
	}//end handle()

	/**
	 * Whether the page is a signed-in administrator's user page.
	 *
	 * @param BeforeTemplateRenderedEvent $event The event.
	 *
	 * @return bool True for a user page of a member of the `admin` group.
	 *
	 * @spec openspec/changes/first-time-setup/specs/first-time-setup/spec.md#requirement-the-wizard-opens-for-an-administrator-on-the-first-nextcloud-page-while-setup-is-due-req-tqfs-001
	 */
	private function appliesTo(BeforeTemplateRenderedEvent $event): bool {
		if ($event->isLoggedIn() === false || $event->getResponse()->getRenderAs() !== TemplateResponse::RENDER_AS_USER) {
			return false;
		}

		return $this->audience->isAdministrator();
	}//end appliesTo()

	/**
	 * Whether the wizard bundle is built; a checkout without a build adds nothing.
	 *
	 * @return bool True when the bundle exists.
	 *
	 * @spec openspec/changes/first-time-setup/specs/first-time-setup/spec.md#requirement-the-wizard-opens-for-an-administrator-on-the-first-nextcloud-page-while-setup-is-due-req-tqfs-001
	 */
	protected function bundleBuilt(): bool {
		return is_file(dirname(__DIR__, 2) . '/' . self::BUNDLE);
	}//end bundleBuilt()

	/**
	 * Add the loader script.
	 *
	 * @return void
	 *
	 * @SuppressWarnings(PHPMD.StaticAccess) \OCP\Util::addScript() is the Nextcloud API for page scripts.
	 *
	 * @spec openspec/changes/first-time-setup/specs/first-time-setup/spec.md#requirement-the-wizard-opens-for-an-administrator-on-the-first-nextcloud-page-while-setup-is-due-req-tqfs-001
	 */
	protected function addLoader(): void {
		\OCP\Util::addScript(Application::APP_ID, 'setup-wizard-loader');
	}//end addLoader()
}//end class
