<?php

/**
 * Thematiq Login Copy Service.
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
 * @spec openspec/changes/login-page-board-parity/specs/workplace-layout/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCA\Thematiq\AppInfo\Application;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * The words on the login page of the light workplace, per token set.
 *
 * Nextcloud's login page says "Log in to {name}" and has no subtitle, and its
 * footer joins the organisation name and the slogan with a dash. The NcLogin
 * boards say "Inloggen bij de werkplek", name who the page is for, and close
 * with a line of their own. None of that is a Nextcloud setting, so a token
 * set carries it in a `login` block in token-sets.json, an administrator may
 * override any part of it in the app config key `login_copy` (the same JSON
 * shape), and js/login-copy.js writes it into the page.
 *
 * Every value is either one string, used in every language, or a map from a
 * language code to a string. A language the map does not name keeps
 * Nextcloud's own words, so a Dutch set never puts Dutch on an English page.
 *
 * Presentation, never a dependency: only the light workplace loads it, the
 * script writes text and never markup, and a failure is logged and leaves
 * Nextcloud's own login page.
 *
 * @spec openspec/changes/login-page-board-parity/specs/workplace-layout/spec.md
 */
class LoginCopyService {

	/**
	 * The initial state key js/login-copy.js reads.
	 *
	 * @var string
	 */
	public const STATE_KEY = 'login-copy';

	/**
	 * The script under `js/`, without the extension.
	 *
	 * @var string
	 */
	public const SCRIPT = 'login-copy';

	/**
	 * The app config key an administrator overrides the set's copy in.
	 *
	 * @var string
	 */
	public const CONFIG_KEY = 'login_copy';

	/**
	 * The keys a `login` block may carry, as written in token-sets.json.
	 *
	 * @var array<int, string>
	 */
	public const KEYS = [
		'title',
		'subtitle',
		'sso_label',
		'username_label',
		'divider',
		'footer',
		'footer_hint',
		'footer_link_label',
		'footer_link_url',
	];

	/**
	 * The longest value accepted; anything longer is dropped, not cut.
	 *
	 * @var int
	 */
	public const MAX_LENGTH = CopyValues::MAX_LENGTH;

	/**
	 * Constructor.
	 *
	 * @param PageCopySources $sources The config, the design systems, the initial state and the language factory.
	 * @param LoggerInterface $logger Records a swallowed failure.
	 * @param GroupThemingService|null $groupTheming Resolves the login page's token set; null in unit tests of {@see self::inject()}.
	 * @param LayoutOptionsService|null $layoutOptions Resolves the login page's layout; null in unit tests of {@see self::inject()}.
	 */
	public function __construct(
		private readonly PageCopySources $sources,
		private readonly LoggerInterface $logger,
		private readonly ?GroupThemingService $groupTheming = null,
		private readonly ?LayoutOptionsService $layoutOptions = null,
	) {
	}//end __construct()

	/**
	 * The login page's entry: resolve the set the page renders with (the
	 * per-group resolution, no app brand: login is never an app page) and
	 * {@see self::inject()} for it. Fails open.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/login-page-board-parity/specs/workplace-layout/spec.md#requirement-the-light-workplace-draws-the-login-card-of-the-board
	 */
	public function injectForLogin(): void {
		if ($this->groupTheming === null || $this->layoutOptions === null) {
			return;
		}

		try {
			$tokenSet = $this->groupTheming->resolveTokenSetForRequest(appBrandSet: null);
		} catch (Throwable $e) {
			$this->logger->warning(
				'thematiq: the login copy could not resolve the token set; the login page keeps Nextcloud\'s words.',
				['app' => Application::APP_ID, 'exception' => $e]
			);
			return;
		}

		$this->inject(tokenSet: $tokenSet, layoutOptions: $this->layoutOptions);
	}//end injectForLogin()

	/**
	 * The copy for a page rendered with this token set, in this language.
	 *
	 * The set's `login` block first, then the administrator's override on top
	 * of it, key by key. Values that are not text, are empty, are too long,
	 * or (for the link) are not a web address are left out.
	 *
	 * @param string $tokenSet The token set the page renders with.
	 * @param string $language The request's language code, e.g. `nl` or `en_GB`.
	 *
	 * @return array<string, string> The resolved copy, keyed in camel case (`ssoLabel`).
	 *
	 * @SuppressWarnings(PHPMD.StaticAccess) - CopyValues holds the pure value rules shared with the not-found page.
	 *
	 * @spec openspec/changes/login-page-board-parity/specs/workplace-layout/spec.md#requirement-a-token-set-may-carry-the-login-copy
	 */
	public function resolveCopy(string $tokenSet, string $language): array {
		$meta = $this->sources->designSystemService->getTokenSetMeta(tokenSetId: $tokenSet);
		$fromSet = ($meta['login'] ?? null);
		$fromAdmin = json_decode($this->sources->config->getAppValue(Application::APP_ID, self::CONFIG_KEY, ''), true);

		$copy = [];
		foreach ([$fromSet, $fromAdmin] as $source) {
			if (is_array($source) === true) {
				$copy = array_replace($copy, $this->sourceCopy(source: $source, language: $language));
			}
		}

		return $copy;
	}//end resolveCopy()

	/**
	 * The values one source gives that may be shown, in key order, under their camel-case names.
	 *
	 * @param array<array-key, mixed> $source The set's `login` block or the administrator's override.
	 * @param string $language The request's language.
	 *
	 * @return array<string, string> The copy.
	 *
	 * @SuppressWarnings(PHPMD.StaticAccess) - CopyValues holds the pure value rules shared with the not-found page.
	 */
	private function sourceCopy(array $source, string $language): array {
		$copy = [];
		foreach (self::KEYS as $key) {
			$value = CopyValues::pick(value: ($source[$key] ?? null), language: $language);
			if ($this->isShown(key: $key, value: $value) === true) {
				$copy[CopyValues::camel(key: $key)] = $value;
			}
		}

		return $copy;
	}//end sourceCopy()

	/**
	 * Whether a picked value may be shown: any value, and for the footer link only a web address or a path here.
	 *
	 * @param string $key The copy key.
	 * @param string|null $value The picked value, null when there is none.
	 *
	 * @return bool True when it may be shown.
	 *
	 * @SuppressWarnings(PHPMD.StaticAccess) - CopyValues holds the pure value rules shared with the not-found page.
	 */
	private function isShown(string $key, string|null $value): bool {
		if ($value === null) {
			return false;
		}

		return ($key !== 'footer_link_url' || CopyValues::isAddress(value: $value) === true);
	}//end isShown()

	/**
	 * Provide the copy and load the script, for a login page drawn by the
	 * light workplace. Fails open.
	 *
	 * The script also places the alternative logins (the SSO button) above
	 * the form with a divider, so it loads with the light layout even when
	 * the set names no copy; the divider then reads this app's "or".
	 *
	 * @param string $tokenSet The token set the page renders with.
	 * @param LayoutOptionsService $layoutOptions Resolves the page's layout.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/login-page-board-parity/specs/workplace-layout/spec.md#requirement-the-light-workplace-draws-the-login-card-of-the-board
	 */
	public function inject(string $tokenSet, LayoutOptionsService $layoutOptions): void {
		try {
			if ($layoutOptions->workplaceLayout(tokenSet: $tokenSet) === LayoutOptionsService::LAYOUT_LIGHT) {
				$this->provideCopy(tokenSet: $tokenSet);
			}
		} catch (Throwable $e) {
			$this->logger->warning(
				'thematiq: the login copy failed for this render; the login page keeps Nextcloud\'s words.',
				['app' => Application::APP_ID, 'exception' => $e]
			);
		}//end try
	}//end inject()

	/**
	 * Provide the copy, with a divider in the request's language when none is set, and load the script.
	 *
	 * @param string $tokenSet The active token set.
	 *
	 * @return void
	 */
	private function provideCopy(string $tokenSet): void {
		$l10n = $this->sources->l10nFactory->get(Application::APP_ID);
		$copy = $this->resolveCopy(tokenSet: $tokenSet, language: $l10n->getLanguageCode());
		$copy['divider'] = ($copy['divider'] ?? $l10n->t('or'));

		$this->sources->initialState->provideInitialState(self::STATE_KEY, $copy);
		$this->emitScript();
	}//end provideCopy()

	/**
	 * Load js/login-copy.js. A seam, so unit tests need no Nextcloud bootstrap.
	 *
	 * @return void
	 *
	 * @SuppressWarnings(PHPMD.StaticAccess) - \OCP\Util is the Nextcloud API for asset injection.
	 *
	 * @spec openspec/changes/login-page-board-parity/specs/workplace-layout/spec.md#requirement-the-light-workplace-draws-the-login-card-of-the-board
	 */
	protected function emitScript(): void {
		\OCP\Util::addScript(application: Application::APP_ID, file: self::SCRIPT);
	}//end emitScript()
}//end class
