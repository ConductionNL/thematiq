<?php

/**
 * Thematiq Header User Service.
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
 * @spec openspec/changes/header-style-workplace/specs/workplace-layout/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCA\Thematiq\AppInfo\Application;
use OCP\Accounts\IAccountManager;
use OCP\AppFramework\Services\IInitialState;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * The name and the role of the signed-in person, for the workplace top bar.
 *
 * Nextcloud's account menu shows an avatar and nothing else. The workplace
 * header style (the DqKop board) shows the name beside it and, under the
 * name, the role the person filled in on their profile (the account's `role`
 * property). This service hands those two to js/header-user.js as initial
 * state and loads that script, only on a page whose header style resolves to
 * `workplace` ({@see LayoutOptionsService::stylesheets()}).
 *
 * The person sees only their own name and role, both of which they can see
 * and change on their own profile page, whatever the scope of the property.
 *
 * Presentation, never a dependency: with nobody signed in it does nothing,
 * and a failure is logged and leaves the page with Nextcloud's own avatar.
 *
 * @spec openspec/changes/header-style-workplace/specs/workplace-layout/spec.md
 */
class HeaderUserService {

	/**
	 * The initial state key js/header-user.js reads.
	 *
	 * @var string
	 */
	public const STATE_KEY = 'header-user';

	/**
	 * The script under `js/`, without the extension.
	 *
	 * @var string
	 */
	public const SCRIPT = 'header-user';

	/**
	 * Constructor.
	 *
	 * @param IUserSession $userSession The signed-in person.
	 * @param IAccountManager $accountManager Reads the profile's role.
	 * @param IInitialState $initialState The initial state channel.
	 * @param LoggerInterface $logger Records a swallowed failure.
	 */
	public function __construct(
		private readonly IUserSession $userSession,
		private readonly IAccountManager $accountManager,
		private readonly IInitialState $initialState,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * The signed-in person's name and role, or null with nobody signed in.
	 *
	 * @return array{name: string, role: string}|null The person; the role is empty when the profile names none.
	 *
	 * @spec openspec/changes/header-style-workplace/specs/workplace-layout/spec.md#requirement-the-workplace-header-shows-the-name-and-the-role
	 */
	public function person(): ?array {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return null;
		}

		$account = $this->accountManager->getAccount($user);
		$property = $account->getProperty(IAccountManager::PROPERTY_ROLE);
		$role = trim($property->getValue());

		return [
			'name' => trim($user->getDisplayName()),
			'role' => $role,
		];
	}//end person()

	/**
	 * Provide the person and load the script, for a page whose header style
	 * resolves to `workplace` with the light layout (the page then also loads
	 * `css/header-workplace.css`). Fails open.
	 *
	 * @param string $tokenSet The token set the page renders with.
	 * @param LayoutOptionsService $layoutOptions Resolves the page's layout stylesheets.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/header-style-workplace/specs/workplace-layout/spec.md#requirement-the-workplace-header-shows-the-name-and-the-role
	 */
	public function inject(string $tokenSet, LayoutOptionsService $layoutOptions): void {
		try {
			$sheets = $layoutOptions->stylesheets(tokenSet: $tokenSet);
			if (in_array(LayoutOptionsService::HEADER_WORKPLACE_STYLESHEET, $sheets, true) === false) {
				return;
			}

			$person = $this->person();
			if ($person === null || $person['name'] === '') {
				return;
			}

			$this->initialState->provideInitialState(self::STATE_KEY, $person);
			$this->emitScript();
		} catch (Throwable $e) {
			$this->logger->warning(
				'thematiq: the name and role in the top bar failed for this render; the bar keeps the avatar.',
				['app' => Application::APP_ID, 'exception' => $e]
			);
		}
	}//end inject()

	/**
	 * Load js/header-user.js. A seam, so unit tests need no Nextcloud bootstrap.
	 *
	 * @return void
	 *
	 * @SuppressWarnings(PHPMD.StaticAccess) - \OCP\Util is the Nextcloud API for asset injection.
	 *
	 * @spec openspec/changes/header-style-workplace/specs/workplace-layout/spec.md#requirement-the-workplace-header-shows-the-name-and-the-role
	 */
	protected function emitScript(): void {
		\OCP\Util::addScript(application: Application::APP_ID, file: self::SCRIPT);
	}//end emitScript()
}//end class
